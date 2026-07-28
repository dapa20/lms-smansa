<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/google_auth.php';

// Mendukung POST Form biasa (GSI ux_mode=popup/redirect) dan AJAX JSON request
$credential = $_POST['credential'] ?? null;
$isJsonRequest = false;

if (!$credential) {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $credential = $json['credential'] ?? null;
            $isJsonRequest = true;
        }
    }
}

/**
 * Dekode payload JWT ID Token dari Google Identity Services
 */
function parseGoogleIdToken(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    
    $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
    if (!$payloadJson) {
        return null;
    }
    
    $payload = json_decode($payloadJson, true);
    return is_array($payload) ? $payload : null;
}

if (!$credential) {
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Token credential Google tidak ditemukan.']);
        exit;
    }
    redirect('../../login.php?error=4');
}

$payload = parseGoogleIdToken($credential);

if (!$payload || empty($payload['email'])) {
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Token Google tidak valid.']);
        exit;
    }
    redirect('../../login.php?error=4');
}

$email     = trim($payload['email']);
$googleSub = $payload['sub'] ?? null;
$name      = $payload['name'] ?? $email;
$picture   = $payload['picture'] ?? null;

// Cari user berdasarkan google_id atau email di database LMS
$stmt = $pdo->prepare('SELECT * FROM users WHERE (google_id IS NOT NULL AND google_id = ?) OR email = ? LIMIT 1');
$stmt->execute([$googleSub, $email]);
$user = $stmt->fetch();

if (!$user) {
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'not_registered',
            'message' => 'Email akun Google (' . $email . ') belum terdaftar di sistem LMS. Silakan hubungi admin sekolah.'
        ]);
        exit;
    }
    redirect('../../login.php?error=3&email=' . urlencode($email));
}

// Cek status akun
if ($user['status'] !== 'aktif') {
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'inactive',
            'message' => 'Akun Anda sedang tidak aktif. Silakan hubungi admin sekolah.'
        ]);
        exit;
    }
    redirect('../../login.php?error=2');
}

// Tautkan google_id atau update foto jika belum ada
if (empty($user['google_id']) && $googleSub) {
    $updateStmt = $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?');
    $updateStmt->execute([$googleSub, $user['id']]);
}

// Login berhasil: simpan data session
session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['nama_lengkap'];

if ($isJsonRequest) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => 'index.php']);
    exit;
}

redirect('../../index.php');
