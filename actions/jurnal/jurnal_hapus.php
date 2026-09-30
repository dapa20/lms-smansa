<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/kelas_jadwal.php?tab=jurnal'); }

$id = (int)($_POST['id'] ?? 0);
if ($id === 0) { redirect('../../pages/kelas_jadwal.php?tab=jurnal'); }

// Validasi: hanya admin atau pemilik jurnal yang boleh menghapus
if (!$isAdmin) {
    $stmt = $pdo->prepare('SELECT guru_id FROM jurnal_mengajar WHERE id=?');
    $stmt->execute([$id]);
    $j = $stmt->fetch();
    if (!$j || (int)$j['guru_id'] !== (int)$user['id']) {
        setFlash('gagal', 'Anda tidak berwenang menghapus jurnal ini.');
        redirect('../../pages/kelas_jadwal.php?tab=jurnal');
    }
}

$stmt = $pdo->prepare('DELETE FROM jurnal_mengajar WHERE id=?');
$stmt->execute([$id]);
setFlash('sukses', 'Jurnal mengajar berhasil dihapus.');
redirect('../../pages/kelas_jadwal.php?tab=jurnal');