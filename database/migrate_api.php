<?php
/**
 * =====================================================================
 * MIGRASI DATABASE UNTUK API SISWA (MOBILE)
 * =====================================================================
 * Yang dilakukan:
 *   1. Menambah kolom `password` pada tabel `siswa`
 *   2. Mengisi password default `siswa123` (hash bcrypt) untuk semua siswa
 *   3. Membuat tabel `siswa_tokens` untuk autentikasi token login siswa
 *
 * Cara menjalankan: akses sekali lewat browser
 *   http://localhost/lms-smansa/database/migrate_api.php
 * atau lewat terminal:
 *   php C:\xampp\htdocs\lms-smansa\database\migrate_api.php
 * =====================================================================
 */

if (PHP_SAPI === 'cli') {
    // Mode CLI: tanpa header
} else {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../config/database.php';

echo "MULAI MIGRASI API SISWA\n\n";

// ---------------------------------------------------------------------
// 1. Cek & tambah kolom password pada tabel siswa
// ---------------------------------------------------------------------
$cols = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'password'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE siswa ADD COLUMN password VARCHAR(255) DEFAULT NULL AFTER nisn");
    echo "[OK] Kolom `password` ditambahkan ke tabel siswa.\n";
} else {
    echo "[SKIP] Kolom `password` sudah ada.\n";
}

// ---------------------------------------------------------------------
// 2. Isi password default untuk siswa yang belum punya password
// ---------------------------------------------------------------------
$hash = password_hash('siswa123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE siswa SET password = ? WHERE password IS NULL OR password = ''");
$stmt->execute([$hash]);
echo "[OK] Password default 'siswa123' di-set untuk {$stmt->rowCount()} siswa.\n";

// ---------------------------------------------------------------------
// 3. Buat tabel siswa_tokens
// ---------------------------------------------------------------------
$pdo->exec("CREATE TABLE IF NOT EXISTS siswa_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    CONSTRAINT fk_siswa_tokens_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "[OK] Tabel `siswa_tokens` dibuat.\n";

echo "\nMIGRASI SELESAI.\n";

