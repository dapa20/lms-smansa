<?php
/**
 * =====================================================================
 * KONEKSI DATABASE (PDO)
 * =====================================================================
 * Sesuaikan 4 baris konstanta di bawah ini dengan pengaturan MySQL
 * di komputer/hosting Anda. Nilai default cocok untuk XAMPP/Laragon.
 * =====================================================================
 */

define('DB_HOST', 'localhost');   // host database, biasanya 'localhost'
define('DB_NAME', 'lms_smansa');  // nama database yang sudah Anda buat
define('DB_USER', 'root');        // username MySQL
define('DB_PASS', '');            // password MySQL (kosongkan jika tidak ada)

// URL dasar aplikasi (tanpa trailing slash), dideteksi otomatis.
// Contoh: http://localhost/lms-smansa
$_scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_appRoot  = rtrim(dirname(dirname(__FILE__)), DIRECTORY_SEPARATOR);
$_docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', DIRECTORY_SEPARATOR);
$_basePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($_appRoot, strlen($_docRoot)));
define('APP_URL', $_scheme . '://' . $_host . $_basePath); // mis. http://localhost/lms-smansa
unset($_scheme, $_host, $_appRoot, $_docRoot, $_basePath);

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lempar exception saat query gagal
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // hasil query berupa array asosiatif
        PDO::ATTR_EMULATE_PREPARES   => false,                    // pakai prepared statement asli (lebih aman)
    ]);
} catch (PDOException $e) {
    // Jangan tampilkan detail koneksi ke publik, cukup pesan umum.
    die('Koneksi ke database gagal. Periksa pengaturan di config/database.php. Detail: ' . $e->getMessage());
}
