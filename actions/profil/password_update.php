<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/pengaturan.php');
}

$userId    = (int)$_SESSION['user_id'];
$lama      = (string)($_POST['password_lama'] ?? '');
$baru      = (string)($_POST['password_baru'] ?? '');
$konfirmasi = (string)($_POST['password_konfirmasi'] ?? '');

$stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
$stmt->execute([$userId]);
$hashLama = $stmt->fetch()['password'] ?? '';

if (!password_verify($lama, $hashLama)) {
    setFlash('gagal', 'Kata sandi saat ini tidak sesuai.');
    redirect('../../pages/pengaturan.php');
}
if (strlen($baru) < 6) {
    setFlash('gagal', 'Kata sandi baru minimal 6 karakter.');
    redirect('../../pages/pengaturan.php');
}
if ($baru !== $konfirmasi) {
    setFlash('gagal', 'Konfirmasi kata sandi baru tidak cocok.');
    redirect('../../pages/pengaturan.php');
}

$stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
$stmt->execute([password_hash($baru, PASSWORD_DEFAULT), $userId]);

setFlash('sukses', 'Kata sandi berhasil diubah.');
redirect('../../pages/pengaturan.php');
