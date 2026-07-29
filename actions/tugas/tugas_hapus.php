<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/tugas_ujian.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    if (!isAdmin()) {
        $stmt = $pdo->prepare('SELECT dibuat_oleh FROM tugas_ujian WHERE id = ?');
        $stmt->execute([$id]);
        $pemilik = $stmt->fetch();
        if (!$pemilik || (int)$pemilik['dibuat_oleh'] !== (int)$_SESSION['user_id']) {
            setFlash('gagal', 'Anda hanya bisa menghapus tugas/ujian yang Anda buat sendiri.');
            redirect('../../pages/tugas_ujian.php');
        }
    }
    $stmt = $pdo->prepare('DELETE FROM tugas_ujian WHERE id = ?');
    $stmt->execute([$id]);
    setFlash('sukses', 'Tugas/ujian berhasil dihapus.');
}

redirect('../../pages/tugas_ujian.php');
