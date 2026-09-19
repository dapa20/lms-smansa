<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

try {
    // Check if kepada column exists
    $cols = $pdo->query("SHOW COLUMNS FROM pengumuman LIKE 'kepada'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE pengumuman ADD COLUMN kepada VARCHAR(255) DEFAULT 'Semua' AFTER judul");
        echo "[OK] Kolom 'kepada' berhasil ditambahkan ke tabel pengumuman.\n";
    } else {
        echo "[SKIP] Kolom 'kepada' sudah ada.\n";
    }

    // Make judul nullable or defaulted
    $pdo->exec("ALTER TABLE pengumuman MODIFY COLUMN judul VARCHAR(200) NULL DEFAULT 'Pengumuman'");

    echo "Pengumuman table updated successfully!\n";
} catch (Exception $e) {
    echo "Error updating pengumuman table: " . $e->getMessage() . "\n";
}
