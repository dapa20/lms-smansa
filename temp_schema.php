<?php
require 'config/database.php';
$stmt = $pdo->query('SHOW COLUMNS FROM pengumpulan_tugas');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
