<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/data_siswa.php?tab=struktur'); }

if (!$isAdmin) { setFlash('gagal', 'Hanya admin yang dapat menghapus struktur.'); redirect('../../pages/data_siswa.php?tab=struktur'); }

$id = (int)($_POST['id'] ?? 0);
$kelasId = (int)($_POST['kelas_id'] ?? 0);
if ($id === 0) { redirect('../../pages/data_siswa.php?tab=struktur'); }

$stmt = $pdo->prepare('DELETE FROM struktur_kelas WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Anggota struktur berhasil dihapus.');
redirect('../../pages/data_siswa.php?tab=struktur&kelas_id='.$kelasId);