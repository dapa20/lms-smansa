<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/data_siswa.php?tab=poin'); }

$id = (int)($_POST['id'] ?? 0);
$kelasId = (int)($_POST['kelas_id'] ?? 0);
if ($id === 0) { redirect('../../pages/data_siswa.php?tab=poin'); }

$stmt = $pdo->prepare('DELETE FROM poin_siswa WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Data poin berhasil dihapus.');
redirect('../../pages/data_siswa.php?tab=poin&kelas_id='.$kelasId);