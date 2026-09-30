<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/rekap_nilai.php?tab=indikator'); }

$id = (int)($_POST['id'] ?? 0);
$mapelId = (int)($_POST['mapel_id'] ?? 0);
if ($id === 0) { redirect('../../pages/rekap_nilai.php?tab=indikator'); }

$stmt = $pdo->prepare('DELETE FROM indikator_nilai WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Indikator berhasil dihapus.');
redirect('../../pages/rekap_nilai.php?tab=indikator&mapel_id='.$mapelId);