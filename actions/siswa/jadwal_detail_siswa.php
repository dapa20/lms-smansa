<?php
/**
 * AJAX: Ambil daftar siswa beserta rekap kehadiran untuk jadwal tertentu.
 * Dipanggil via fetch() dari kelas_jadwal.php saat jadwal diklik.
 *
 * GET params:
 *   jadwal_id  - ID row jadwal_mengajar
 *   tanggal    - Tanggal absensi yang sedang dilihat (format Y-m-d), default hari ini
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$jadwalId = (int)($_GET['jadwal_id'] ?? 0);
$tanggal  = $_GET['tanggal'] ?? date('Y-m-d');

// Validasi format tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

if ($jadwalId === 0) {
    echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan.']);
    exit;
}

// Ambil info jadwal beserta mapel & kelas
$stmtJ = $pdo->prepare("SELECT j.*, m.nama_mapel, k.nama_kelas, k.id AS kelas_id_val,
                                u.nama_lengkap AS nama_guru
                         FROM jadwal_mengajar j
                         JOIN mata_pelajaran m ON m.id = j.mapel_id
                         JOIN kelas k ON k.id = j.kelas_id
                         JOIN users u ON u.id = j.guru_id
                         WHERE j.id = ? LIMIT 1");
$stmtJ->execute([$jadwalId]);
$jadwal = $stmtJ->fetch(PDO::FETCH_ASSOC);

if (!$jadwal) {
    echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan.']);
    exit;
}

// Guru biasa hanya boleh lihat jadwal miliknya sendiri
if (!isAdmin() && (int)$jadwal['guru_id'] !== (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$kelasId = (int)$jadwal['kelas_id_val'];

// Ambil daftar siswa aktif di kelas ini
$stmtS = $pdo->prepare("SELECT s.id, s.nis, s.nisn, s.nama_lengkap, s.jenis_kelamin,
                                k_absen.status AS status_hari_ini
                         FROM siswa s
                         LEFT JOIN kehadiran k_absen ON k_absen.siswa_id = s.id
                             AND k_absen.kelas_id = ? AND k_absen.tanggal = ?
                         WHERE s.kelas_id = ? AND s.status = 'aktif'
                         ORDER BY s.nama_lengkap ASC");
$stmtS->execute([$kelasId, $tanggal, $kelasId]);
$siswaList = $stmtS->fetchAll(PDO::FETCH_ASSOC);

// Rekap kehadiran per siswa (total semester)
$stmtRekap = $pdo->prepare("SELECT siswa_id,
                                    SUM(status = 'hadir') AS total_hadir,
                                    SUM(status = 'izin')  AS total_izin,
                                    SUM(status = 'sakit') AS total_sakit,
                                    SUM(status = 'alpa')  AS total_alpa,
                                    COUNT(*) AS total_pertemuan
                             FROM kehadiran
                             WHERE kelas_id = ?
                             GROUP BY siswa_id");
$stmtRekap->execute([$kelasId]);
$rekapMap = [];
foreach ($stmtRekap->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $rekapMap[(int)$r['siswa_id']] = $r;
}

// Gabungkan rekap ke data siswa
foreach ($siswaList as &$s) {
    $sid = (int)$s['id'];
    $s['total_hadir']     = (int)($rekapMap[$sid]['total_hadir']     ?? 0);
    $s['total_izin']      = (int)($rekapMap[$sid]['total_izin']      ?? 0);
    $s['total_sakit']     = (int)($rekapMap[$sid]['total_sakit']     ?? 0);
    $s['total_alpa']      = (int)($rekapMap[$sid]['total_alpa']      ?? 0);
    $s['total_pertemuan'] = (int)($rekapMap[$sid]['total_pertemuan'] ?? 0);
    // Kalau hari ini belum diabsen, default 'hadir'
    if (!$s['status_hari_ini']) {
        $s['status_hari_ini'] = 'hadir';
    }
}
unset($s);

echo json_encode([
    'success' => true,
    'jadwal'  => $jadwal,
    'siswa'   => $siswaList,
    'tanggal' => $tanggal,
]);
