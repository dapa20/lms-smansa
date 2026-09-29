<?php
/**
 * =====================================================================
 * API SCAN QR CODE PRESENSI SISWA (MOBILE & WEB)
 * =====================================================================
 * POST /api/absen_qr_scan.php
 * Header: Authorization: Bearer <token_siswa>
 * Body JSON: { "kode_qr": "ABS-..." }
 * =====================================================================
 */

require_once __DIR__ . '/config.php';

// Pastikan method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metode request tidak diizinkan. Gunakan POST.', 405);
}

// 1. Autentikasi Siswa
$siswa = requireAuth();

// 2. Ambil parameter body
$body = json_body();
$rawKode = trim($body['kode_qr'] ?? $body['qr_code'] ?? $body['token'] ?? '');

if ($rawKode === '') {
    json_error('Kode QR tidak boleh kosong.', 400);
}

// Jika payload berupa format JSON string yang di-encode dalam QR, parse jika perlu
if (str_starts_with($rawKode, '{') && str_ends_with($rawKode, '}')) {
    $parsed = json_decode($rawKode, true);
    if (is_array($parsed) && !empty($parsed['kode_qr'])) {
        $rawKode = trim($parsed['kode_qr']);
    }
}

// Jika payload berupa URL, ambil query parameter kode / token jika ada
if (filter_var($rawKode, FILTER_VALIDATE_URL)) {
    $parts = parse_url($rawKode);
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $qParams);
        if (!empty($qParams['token'])) {
            $rawKode = trim($qParams['token']);
        } elseif (!empty($qParams['kode_qr'])) {
            $rawKode = trim($qParams['kode_qr']);
        }
    }
}

// 3. Cari sesi di database
$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, u.nama_lengkap AS nama_guru, m.nama_mapel
                       FROM absen_qr_sessions s
                       JOIN kelas k ON k.id = s.kelas_id
                       JOIN users u ON u.id = s.guru_id
                       LEFT JOIN mata_pelajaran m ON m.id = s.mapel_id
                       WHERE s.kode_qr = ?
                       LIMIT 1");
$stmt->execute([$rawKode]);
$session = $stmt->fetch();

if (!$session) {
    json_error('Sesi presensi QR tidak ditemukan atau kode QR tidak valid.', 404);
}

// 4. Cek apakah sesi aktif
if ((int)$session['is_active'] !== 1) {
    json_error('Sesi presensi QR ini sudah ditutup oleh guru.', 400, [
        'code' => 'SESSION_CLOSED'
    ]);
}

// 5. Cek masa berlaku
$sekarangTs = time();
$expiredTs  = strtotime($session['berlaku_sampai']);
if ($sekarangTs > $expiredTs) {
    json_error('Masa berlaku QR Code telah berakhir pada ' . date('H:i', $expiredTs) . ' WIB.', 400, [
        'code' => 'QR_EXPIRED',
        'berlaku_sampai' => isoDate($session['berlaku_sampai'])
    ]);
}

// 6. Validasi Kelas Siswa
if ((int)$siswa['kelas_id'] !== (int)$session['kelas_id']) {
    json_error("Kode QR ini diperuntukkan untuk kelas {$session['nama_kelas']}. Anda terdaftar di kelas {$siswa['nama_kelas']}.", 403, [
        'code' => 'CLASS_MISMATCH',
        'kelas_sesi' => $session['nama_kelas'],
        'kelas_siswa' => $siswa['nama_kelas']
    ]);
}

// 7. Cek apakah sudah pernah absen hari ini
$stmtCek = $pdo->prepare("SELECT * FROM kehadiran WHERE siswa_id = ? AND tanggal = ?");
$stmtCek->execute([$siswa['id'], $session['tanggal']]);
$existing = $stmtCek->fetch();

$sudahHadirSebelumnya = ($existing && $existing['status'] === 'hadir');
$waktuAbsen = date('Y-m-d H:i:s');

// 8. Simpan kehadiran ke database
$stmtSave = $pdo->prepare("INSERT INTO kehadiran (siswa_id, kelas_id, tanggal, status, dicatat_oleh, metode_absen, waktu_absen, qr_session_id)
                           VALUES (?, ?, ?, 'hadir', ?, 'qr', ?, ?)
                           ON DUPLICATE KEY UPDATE 
                               status = 'hadir',
                               dicatat_oleh = VALUES(dicatat_oleh),
                               metode_absen = 'qr',
                               waktu_absen = VALUES(waktu_absen),
                               qr_session_id = VALUES(qr_session_id)");
$stmtSave->execute([
    $siswa['id'],
    $session['kelas_id'],
    $session['tanggal'],
    $session['guru_id'],
    $waktuAbsen,
    $session['id']
]);

// 9. Simpan log scan
$ipAddress  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
$deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Aplikasi Siswa';
if ($ipAddress && str_contains($ipAddress, ',')) {
    $ipAddress = trim(explode(',', $ipAddress)[0]);
}

$stmtLog = $pdo->prepare("INSERT INTO absen_qr_logs (session_id, siswa_id, scanned_at, ip_address, device_info)
                          VALUES (?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                              scanned_at = VALUES(scanned_at),
                              ip_address = VALUES(ip_address),
                              device_info = VALUES(device_info)");
$stmtLog->execute([
    $session['id'],
    $siswa['id'],
    $waktuAbsen,
    substr((string)$ipAddress, 0, 45),
    substr((string)$deviceInfo, 0, 250)
]);

// 10. Respon Sukses
json_out([
    'success' => true,
    'message' => $sudahHadirSebelumnya 
        ? 'Anda sudah tercatat hadir pada hari ini. Status kehadiran telah diperbarui.'
        : 'Presensi berhasil dicatat! Status: Hadir.',
    'data' => [
        'status_kehadiran' => 'hadir',
        'waktu_absen'      => isoDate($waktuAbsen),
        'siswa' => [
            'id'           => (int)$siswa['id'],
            'nama_lengkap' => $siswa['nama_lengkap'],
            'nisn'         => $siswa['nisn'],
            'kelas'        => $session['nama_kelas'],
        ],
        'sesi' => [
            'id'           => (int)$session['id'],
            'judul'        => $session['judul'],
            'mapel'        => $session['nama_mapel'] ?? 'Wali Kelas / Pelajaran Umum',
            'guru'         => $session['nama_guru'],
            'tanggal'      => $session['tanggal'],
            'berlaku_sampai' => isoDate($session['berlaku_sampai']),
        ]
    ]
]);
