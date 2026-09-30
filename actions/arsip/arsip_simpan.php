<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/export_nilai.php?tab=arsip'); }

$id          = (int)($_POST['id'] ?? 0);
$siswaId     = (int)($_POST['siswa_id'] ?? 0);
$kelasId     = (int)($_POST['kelas_id'] ?? 0);
$semester    = in_array($_POST['semester'] ?? '', ['Ganjil','Genap']) ? $_POST['semester'] : 'Ganjil';
$tahunAjaran = trim($_POST['tahun_ajaran'] ?? '2024/2025');
$rataRata    = $_POST['rata_rata'] !== '' ? (float)$_POST['rata_rata'] : null;
$peringkat   = $_POST['peringkat'] !== '' ? (int)$_POST['peringkat'] : null;
$status      = in_array($_POST['status'] ?? '', ['cetak','diarsipkan']) ? $_POST['status'] : 'cetak';
$catatan     = trim($_POST['catatan'] ?? '');

if ($siswaId === 0 || $kelasId === 0) {
    setFlash('gagal', 'Siswa dan kelas wajib dipilih.');
    redirect('../../pages/export_nilai.php?tab=arsip');
}

try {
    $stmt = $pdo->prepare("INSERT INTO arsip_rapor (siswa_id, kelas_id, semester, tahun_ajaran, rata_rata, peringkat, status, catatan, dibuat_oleh)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE rata_rata=VALUES(rata_rata), peringkat=VALUES(peringkat), status=VALUES(status), catatan=VALUES(catatan)");
    $stmt->execute([$siswaId, $kelasId, $semester, $tahunAjaran, $rataRata, $peringkat, $status, $catatan, $user['id']]);
    setFlash('sukses', 'Data arsip rapor berhasil disimpan.');
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan arsip rapor.');
}
redirect('../../pages/export_nilai.php?tab=arsip&kelas_id='.$kelasId);