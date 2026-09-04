<?php
/**
 * API Profil Siswa
 * GET /api/profil.php
 * Header Authorization: Bearer <token>
 * Response: data lengkap profil siswa (identitas, kelas, wali kelas, kehadiran)
 */
require_once __DIR__ . '/config.php';

$siswa = requireAuth();

// Info wali kelas
$stmt = $pdo->prepare("SELECT u.nama_lengkap AS wali_kelas, u.nip AS nip_wali
                       FROM kelas k
                       LEFT JOIN users u ON u.id = k.wali_kelas_id
                       WHERE k.id = ?");
$stmt->execute([$siswa['kelas_id']]);
$kelasInfo = $stmt->fetch();

// Ringkasan kehadiran
$stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'hadir') AS hadir,
                              SUM(status = 'izin') AS izin, SUM(status = 'sakit') AS sakit,
                              SUM(status = 'alpa') AS alpa
                       FROM kehadiran WHERE siswa_id = ?");
$stmt->execute([$siswa['id']]);
$absen = $stmt->fetch();

$totalAbsen = (int)($absen['total'] ?? 0);
$hadir = (int)($absen['hadir'] ?? 0);
$persen = $totalAbsen > 0 ? round(($hadir / $totalAbsen) * 100, 1) : 0.0;

json_out([
    'success' => true,
    'data' => [
        'siswa' => [
            'id' => (int)$siswa['id'],
            'nis' => $siswa['nis'],
            'nisn' => $siswa['nisn'],
            'nama_lengkap' => $siswa['nama_lengkap'],
            'jenis_kelamin' => $siswa['jenis_kelamin'],
            'foto' => !empty($siswa['foto']) ? APP_URL . '/uploads/avatar/' . $siswa['foto'] : null,
            'status' => $siswa['status'],
            'kelas_id' => $siswa['kelas_id'] ? (int)$siswa['kelas_id'] : null,
            'nama_kelas' => $siswa['nama_kelas'] ?? '-',
            'tingkat' => $siswa['tingkat'] ?? '-',
            'program' => $siswa['program'] ?? '-',
            'tahun_ajaran' => $siswa['tahun_ajaran'] ?? '-',
            'wali_kelas' => $kelasInfo['wali_kelas'] ?? null,
        ],
        'kehadiran' => [
            'total' => $totalAbsen,
            'hadir' => $hadir,
            'izin' => (int)($absen['izin'] ?? 0),
            'sakit' => (int)($absen['sakit'] ?? 0),
            'alpa' => (int)($absen['alpa'] ?? 0),
            'persen_hadir' => $persen,
        ],
    ],
]);

