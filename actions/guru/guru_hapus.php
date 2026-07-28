<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../data_siswa.php?tab=guru');
}

$id = (int)($_POST['id'] ?? 0);

if ($id === (int)$_SESSION['user_id']) {
    setFlash('gagal', 'Anda tidak bisa menghapus akun Anda sendiri.');
    redirect('../../data_siswa.php?tab=guru');
}

if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    setFlash('sukses', 'Akun berhasil dihapus.');
}

redirect('../data_siswa.php?tab=guru');
