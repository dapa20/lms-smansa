<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pengaturan.php');
}

$notifEmail = !empty($_POST['notif_email']) ? 1 : 0;
$notifPush  = !empty($_POST['notif_push'])  ? 1 : 0;
$notifSms   = !empty($_POST['notif_sms'])   ? 1 : 0;

$stmt = $pdo->prepare('UPDATE users SET notif_email=?, notif_push=?, notif_sms=? WHERE id=?');
$stmt->execute([$notifEmail, $notifPush, $notifSms, $_SESSION['user_id']]);

setFlash('sukses', 'Preferensi notifikasi berhasil disimpan.');
redirect('../pengaturan.php');
