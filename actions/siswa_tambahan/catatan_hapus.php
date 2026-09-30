<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/data_siswa.php?tab=catatan'); }

$id = (int)($_POST['id'] ?? 0);
$kelasId = (int)($_POST['kelas_id'] ?? 0);
if ($id === 0) { redirect('../../pages/data_siswa.php?tab=catatan'); }

$stmt = $pdo->prepare('DELETE FROM catatan_wali WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Catatan berhasil dihapus.');
redirect('../../pages/data_siswa.php?tab=catatan&kelas_id='.$kelasId);