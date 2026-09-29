<?php
require_once __DIR__ . '/../includes/auth.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'guru';

$isAdmin = false;
$user = $pdo->query("SELECT * FROM users WHERE id = 1")->fetch();
$daftarKelas = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$stmt = $pdo->prepare("SELECT DISTINCT k.id, k.nama_kelas FROM kelas k
                        JOIN jadwal_mengajar j ON j.kelas_id = k.id
                        WHERE j.guru_id = ? ORDER BY k.nama_kelas");
$stmt->execute([$user['id']]);
$kelasDiajarGuru = $stmt->fetchAll();
$idKelasDiajarGuru = array_column($kelasDiajarGuru, 'id');

ob_start();
require __DIR__ . '/../includes/partials/tab_data_siswa.php';
$html = ob_get_clean();

echo "Rendered length: " . strlen($html) . " bytes\n";
if (strpos($html, 'Siswa Kelas') !== false) {
    echo "SUCCESS: Title Siswa Kelas rendered!\n";
}
if (strpos($html, 'Cari / Pilih Kelas') !== false) {
    echo "SUCCESS: Hover bar rendered!\n";
}
if (strpos($html, 'Show') !== false && strpos($html, 'Search:') !== false) {
    echo "SUCCESS: Table controls rendered!\n";
}
