<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/materi.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT nama_file, diunggah_oleh FROM materi WHERE id = ?');
    $stmt->execute([$id]);
    $materi = $stmt->fetch();

    if ($materi && !isAdmin() && (int)$materi['diunggah_oleh'] !== (int)$_SESSION['user_id']) {
        setFlash('gagal', 'Anda hanya bisa menghapus materi yang Anda unggah sendiri.');
        redirect('../../pages/materi.php');
    }

    if ($materi) {
        $pathFile = __DIR__ . '/../../uploads/materi/' . $materi['nama_file'];
        if (is_file($pathFile)) {
            unlink($pathFile);
        }
        $stmt = $pdo->prepare('DELETE FROM materi WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('sukses', 'Materi berhasil dihapus.');
    }
}

redirect('../../pages/materi.php');
