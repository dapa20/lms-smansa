<?php
/**
 * API Daftar Tugas & Ujian Siswa
 * GET /api/tugas.php
 * Header Authorization: Bearer <token>
 * Query opsional: ?jenis=tugas|kuis|uts|uas, ?status=aktif|selesai, ?belum=1 (filter belum dikumpul)
 */
require_once __DIR__ . '/config.php';

$siswa = requireAuth();
$kelasId = (int)$siswa['kelas_id'];

$jenis  = $_GET['jenis'] ?? '';
$status = $_GET['status'] ?? '';
$belum  = $_GET['belum'] ?? '';

$sql = "SELECT t.id, t.judul, t.jenis, t.deskripsi, t.tanggal_deadline, t.status AS status_tugas,
               mp.nama_mapel, mp.kode_mapel,
               u.nama_lengkap AS nama_guru,
               COALESCE(pt.status, 'belum') AS status_kumpul,
               pt.waktu_kumpul, pt.nilai, pt.file_jawaban
        FROM tugas_ujian t
        JOIN mata_pelajaran mp ON mp.id = t.mapel_id
        JOIN users u ON u.id = t.dibuat_oleh
        LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = ?
        WHERE t.kelas_id = ?";
$params = [$siswa['id'], $kelasId];

if ($jenis !== '') {
    $sql .= " AND t.jenis = ?";
    $params[] = $jenis;
}
if ($status !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $status;
}
if ($belum === '1') {
    $sql .= " AND COALESCE(pt.status, 'belum') = 'belum'";
}

$sql .= " ORDER BY t.tanggal_deadline ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tugas = $stmt->fetchAll();

foreach ($tugas as &$t) {
    $t['id'] = (int)$t['id'];
    $t['tanggal_deadline'] = isoDate($t['tanggal_deadline']);
    $t['waktu_kumpul'] = isoDate($t['waktu_kumpul']);
    $t['nilai'] = $t['nilai'] !== null ? (float)$t['nilai'] : null;
    // Tentukan status tampilan untuk aplikasi
    $sudahLewat = strtotime($t['tanggal_deadline']) < time();
    if ($t['status_kumpul'] === 'belum') {
        $t['display_status'] = $sudahLewat ? 'terlambat' : 'menunggu';
    } else {
        $t['display_status'] = $t['status_kumpul']; // terkumpul / dinilai
    }
}
unset($t);

json_out(['success' => true, 'data' => $tugas]);

