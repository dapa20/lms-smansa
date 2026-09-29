<?php
/**
 * Action: Kelola Sesi Absensi QR (Tutup, Perpanjang, Perbarui Kode, Hapus)
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$user = currentUser();
$isAdmin = isAdmin();

$action    = $_POST['action'] ?? $_GET['action'] ?? '';
$sessionId = (int)($_POST['session_id'] ?? $_GET['session_id'] ?? 0);
$isAjax    = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
             || isset($_GET['ajax']) || isset($_POST['ajax']);

if ($sessionId <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID Sesi tidak valid']);
        exit;
    }
    redirect('../../pages/absen_qr.php');
}

// Cek kepemilikan sesi
$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM absen_qr_sessions s JOIN kelas k ON k.id = s.kelas_id WHERE s.id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan']);
        exit;
    }
    setFlash('gagal', 'Sesi tidak ditemukan.');
    redirect('../../pages/absen_qr.php');
}

if (!$isAdmin && (int)$session['guru_id'] !== (int)$user['id']) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }
    setFlash('gagal', 'Anda tidak memiliki hak untuk mengelola sesi ini.');
    redirect('../../pages/absen_qr.php');
}

$pesan = '';

if ($action === 'close') {
    // Tutup sesi absensi
    $stmtUp = $pdo->prepare("UPDATE absen_qr_sessions SET is_active = 0 WHERE id = ?");
    $stmtUp->execute([$sessionId]);
    $pesan = "Sesi absensi untuk kelas {$session['nama_kelas']} berhasil ditutup.";
} elseif ($action === 'reopen') {
    // Buka kembali sesi dengan durasi default 30 menit dari sekarang
    $tambahMenit = max(5, (int)($_POST['tambah_menit'] ?? 30));
    $newExpiry = date('Y-m-d H:i:s', time() + ($tambahMenit * 60));
    $stmtUp = $pdo->prepare("UPDATE absen_qr_sessions SET is_active = 1, berlaku_sampai = ? WHERE id = ?");
    $stmtUp->execute([$newExpiry, $sessionId]);
    $pesan = "Sesi absensi berhasil diaktifkan kembali hingga " . date('H:i', strtotime($newExpiry)) . " WIB.";
} elseif ($action === 'regenerate') {
    // Perbarui kode QR untuk mencegah kecurangan
    $kodeRandom = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $slugKelas = preg_replace('/[^A-Z0-9]/', '', strtoupper($session['nama_kelas']));
    $newKode = 'ABS-' . $slugKelas . '-' . $kodeRandom;
    
    // Juga pastikan sesi aktif dan jika sudah kedaluwarsa, tambahkan 15 menit
    $newExpiry = $session['berlaku_sampai'];
    if (strtotime($newExpiry) <= time()) {
        $newExpiry = date('Y-m-d H:i:s', time() + (15 * 60));
    }

    $stmtUp = $pdo->prepare("UPDATE absen_qr_sessions SET kode_qr = ?, is_active = 1, berlaku_sampai = ? WHERE id = ?");
    $stmtUp->execute([$newKode, $newExpiry, $sessionId]);
    $pesan = "Kode QR berhasil diperbarui dengan token baru.";
} elseif ($action === 'delete') {
    // Hapus sesi
    $stmtDel = $pdo->prepare("DELETE FROM absen_qr_sessions WHERE id = ?");
    $stmtDel->execute([$sessionId]);
    $pesan = "Sesi absensi berhasil dihapus.";
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $pesan]);
        exit;
    }
    setFlash('sukses', $pesan);
    redirect('../../pages/absen_qr.php');
}

if ($isAjax) {
    header('Content-Type: application/json');
    // Ambil data terbaru sesi
    $stmt = $pdo->prepare("SELECT * FROM absen_qr_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $updated = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => $pesan,
        'data' => $updated
    ]);
    exit;
}

setFlash('sukses', $pesan);
redirect('../../pages/absen_qr.php?session_id=' . $sessionId);
