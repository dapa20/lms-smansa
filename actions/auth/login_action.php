<?php
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../login.php');
}

$email    = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');
$remember = !empty($_POST['remember_me']);

if ($email === '' || $password === '') {
    redirect('../../login.php?error=1');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    redirect('../../login.php?error=1&email=' . urlencode($email));
}

if ($user['status'] !== 'aktif') {
    redirect('../../login.php?error=2');
}

// Login berhasil: regenerasi session ID untuk mencegah session fixation.
session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['nama_lengkap'];

// "Ingat Saya": perpanjang umur cookie session menjadi 30 hari.
if ($remember) {
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires'  => time() + (30 * 24 * 60 * 60),
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

redirect('../../index.php');
