<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/data_siswa.php?tab=kenaikan'); }

if (!$isAdmin) { setFlash('gagal', 'Hanya admin yang dapat menghapus data kenaikan.'); redirect('../../pages/data_siswa.php?tab=kenaikan'); }

$id = (int)($_POST['id'] ?? 0);
if ($id === 0) { redirect('../../pages/data_siswa.php?tab=kenaikan'); }

$stmt = $pdo->prepare('DELETE FROM kenaikan_kelas WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Data kenaikan berhasil dihapus.');
redirect('../../pages/data_siswa.php?tab=kenaikan');