<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/rekap_nilai.php?tab=prestasi'); }

$id = (int)($_POST['id'] ?? 0);
$kelasId = (int)($_POST['kelas_id'] ?? 0);
if ($id === 0) { redirect('../../pages/rekap_nilai.php?tab=prestasi'); }

$stmt = $pdo->prepare('DELETE FROM prestasi_siswa WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Prestasi berhasil dihapus.');
redirect('../../pages/rekap_nilai.php?tab=prestasi&kelas_id='.$kelasId);