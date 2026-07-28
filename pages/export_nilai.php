<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$kelasId     = (int)($_GET['kelas_id'] ?? 0);
$mapelId     = (int)($_GET['mapel_id'] ?? 0);
$semester    = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? '2024/2025';

if ($kelasId === 0 || $mapelId === 0) {
    redirect('rekap_nilai.php');
}

if (!isAdmin()) {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ?');
    $stmt->execute([$_SESSION['user_id'], $kelasId, $mapelId]);
    if ((int)$stmt->fetch()['c'] === 0) {
        redirect('rekap_nilai.php');
    }
}

$stmtInfo = $pdo->prepare('SELECT nama_kelas FROM kelas WHERE id = ?');
$stmtInfo->execute([$kelasId]);
$namaKelas = $stmtInfo->fetch()['nama_kelas'] ?? 'Kelas';

$stmtInfo = $pdo->prepare('SELECT nama_mapel FROM mata_pelajaran WHERE id = ?');
$stmtInfo->execute([$mapelId]);
$namaMapel = $stmtInfo->fetch()['nama_mapel'] ?? 'Mapel';

$stmt = $pdo->prepare("SELECT s.nis, s.nisn, s.nama_lengkap,
                               n.tugas_1, n.tugas_2, n.tugas_3, n.uts, n.uas, n.catatan
                        FROM siswa s
                        LEFT JOIN nilai n ON n.siswa_id = s.id AND n.mapel_id = ? AND n.semester = ? AND n.tahun_ajaran = ?
                        WHERE s.kelas_id = ? AND s.status = 'aktif'
                        ORDER BY s.nama_lengkap");
$stmt->execute([$mapelId, $semester, $tahunAjaran, $kelasId]);
$data = $stmt->fetchAll();

$namaFile = 'Rekap_Nilai_' . preg_replace('/[^A-Za-z0-9_]/', '_', "{$namaKelas}_{$namaMapel}_{$semester}") . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM supaya karakter dibaca benar oleh Excel
fputcsv($out, ['Rekap Nilai', $namaMapel, $namaKelas, "Semester $semester $tahunAjaran"]);
fputcsv($out, []);
fputcsv($out, ['NIS', 'NISN', 'Nama Lengkap', 'Tugas 1', 'Tugas 2', 'Tugas 3', 'UTS', 'UAS', 'Nilai Akhir', 'Catatan']);

foreach ($data as $row) {
    $nilaiAkhir = hitungNilaiAkhir($row['tugas_1'], $row['tugas_2'], $row['tugas_3'], $row['uts'], $row['uas']);
    fputcsv($out, [
        $row['nis'], $row['nisn'], $row['nama_lengkap'],
        $row['tugas_1'] ?? '', $row['tugas_2'] ?? '', $row['tugas_3'] ?? '',
        $row['uts'] ?? '', $row['uas'] ?? '', $nilaiAkhir ?? '', $row['catatan'] ?? '',
    ]);
}
fclose($out);
exit;
