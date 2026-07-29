<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/data_siswa.php');
}

$kelasId   = (int)($_POST['kelas_id'] ?? 0);
$tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
$siswaIds  = $_POST['siswa_id'] ?? [];
$statusArr = $_POST['status'] ?? [];
$jadwalId  = (int)($_POST['jadwal_id'] ?? 0);
$redirect  = $_POST['redirect_to'] ?? 'data_siswa';

// Validasi format tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

// Guru hanya boleh mencatat kehadiran untuk kelas yang benar-benar ia ajar.
if (!isAdmin()) {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ?');
    $stmt->execute([$_SESSION['user_id'], $kelasId]);
    if ((int)$stmt->fetch()['c'] === 0) {
        setFlash('gagal', 'Anda tidak mengajar di kelas tersebut.');
        redirect('../../pages/data_siswa.php');
    }
}

if ($kelasId === 0 || !is_array($siswaIds)) {
    redirect('../../pages/data_siswa.php');
}

$stmt = $pdo->prepare("INSERT INTO kehadiran (siswa_id, kelas_id, tanggal, status, dicatat_oleh)
                        VALUES (?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE status = VALUES(status), dicatat_oleh = VALUES(dicatat_oleh)");

$jumlahDisimpan = 0;
foreach ($siswaIds as $i => $siswaId) {
    $status = $statusArr[$i] ?? 'hadir';
    if (!in_array($status, ['hadir', 'izin', 'sakit', 'alpa'])) {
        $status = 'hadir';
    }
    $stmt->execute([(int)$siswaId, $kelasId, $tanggal, $status, $_SESSION['user_id']]);
    $jumlahDisimpan++;
}

setFlash('sukses', "Kehadiran berhasil dicatat untuk $jumlahDisimpan siswa pada tanggal " . date('d/m/Y', strtotime($tanggal)) . ".");

if ($redirect === 'kelas_jadwal') {
    $back = '../../kelas_jadwal.php';
    if ($jadwalId > 0) {
        $back .= '?detail_jadwal=' . $jadwalId . '&tanggal=' . urlencode($tanggal);
    }
    redirect($back);
} else {
    redirect('../../pages/data_siswa.php');
}
