<?php
/**
 * Action: Buat Sesi Absensi QR Baru
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/absen_qr.php');
}

$user = currentUser();
$isAdmin = isAdmin();

$kelasId     = (int)($_POST['kelas_id'] ?? 0);
$mapelId     = !empty($_POST['mapel_id']) ? (int)$_POST['mapel_id'] : null;
$judul       = trim($_POST['judul'] ?? 'Presensi Pertemuan');
$tanggal     = $_POST['tanggal'] ?? date('Y-m-d');
$durasiMenit = max(5, min(1440, (int)($_POST['durasi_menit'] ?? 30))); // 5 menit s/d 24 jam

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

if ($kelasId <= 0) {
    setFlash('gagal', 'Silakan pilih kelas terlebih dahulu.');
    redirect('../../pages/absen_qr.php');
}

// Validasi hak akses guru: hanya boleh untuk kelas yang diajar
if (!$isAdmin) {
    $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ?");
    $stmtCek->execute([$user['id'], $kelasId]);
    $adaJadwal = (int)$stmtCek->fetchColumn();

    // Atau wali kelas
    $stmtWali = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE id = ? AND wali_kelas_id = ?");
    $stmtWali->execute([$kelasId, $user['id']]);
    $isWali = (int)$stmtWali->fetchColumn();

    if ($adaJadwal === 0 && $isWali === 0) {
        setFlash('gagal', 'Anda tidak memiliki hak akses untuk mengajar di kelas tersebut.');
        redirect('../../pages/absen_qr.php');
    }
}

// Format token QR yang unik, jelas, dan aman
// Contoh: ABS-X1-A9F4E8
$kodeRandom = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
$stmtKelas = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
$stmtKelas->execute([$kelasId]);
$namaKelas = $stmtKelas->fetchColumn() ?: 'KLS';
$slugKelas = preg_replace('/[^A-Z0-9]/', '', strtoupper($namaKelas));
$kodeQr = 'ABS-' . $slugKelas . '-' . $kodeRandom;

$jamMulai = date('H:i:s');
$berlakuSampai = date('Y-m-d H:i:s', time() + ($durasiMenit * 60));

// Nonaktifkan sesi aktif sebelumnya untuk guru & kelas ini di tanggal yang sama agar tidak bentrok
$stmtDeact = $pdo->prepare("UPDATE absen_qr_sessions SET is_active = 0 WHERE guru_id = ? AND kelas_id = ? AND tanggal = ? AND is_active = 1");
$stmtDeact->execute([$user['id'], $kelasId, $tanggal]);

// Insert sesi baru
$stmtInsert = $pdo->prepare("INSERT INTO absen_qr_sessions 
    (guru_id, kelas_id, mapel_id, kode_qr, judul, tanggal, jam_mulai, durasi_menit, berlaku_sampai, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

$stmtInsert->execute([
    $user['id'],
    $kelasId,
    $mapelId,
    $kodeQr,
    $judul !== '' ? $judul : 'Presensi Pertemuan',
    $tanggal,
    $jamMulai,
    $durasiMenit,
    $berlakuSampai
]);

$sessionId = $pdo->lastInsertId();

setFlash('sukses', "Sesi Absensi QR untuk kelas $namaKelas berhasil dibuat! Kode berlaku selama $durasiMenit menit.");
redirect('../../pages/absen_qr.php?session_id=' . $sessionId);
