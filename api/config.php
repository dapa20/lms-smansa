<?php
/**
 * =====================================================================
 * KONFIGURASI API SISWA (MOBILE)
 * =====================================================================
 * File ini wajib di-include di semua endpoint API siswa.
 * Menyediakan:
 *   - Header CORS (agar aplikasi Flutter bisa mengakses)
 *   - Load koneksi database
 *   - Helper JSON response (json_out, json_error)
 *   - Fungsi autentikasi token siswa (requireAuth)
 *   - Konstanta base URL file (untuk unduh materi)
 * =====================================================================
 */

// Header CORS — izinkan akses dari aplikasi mobile
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Tangani preflight request CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';

/** Keluarkan response JSON lalu stop. */
function json_out($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Keluarkan response error JSON lalu stop. */
function json_error(string $pesan, int $status = 400, array $extra = []): void
{
    json_out(array_merge(['success' => false, 'message' => $pesan], $extra), $status);
}

/** Ambil body request JSON (untuk POST/PUT). */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

/** Ambil token dari header Authorization. */
function getBearerToken(): ?string
{
    // 1) Cara standar PHP
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // 2) XAMPP/Apache sering menyimpan di REDIRECT_HTTP_AUTHORIZATION
    if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // 3) Ambil dari apache_request_headers() / getallheaders()
    if ($header === '') {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        foreach (['Authorization', 'authorization', 'AUTHORIZATION'] as $k) {
            if (isset($headers[$k]) && $headers[$k] !== '') {
                $header = $headers[$k];
                break;
            }
        }
    }

    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return trim($m[1]);
    }

    // Fallback: ambil dari query ?token=
    return $_GET['token'] ?? null;
}

/** Cari siswa berdasarkan token (valid & belum kedaluwarsa). */
function getSiswaByToken(?string $token): ?array
{
    global $pdo;
    if (!$token) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat, k.program, k.tahun_ajaran
                           FROM siswa s
                           JOIN siswa_tokens st ON st.siswa_id = s.id
                           LEFT JOIN kelas k ON k.id = s.kelas_id
                           WHERE st.token = ? AND st.expires_at > NOW() AND s.status = 'aktif'
                           LIMIT 1");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/** Paksa request harus punya token siswa valid. Return data siswa. */
function requireAuth(): array
{
    $token = getBearerToken();
    $siswa = getSiswaByToken($token);
    if (!$siswa) {
        json_error('Token tidak valid atau sudah kedaluwarsa. Silakan login ulang.', 401, ['code' => 'AUTH_REQUIRED']);
    }
    return $siswa;
}

/** URL dasar file materi (untuk keperluan unduh). */
function materiUrl(string $namaFile): string
{
    return APP_URL . '/uploads/materi/' . rawurlencode($namaFile);
}

/** Format tanggal SQL (datetime) ke ISO 8601 untuk JSON. */
function isoDate(?string $tanggal): ?string
{
    if (empty($tanggal) || $tanggal === '0000-00-00 00:00:00') {
        return null;
    }
    return date('c', strtotime($tanggal));
}

