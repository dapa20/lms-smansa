<?php
header('Content-Type: text/plain; charset=utf-8');
try {
    $pdo = new PDO('mysql:host=localhost;dbname=lms_smansa;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "=== DAFTAR TABEL ===\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo implode("\n", $tables) . "\n\n";

    foreach ($tables as $table) {
        echo "=== STRUKTUR TABEL: $table ===\n";
        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo "  - {$c['Field']} | {$c['Type']} | Null={$c['Null']} | Key={$c['Key']} | Default={$c['Default']}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}

