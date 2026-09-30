<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/rekap_nilai.php?tab=spiritual'); }

$id          = (int)($_POST['id'] ?? 0);
$kelasId     = (int)($_POST['kelas_id'] ?? 0);
$jenis       = in_array($_POST['jenis'] ?? '', ['spiritual','sosial']) ? $_POST['jenis'] : 'spiritual';
if ($id === 0) { redirect('../../pages/rekap_nilai.php?tab='.$jenis); }

$stmt = $pdo->prepare('DELETE FROM nilai_sikap WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Nilai sikap berhasil dihapus.');
redirect('../../pages/rekap_nilai.php?tab='.$jenis.'&kelas_id='.$kelasId);