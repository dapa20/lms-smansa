<?php
/**
 * Action: Tandai Hadir Manual dari Layar Absen QR
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$user = currentUser();
$isAdmin = isAdmin();

$sessionId = (int)($_POST['session_id'] ?? 0);
$siswaId   = (int)($_POST['siswa_id'] ?? 0);
$status    = $_POST['status'] ?? 'hadir';

if (!in_array($status, ['hadir', 'izin', 'sakit', 'alpa'])) {
    $status = 'hadir';
}

if ($sessionId <= 0 || $siswaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM absen_qr_sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan']);
    exit;
}

if (!$isAdmin && (int)$session['guru_id'] !== (int)$user['id']) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$waktu = date('Y-m-d H:i:s');

$stmtSave = $pdo->prepare("INSERT INTO kehadiran (siswa_id, kelas_id, tanggal, status, dicatat_oleh, metode_absen, waktu_absen, qr_session_id)
                           VALUES (?, ?, ?, ?, ?, 'manual', ?, ?)
                           ON DUPLICATE KEY UPDATE 
                               status = VALUES(status),
                               dicatat_oleh = VALUES(dicatat_oleh),
                               metode_absen = 'manual',
                               waktu_absen = VALUES(waktu_absen),
                               qr_session_id = VALUES(qr_session_id)");
$stmtSave->execute([
    $siswaId,
    $session['kelas_id'],
    $session['tanggal'],
    $status,
    $user['id'],
    $waktu,
    $session['id']
]);

echo json_encode(['success' => true, 'message' => 'Status kehadiran berhasil diperbarui']);
