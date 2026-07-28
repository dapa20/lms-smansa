<?php
/**
 * =====================================================================
 * AUTH HELPER
 * =====================================================================
 * Kumpulan fungsi untuk mengelola session login, cek hak akses (role),
 * dan mengambil data user yang sedang login.
 * File ini WAJIB di-include paling atas di setiap halaman yang butuh
 * proteksi login (dashboard, data siswa, materi, dst).
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/** Apakah user sedang login? */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Paksa user login. Jika belum, lempar ke halaman login. */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/** Paksa role tertentu (mis. hanya admin). Guru & admin lain akan ditolak. */
function requireRole(string $role): void
{
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== $role) {
        http_response_code(403);
        die('Anda tidak memiliki akses ke halaman ini.');
    }
}

/** Ambil data lengkap user yang sedang login dari database. */
function currentUser(): ?array
{
    global $pdo;
    if (!isLoggedIn()) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $cached = $stmt->fetch() ?: null;
    return $cached;
}

/** True jika user yang login adalah admin. */
function isAdmin(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}
