<?php
require_once dirname(__DIR__) . '/config/database.php';

try {
    // 1. Table materi_section
    $pdo->exec("CREATE TABLE IF NOT EXISTS materi_section (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kelas_id INT UNSIGNED NOT NULL,
        mapel_id INT UNSIGNED NOT NULL,
        judul VARCHAR(250) NOT NULL DEFAULT 'New section',
        urutan INT NOT NULL DEFAULT 0,
        dibuat_oleh INT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_section_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
        CONSTRAINT fk_section_mapel FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Table materi_item
    $pdo->exec("CREATE TABLE IF NOT EXISTS materi_item (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        section_id INT UNSIGNED NOT NULL,
        tipe ENUM('file','link','gambar','video','diskusi') NOT NULL DEFAULT 'file',
        judul VARCHAR(250) NOT NULL,
        deskripsi TEXT DEFAULT NULL,
        url_link TEXT DEFAULT NULL,
        nama_file VARCHAR(255) DEFAULT NULL,
        nama_file_asli VARCHAR(255) DEFAULT NULL,
        ukuran_file BIGINT UNSIGNED DEFAULT 0,
        diunggah_oleh INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_item_section FOREIGN KEY (section_id) REFERENCES materi_section(id) ON DELETE CASCADE,
        CONSTRAINT fk_item_user FOREIGN KEY (diunggah_oleh) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Table materi_diskusi_balasan
    $pdo->exec("CREATE TABLE IF NOT EXISTS materi_diskusi_balasan (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        item_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        pesan TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_balasan_item FOREIGN KEY (item_id) REFERENCES materi_item(id) ON DELETE CASCADE,
        CONSTRAINT fk_balasan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    echo "Migration completed successfully!\n";

    // Seed default sections for existing classes/jadwal if empty
    $jadwal = $pdo->query("SELECT DISTINCT kelas_id, mapel_id, guru_id FROM jadwal_mengajar")->fetchAll();
    foreach ($jadwal as $j) {
        $count = $pdo->prepare("SELECT COUNT(*) FROM materi_section WHERE kelas_id = ? AND mapel_id = ?");
        $count->execute([$j['kelas_id'], $j['mapel_id']]);
        if ($count->fetchColumn() == 0) {
            // General section
            $stmt = $pdo->prepare("INSERT INTO materi_section (kelas_id, mapel_id, judul, urutan, dibuat_oleh) VALUES (?, ?, 'General', 1, ?)");
            $stmt->execute([$j['kelas_id'], $j['mapel_id'], $j['guru_id']]);
            $genId = $pdo->lastInsertId();

            // Default General Forum Item
            $itemStmt = $pdo->prepare("INSERT INTO materi_item (section_id, tipe, judul, deskripsi, diunggah_oleh) VALUES (?, 'diskusi', 'Forum Pengumuman & Discussion', 'Ruang diskusi umum dan pengumuman kelas', ?)");
            $itemStmt->execute([$genId, $j['guru_id']]);

            // Section 1
            $stmt2 = $pdo->prepare("INSERT INTO materi_section (kelas_id, mapel_id, judul, urutan, dibuat_oleh) VALUES (?, ?, 'Pertemuan 1: Pengenalan & Pengantar', 2, ?)");
            $stmt2->execute([$j['kelas_id'], $j['mapel_id'], $j['guru_id']]);
            $sec1Id = $pdo->lastInsertId();

            // Section 1 items
            $itemStmt->execute([$sec1Id, $j['guru_id']]); // Example file/item

            // Section 2
            $stmt3 = $pdo->prepare("INSERT INTO materi_section (kelas_id, mapel_id, judul, urutan, dibuat_oleh) VALUES (?, ?, 'Pertemuan 2: Materi Pendalaman & Diskusi', 3, ?)");
            $stmt3->execute([$j['kelas_id'], $j['mapel_id'], $j['guru_id']]);
        }
    }
    echo "Seed data completed!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
