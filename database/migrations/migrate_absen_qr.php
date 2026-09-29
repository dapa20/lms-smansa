<?php
/**
 * =====================================================================
 * MIGRASI DATABASE UNTUK FITUR ABSEN QR CODE
 * =====================================================================
 * Menambahkan:
 *   1. Tabel `absen_qr_sessions` (pengelolaan sesi absensi QR oleh guru)
 *   2. Tabel `absen_qr_logs` (catatan waktu & perangkat siswa saat scan)
 *   3. Kolom `metode_absen`, `waktu_absen`, `qr_session_id` pada tabel `kehadiran`
 * =====================================================================
 */

if (PHP_SAPI === 'cli') {
    // Mode CLI: tanpa header
} else {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../../config/database.php';

echo "=== MULAI MIGRASI ABSEN QR CODE ===\n\n";

// ---------------------------------------------------------------------
// 1. Tabel `absen_qr_sessions`
// ---------------------------------------------------------------------
$pdo->exec("CREATE TABLE IF NOT EXISTS absen_qr_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    mapel_id INT UNSIGNED DEFAULT NULL,
    kode_qr VARCHAR(100) NOT NULL UNIQUE,
    judul VARCHAR(150) NOT NULL DEFAULT 'Presensi QR',
    tanggal DATE NOT NULL,
    jam_mulai TIME NOT NULL,
    durasi_menit INT NOT NULL DEFAULT 30,
    berlaku_sampai DATETIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_qr_session_guru FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_qr_session_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_qr_session_mapel FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "[OK] Tabel `absen_qr_sessions` siap.\n";

// ---------------------------------------------------------------------
// 2. Tabel `absen_qr_logs`
// ---------------------------------------------------------------------
$pdo->exec("CREATE TABLE IF NOT EXISTS absen_qr_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    siswa_id INT UNSIGNED NOT NULL,
    scanned_at DATETIME NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    device_info VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_qr_log_session FOREIGN KEY (session_id) REFERENCES absen_qr_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_qr_log_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_session_siswa (session_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "[OK] Tabel `absen_qr_logs` siap.\n";

// ---------------------------------------------------------------------
// 3. Tambah kolom pelengkap ke tabel `kehadiran`
// ---------------------------------------------------------------------
$checkMetode = $pdo->query("SHOW COLUMNS FROM kehadiran LIKE 'metode_absen'")->fetchAll();
if (empty($checkMetode)) {
    $pdo->exec("ALTER TABLE kehadiran ADD COLUMN metode_absen ENUM('manual','qr') NOT NULL DEFAULT 'manual' AFTER status");
    echo "[OK] Kolom `metode_absen` ditambahkan ke tabel `kehadiran`.\n";
} else {
    echo "[SKIP] Kolom `metode_absen` sudah ada.\n";
}

$checkWaktu = $pdo->query("SHOW COLUMNS FROM kehadiran LIKE 'waktu_absen'")->fetchAll();
if (empty($checkWaktu)) {
    $pdo->exec("ALTER TABLE kehadiran ADD COLUMN waktu_absen DATETIME DEFAULT NULL AFTER metode_absen");
    echo "[OK] Kolom `waktu_absen` ditambahkan ke tabel `kehadiran`.\n";
} else {
    echo "[SKIP] Kolom `waktu_absen` sudah ada.\n";
}

$checkQrSession = $pdo->query("SHOW COLUMNS FROM kehadiran LIKE 'qr_session_id'")->fetchAll();
if (empty($checkQrSession)) {
    $pdo->exec("ALTER TABLE kehadiran ADD COLUMN qr_session_id INT UNSIGNED DEFAULT NULL AFTER waktu_absen");
    echo "[OK] Kolom `qr_session_id` ditambahkan ke tabel `kehadiran`.\n";
} else {
    echo "[SKIP] Kolom `qr_session_id` sudah ada.\n";
}

echo "\n=== MIGRASI SELESAI DENGAN SUKSES ===\n";
