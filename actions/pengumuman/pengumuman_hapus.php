<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$redirectTo = trim($_GET['redirect_to'] ?? $_POST['redirect_to'] ?? '../../index.php');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    setFlash('gagal', 'ID pengumuman tidak valid.');
    redirect($redirectTo);
}

$user = currentUser();
$isAdmin = isAdmin();

try {
    if ($isAdmin) {
        $stmt = $pdo->prepare('DELETE FROM pengumuman WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM pengumuman WHERE id = ? AND dibuat_oleh = ?');
        $stmt->execute([$id, $user['id']]);
    }
    
    if ($stmt->rowCount() > 0) {
        setFlash('sukses', 'Pengumuman berhasil dihapus.');
    } else {
        setFlash('gagal', 'Pengumuman tidak ditemukan atau Anda tidak memiliki akses.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menghapus pengumuman.');
}

redirect($redirectTo);
