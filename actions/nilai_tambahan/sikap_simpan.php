<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/rekap_nilai.php'); }

$id          = (int)($_POST['id'] ?? 0);
$siswaId     = (int)($_POST['siswa_id'] ?? 0);
$kelasId     = (int)($_POST['kelas_id'] ?? 0);
$jenis       = in_array($_POST['jenis'] ?? '', ['spiritual','sosial']) ? $_POST['jenis'] : 'spiritual';
$semester    = in_array($_POST['semester'] ?? '', ['Ganjil','Genap']) ? $_POST['semester'] : 'Ganjil';
$tahunAjaran = trim($_POST['tahun_ajaran'] ?? '2024/2025');
$predikat    = in_array($_POST['predikat'] ?? '', ['Sangat Baik','Baik','Cukup','Perlu Bimbingan']) ? $_POST['predikat'] : 'Baik';
$deskripsi   = trim($_POST['deskripsi'] ?? '');

// Tab tujuan setelah simpan (spiritual atau sosial)
$tabTujuan = ($_POST['tab_tujuan'] ?? $jenis) === 'sosial' ? 'sosial' : 'spiritual';

if ($siswaId === 0 || $kelasId === 0) {
    setFlash('gagal', 'Siswa dan kelas wajib dipilih.');
    redirect('../../pages/rekap_nilai.php?tab='.$tabTujuan);
}

try {
    // Upsert
    $stmt = $pdo->prepare("INSERT INTO nilai_sikap (siswa_id, kelas_id, jenis, semester, tahun_ajaran, predikat, deskripsi, dibuat_oleh)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE predikat=VALUES(predikat), deskripsi=VALUES(deskripsi), dibuat_oleh=VALUES(dibuat_oleh)");
    $stmt->execute([$siswaId, $kelasId, $jenis, $semester, $tahunAjaran, $predikat, $deskripsi, $user['id']]);
    setFlash('sukses', 'Nilai sikap berhasil disimpan.');
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan nilai sikap.');
}
redirect('../../pages/rekap_nilai.php?tab='.$tabTujuan.'&kelas_id='.$kelasId);