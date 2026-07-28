<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../kelas_jadwal.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM jadwal_mengajar WHERE id = ?');
    $stmt->execute([$id]);
    setFlash('sukses', 'Jadwal berhasil dihapus.');
}

redirect('../kelas_jadwal.php');
