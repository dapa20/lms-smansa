<?php
/**
 * API Dashboard (Beranda Siswa)
 * GET /api/dashboard.php
 * Header Authorization: Bearer <token>
 * Response: ringkasan beranda (tugas aktif, kehadiran, materi terbaru, pengumuman)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

$siswa = requireAuth();
$kelasId = (int)$siswa['kelas_id'];

// ─── Tugas aktif (belum lewat deadline) untuk kelas siswa ───────────
$stmt = $pdo->prepare("SELECT t.id, t.judul, t.jenis, t.deskripsi, t.tanggal_deadline,
                              mp.nama_mapel, mp.kode_mapel,
                              COALESCE(pt.status, 'belum') AS status_kumpul,
                              pt.nilai
                       FROM tugas_ujian t
                       JOIN mata_pelajaran mp ON mp.id = t.mapel_id
                       LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = ?
                       WHERE t.kelas_id = ? AND t.status = 'aktif'
                       ORDER BY t.tanggal_deadline ASC
                       LIMIT 5");
$stmt->execute([$siswa['id'], $kelasId]);
$tugasAktif = $stmt->fetchAll();
foreach ($tugasAktif as &$t) {
    $t['id'] = (int)$t['id'];
    $t['tanggal_deadline'] = isoDate($t['tanggal_deadline']);
}
unset($t);

$tugasBelum = array_values(array_filter($tugasAktif, fn($t) => $t['status_kumpul'] === 'belum'));

// ─── Persentase kehadiran (30 hari terakhir) ────────────────────────
$stmt = $pdo->prepare("SELECT
                          COUNT(*) AS total,
                          SUM(status = 'hadir') AS hadir
                       FROM kehadiran
                       WHERE siswa_id = ? AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$siswa['id']]);
$absen = $stmt->fetch();
$persenHadir = ($absen && $absen['total'] > 0) ? round(($absen['hadir'] / $absen['total']) * 100, 1) : 0.0;

// ─── Materi terbaru untuk tingkat kelas siswa ───────────────────────
$stmt = $pdo->prepare("SELECT m.id, m.judul, m.tipe_file, m.nama_file, m.nama_file_asli, m.ukuran_file, m.created_at,
                              mp.nama_mapel
                       FROM materi m
                       JOIN mata_pelajaran mp ON mp.id = m.mapel_id
                       WHERE m.kelas_tingkat = ?
                       ORDER BY m.created_at DESC
                       LIMIT 5");
$stmt->execute([$siswa['tingkat']]);
$materiTerbaru = $stmt->fetchAll();
foreach ($materiTerbaru as &$m) {
    $m['id'] = (int)$m['id'];
    $m['created_at'] = isoDate($m['created_at']);
    $m['url'] = materiUrl($m['nama_file']);
}
unset($m);

// ─── Pengumuman ─────────────────────────────────────────────────────
$stmt = $pdo->query("SELECT id, judul, isi, kategori, created_at FROM pengumuman ORDER BY created_at DESC LIMIT 4");
$pengumuman = $stmt->fetchAll();
foreach ($pengumuman as &$p) {
    $p['id'] = (int)$p['id'];
    $p['created_at'] = isoDate($p['created_at']);
}
unset($p);

json_out([
    'success' => true,
    'data' => [
        'siswa' => [
            'id' => (int)$siswa['id'],
            'nis' => $siswa['nis'],
            'nama_lengkap' => $siswa['nama_lengkap'],
            'nama_kelas' => $siswa['nama_kelas'] ?? '-',
            'tingkat' => $siswa['tingkat'],
        ],
        'tugas_aktif' => $tugasAktif,
        'tugas_belum' => count($tugasBelum),
        'persen_kehadiran' => $persenHadir,
        'materi_terbaru' => $materiTerbaru,
        'pengumuman' => $pengumuman,
    ],
]);
