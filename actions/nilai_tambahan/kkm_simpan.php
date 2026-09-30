<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/rekap_nilai.php?tab=kkm'); }

$id          = (int)($_POST['id'] ?? 0);
$mapelId     = (int)($_POST['mapel_id'] ?? 0);
$kelasId     = (int)($_POST['kelas_id'] ?? 0);
$kkm         = (float)($_POST['kkm'] ?? 75);
$bobotTugas  = (float)($_POST['bobot_tugas'] ?? 30);
$bobotUts    = (float)($_POST['bobot_uts'] ?? 30);
$bobotUas    = (float)($_POST['bobot_uas'] ?? 40);
$deskripsi   = trim($_POST['deskripsi'] ?? '');

if ($mapelId === 0 || $kelasId === 0) {
    setFlash('gagal', 'Mata pelajaran dan kelas wajib dipilih.');
    redirect('../../pages/rekap_nilai.php?tab=kkm');
}

// Validasi total bobot = 100
if (abs($bobotTugas + $bobotUts + $bobotUas - 100) > 0.01) {
    setFlash('gagal', 'Total bobot harus 100%. Saat ini: '.($bobotTugas+$bobotUts+$bobotUas).'%');
    redirect('../../pages/rekap_nilai.php?tab=kkm&mapel_id='.$mapelId.'&kelas_id='.$kelasId);
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE kkm_mapel SET kkm=?, bobot_tugas=?, bobot_uts=?, bobot_uas=?, deskripsi=? WHERE id=?');
        $stmt->execute([$kkm, $bobotTugas, $bobotUts, $bobotUas, $deskripsi, $id]);
        setFlash('sukses', 'Data KKM berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO kkm_mapel (mapel_id, kelas_id, kkm, bobot_tugas, bobot_uts, bobot_uas, deskripsi) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE kkm=VALUES(kkm), bobot_tugas=VALUES(bobot_tugas), bobot_uts=VALUES(bobot_uts), bobot_uas=VALUES(bobot_uas), deskripsi=VALUES(deskripsi)');
        $stmt->execute([$mapelId, $kelasId, $kkm, $bobotTugas, $bobotUts, $bobotUas, $deskripsi]);
        setFlash('sukses', 'Data KKM berhasil disimpan.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan KKM.');
}
redirect('../../pages/rekap_nilai.php?tab=kkm&mapel_id='.$mapelId.'&kelas_id='.$kelasId);