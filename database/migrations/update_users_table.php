<?php
require_once dirname(__DIR__) . '/config/database.php';

try {
    // Check if agama column exists
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'agama'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN agama VARCHAR(50) DEFAULT 'Islam'");
    }

    // Check if no_telp column exists
    $colsTelp = $pdo->query("SHOW COLUMNS FROM users LIKE 'no_telp'")->fetchAll();
    if (empty($colsTelp)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN no_telp VARCHAR(30) DEFAULT '0812-3456-7890'");
    }

    // Check if google_id column exists
    $colsGoogle = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'")->fetchAll();
    if (empty($colsGoogle)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER nip");
    }

    // Make password nullable for google users
    $pdo->exec("ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL");

    echo "User table columns updated successfully!\n";
} catch (Exception $e) {
    echo "Error updating users table: " . $e->getMessage() . "\n";
}

