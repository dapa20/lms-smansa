<?php
/**
 * Endpoint AJAX: Real-time Live Attendees untuk Sesi Absen QR
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$sessionId = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);

if ($sessionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid']);
    exit;
}

$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, u.nama_lengkap AS nama_guru, m.nama_mapel
                       FROM absen_qr_sessions s
                       JOIN kelas k ON k.id = s.kelas_id
                       JOIN users u ON u.id = s.guru_id
                       LEFT JOIN mata_pelajaran m ON m.id = s.mapel_id
                       WHERE s.id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan']);
    exit;
}

$nowTs = time();
$expiryTs = strtotime($session['berlaku_sampai']);
$sisaDetik = max(0, $expiryTs - $nowTs);
$isExpired = ($nowTs > $expiryTs);

// Jika melewati batas berlaku, otomatis set is_active = 0 di database jika belum
if ($isExpired && (int)$session['is_active'] === 1) {
    $pdo->prepare("UPDATE absen_qr_sessions SET is_active = 0 WHERE id = ?")->execute([$sessionId]);
    $session['is_active'] = 0;
}

// 1. Ambil semua siswa di kelas ini
$stmtSiswa = $pdo->prepare("SELECT id, nis, nisn, nama_lengkap, jenis_kelamin, foto
                            FROM siswa
                            WHERE kelas_id = ? AND status = 'aktif'
                            ORDER BY nama_lengkap ASC");
$stmtSiswa->execute([$session['kelas_id']]);
$semuaSiswa = $stmtSiswa->fetchAll();

// 2. Ambil data kehadiran pada tanggal sesi ini
$stmtHadir = $pdo->prepare("SELECT k.siswa_id, k.status, k.metode_absen, k.waktu_absen,
                                   l.scanned_at, l.ip_address, l.device_info
                            FROM kehadiran k
                            LEFT JOIN absen_qr_logs l ON l.session_id = ? AND l.siswa_id = k.siswa_id
                            WHERE k.kelas_id = ? AND k.tanggal = ?");
$stmtHadir->execute([$sessionId, $session['kelas_id'], $session['tanggal']]);
$kehadiranMap = [];
foreach ($stmtHadir->fetchAll() as $h) {
    $kehadiranMap[$h['siswa_id']] = $h;
}

$hadirList = [];
$belumList = [];

foreach ($semuaSiswa as $sw) {
    $swId = $sw['id'];
    $fotoUrl = !empty($sw['foto']) 
        ? APP_URL . '/uploads/avatar/' . rawurlencode($sw['foto'])
        : null;

    if (isset($kehadiranMap[$swId]) && in_array($kehadiranMap[$swId]['status'], ['hadir', 'izin', 'sakit'])) {
        $kh = $kehadiranMap[$swId];
        $hadirList[] = [
            'id'           => $sw['id'],
            'nama_lengkap' => $sw['nama_lengkap'],
            'nisn'         => $sw['nisn'],
            'jenis_kelamin'=> $sw['jenis_kelamin'],
            'foto'         => $fotoUrl,
            'status'       => $kh['status'],
            'metode'       => $kh['metode_absen'] ?? 'manual',
            'waktu_absen'  => $kh['waktu_absen'] ?: $kh['scanned_at'] ?: null,
            'jam_menit'    => ($kh['waktu_absen'] ?: $kh['scanned_at']) 
                                ? date('H:i:s', strtotime($kh['waktu_absen'] ?: $kh['scanned_at']))
                                : '-',
        ];
    } else {
        $belumList[] = [
            'id'           => $sw['id'],
            'nama_lengkap' => $sw['nama_lengkap'],
            'nisn'         => $sw['nisn'],
            'jenis_kelamin'=> $sw['jenis_kelamin'],
            'foto'         => $fotoUrl,
            'status'       => 'belum',
        ];
    }
}

// Urutkan daftar hadir berdasarkan yang paling baru scan
usort($hadirList, function($a, $b) {
    return strcmp($b['waktu_absen'] ?? '', $a['waktu_absen'] ?? '');
});

$totalSiswa = count($semuaSiswa);
$totalHadir = count($hadirList);
$persen = $totalSiswa > 0 ? round(($totalHadir / $totalSiswa) * 100) : 0;

echo json_encode([
    'success' => true,
    'session' => [
        'id'             => (int)$session['id'],
        'kode_qr'        => $session['kode_qr'],
        'judul'          => $session['judul'],
        'kelas_id'       => (int)$session['kelas_id'],
        'nama_kelas'     => $session['nama_kelas'],
        'mapel'          => $session['nama_mapel'] ?? 'Wali Kelas / Pelajaran Umum',
        'is_active'      => (int)$session['is_active'],
        'is_expired'     => $isExpired,
        'sisa_detik'     => $sisaDetik,
        'berlaku_sampai' => $session['berlaku_sampai'],
        'format_berlaku' => date('H:i:s', strtotime($session['berlaku_sampai'])),
    ],
    'stats' => [
        'total'          => $totalSiswa,
        'hadir'          => $totalHadir,
        'belum'          => count($belumList),
        'persen'         => $persen,
    ],
    'hadir_list' => $hadirList,
    'belum_list' => $belumList,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
