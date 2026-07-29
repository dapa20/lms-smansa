<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/tugas_ujian.php');
}

$tugasId  = (int)($_POST['tugas_id'] ?? 0);
$siswaIds = $_POST['siswa_id'] ?? [];
$nilaiArr = $_POST['nilai'] ?? [];

if ($tugasId === 0 || !is_array($siswaIds)) {
    redirect('../../pages/tugas_ujian.php');
}

$stmt = $pdo->prepare("INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, nilai, status, waktu_kumpul)
                        VALUES (?, ?, ?, 'dinilai', NOW())
                        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), status = 'dinilai'");

$jumlahDisimpan = 0;
foreach ($siswaIds as $i => $siswaId) {
    $nilai = $nilaiArr[$i] ?? '';
    if ($nilai === '' || $nilai === null) {
        continue; // lewati siswa yang belum diberi nilai
    }
    $nilai = max(0, min(100, (float)$nilai));
    $stmt->execute([$tugasId, (int)$siswaId, $nilai]);
    $jumlahDisimpan++;
}

setFlash('sukses', "Nilai berhasil disimpan untuk $jumlahDisimpan siswa.");
redirect('../../pages/tugas_ujian.php');
