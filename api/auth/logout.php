<?php
/**
 * API Logout Siswa
 * POST /api/auth/logout.php
 * Header Authorization: Bearer <token>
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metode tidak diizinkan. Gunakan POST.', 405);
}

$token = getBearerToken();
if ($token) {
    $stmt = $pdo->prepare('DELETE FROM siswa_tokens WHERE token = ?');
    $stmt->execute([$token]);
}

json_out(['success' => true, 'message' => 'Logout berhasil.']);

