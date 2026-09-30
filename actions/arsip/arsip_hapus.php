<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/export_nilai.php?tab=arsip'); }

$id = (int)($_POST['id'] ?? 0);
$kelasId = (int)($_POST['kelas_id'] ?? 0);
if ($id === 0) { redirect('../../pages/export_nilai.php?tab=arsip'); }

$stmt = $pdo->prepare('DELETE FROM arsip_rapor WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Arsip rapor berhasil dihapus.');
redirect('../../pages/export_nilai.php?tab=arsip&kelas_id='.$kelasId);