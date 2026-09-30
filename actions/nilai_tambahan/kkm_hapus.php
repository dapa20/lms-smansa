<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/rekap_nilai.php?tab=kkm'); }

$id = (int)($_POST['id'] ?? 0);
$mapelId = (int)($_POST['mapel_id'] ?? 0);
$kelasId = (int)($_POST['kelas_id'] ?? 0);
if ($id === 0) { redirect('../../pages/rekap_nilai.php?tab=kkm'); }

$stmt = $pdo->prepare('DELETE FROM kkm_mapel WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Data KKM berhasil dihapus.');
redirect('../../pages/rekap_nilai.php?tab=kkm&mapel_id='.$mapelId.'&kelas_id='.$kelasId);