<?php
/**
 * Migrasi tabel kinerja_harian_kegiatan
 */
require_once __DIR__ . '/../../config/database.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS kinerja_harian_kegiatan (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_id INT UNSIGNED NOT NULL,
    tanggal DATE NOT NULL,
    kegiatan TEXT NOT NULL,
    volume VARCHAR(50) NOT NULL DEFAULT '1 Kegiatan',
    keterangan VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_kinerja_guru FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "[OK] Tabel `kinerja_harian_kegiatan` siap.\n";
