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

$ip_address = $_SERVER['REMOTE_ADDR'];
$max_attempts = 5;
$lockout_time = 15; // in minutes

// Cek rate limit
$stmtLimit = $pdo->prepare('SELECT attempts, last_attempt FROM login_rate_limits WHERE ip_address = ?');
$stmtLimit->execute([$ip_address]);
$rateLimit = $stmtLimit->fetch();

if ($rateLimit && (int)$rateLimit['attempts'] >= $max_attempts) {
    $lastAttempt = new DateTime($rateLimit['last_attempt']);
    $now = new DateTime();
    $diff = $now->diff($lastAttempt);
    $minutesPassed = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    
    if ($minutesPassed < $lockout_time) {
        $wait_time = $lockout_time - $minutesPassed;
        redirect("../../login.php?error=5&wait=$wait_time");
    } else {
        // Reset attempts after lockout time
        $pdo->prepare('DELETE FROM login_rate_limits WHERE ip_address = ?')->execute([$ip_address]);
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    // Increment failed attempts
    $pdo->prepare('
        INSERT INTO login_rate_limits (ip_address, attempts, last_attempt) 
        VALUES (?, 1, NOW()) 
        ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()
    ')->execute([$ip_address]);
    
    redirect('../../login.php?error=1&email=' . urlencode($email));
}

// Clear failed attempts on successful login
$pdo->prepare('DELETE FROM login_rate_limits WHERE ip_address = ?')->execute([$ip_address]);

if ($user['status'] !== 'aktif') {
    redirect('../../login.php?error=2');
}

// Login berhasil: regenerasi session ID untuk mencegah session fixation.
session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['nama_lengkap'];

require_once __DIR__ . '/../../includes/functions.php';
logAktivitas('LOGIN', 'User berhasil login menggunakan alamat email.');

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
