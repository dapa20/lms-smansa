<?php
/**
 * API Materi Pembelajaran Siswa
 * GET /api/materi.php
 * Header Authorization: Bearer <token>
 *
 * Response: daftar mata pelajaran yang dipelajari siswa (berdasarkan jadwal kelas),
 * masing-masing berisi section & item konten dari tabel materi_section / materi_item.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

$siswa = requireAuth();
$kelasId = (int)$siswa['kelas_id'];
$tingkat = $siswa['tingkat'] ?? '';

// Cek apakah level detail diminta
$mapelId = (int)($_GET['mapel_id'] ?? 0);
$detail = $_GET['detail'] ?? '';

// ─── Ambil daftar mapel yang dipelajari siswa (dari jadwal mengajar kelas) ───
$stmt = $pdo->prepare("SELECT DISTINCT mp.id, mp.kode_mapel, mp.nama_mapel,
                              u.nama_lengkap AS nama_guru
                       FROM jadwal_mengajar j
                       JOIN mata_pelajaran mp ON mp.id = j.mapel_id
                       JOIN users u ON u.id = j.guru_id
                       WHERE j.kelas_id = ?
                       ORDER BY mp.nama_mapel");
$stmt->execute([$kelasId]);
$daftarMapel = $stmt->fetchAll();

// ─── Mode detail: tampilkan section & item untuk mapel tertentu ───
if ($mapelId > 0) {
    // Validasi mapel itu memang dipelajari kelas siswa
    $valid = false;
    foreach ($daftarMapel as $m) {
        if ((int)$m['id'] === $mapelId) {
            $valid = true;
            break;
        }
    }
    if (!$valid) {
        json_error('Mata pelajaran tidak ditemukan untuk kelas Anda.', 404);
    }

    // Info mapel & guru
    $infoMapel = null;
    foreach ($daftarMapel as $m) {
        if ((int)$m['id'] === $mapelId) {
            $infoMapel = $m;
            break;
        }
    }

    // Section
    $stmt = $pdo->prepare("SELECT * FROM materi_section WHERE kelas_id = ? AND mapel_id = ? ORDER BY urutan ASC, id ASC");
    $stmt->execute([$kelasId, $mapelId]);
    $sections = $stmt->fetchAll();

    $sectionList = [];
    foreach ($sections as $s) {
        $stmtItem = $pdo->prepare("SELECT mi.id, mi.tipe, mi.judul, mi.deskripsi, mi.url_link,
                                          mi.nama_file, mi.nama_file_asli, mi.ukuran_file, mi.created_at,
                                          u.nama_lengkap AS nama_pengunggah
                                   FROM materi_item mi
                                   JOIN users u ON u.id = mi.diunggah_oleh
                                   WHERE mi.section_id = ?
                                   ORDER BY mi.created_at ASC");
        $stmtItem->execute([$s['id']]);
        $items = $stmtItem->fetchAll();

        foreach ($items as &$it) {
            $it['id'] = (int)$it['id'];
            $it['ukuran_file'] = $it['ukuran_file'] !== null ? (int)$it['ukuran_file'] : 0;
            $it['created_at'] = isoDate($it['created_at']);
            $it['url_file'] = !empty($it['nama_file']) ? materiUrl($it['nama_file']) : null;
        }
        unset($it);

        $sectionList[] = [
            'id' => (int)$s['id'],
            'judul' => $s['judul'],
            'urutan' => (int)$s['urutan'],
            'items' => $items,
        ];
    }

    json_out([
        'success' => true,
        'data' => [
            'mapel' => [
                'id' => (int)$infoMapel['id'],
                'kode_mapel' => $infoMapel['kode_mapel'],
                'nama_mapel' => $infoMapel['nama_mapel'],
                'nama_guru' => $infoMapel['nama_guru'],
            ],
            'sections' => $sectionList,
        ],
    ]);
}

// ─── Mode daftar: tampilkan mapel + jumlah materi ───
$result = [];
foreach ($daftarMapel as $m) {
    // Hitung jumlah materi di tabel `materi` untuk tingkat tsb + mapel (fallback)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM materi WHERE mapel_id = ? AND kelas_tingkat = ?");
    $stmt->execute([$m['id'], $tingkat]);
    $jumlahMateri = (int)$stmt->fetchColumn();

    // Jumlah konten dari materi_item (lebih akurat berdasarkan kelas)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM materi_item mi
                           JOIN materi_section ms ON ms.id = mi.section_id
                           WHERE ms.kelas_id = ? AND ms.mapel_id = ?");
    $stmt->execute([$kelasId, $m['id']]);
    $jumlahKonten = (int)$stmt->fetchColumn();

    $result[] = [
        'id' => (int)$m['id'],
        'kode_mapel' => $m['kode_mapel'],
        'nama_mapel' => $m['nama_mapel'],
        'nama_guru' => $m['nama_guru'],
        'jumlah_materi' => $jumlahMateri + $jumlahKonten,
    ];
}

json_out(['success' => true, 'data' => $result]);

