<?php
/**
 * API Login Siswa
 * POST /api/auth/login.php
 * Body (JSON atau form): { "nis": "1920104", "password": "siswa123" }
 * Response sukses:
 *   { "success": true, "token": "...", "siswa": { ... } }
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metode tidak diizinkan. Gunakan POST.', 405);
}

$data    = json_body();
$nis     = trim($data['nis'] ?? '');
$password = (string)($data['password'] ?? '');

if ($nis === '' || $password === '') {
    json_error('NIS dan password wajib diisi.');
}

$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat, k.program, k.tahun_ajaran
                       FROM siswa s
                       LEFT JOIN kelas k ON k.id = s.kelas_id
                       WHERE s.nis = ? LIMIT 1");
$stmt->execute([$nis]);
$siswa = $stmt->fetch();

if (!$siswa || empty($siswa['password']) || !password_verify($password, $siswa['password'])) {
    json_error('NIS atau password salah.', 401, ['code' => 'INVALID_CREDENTIALS']);
}

if ($siswa['status'] !== 'aktif') {
    json_error('Akun siswa tidak aktif. Hubungi admin sekolah.', 403, ['code' => 'ACCOUNT_INACTIVE']);
}

// Generate token unik (64 karakter hex)
$token = bin2hex(random_bytes(32));

// Hapus token lama milik siswa ini (agar tidak menumpuk), lalu simpan yang baru
$del = $pdo->prepare('DELETE FROM siswa_tokens WHERE siswa_id = ?');
$del->execute([$siswa['id']]);

$expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 hari
$ins = $pdo->prepare('INSERT INTO siswa_tokens (siswa_id, token, expires_at) VALUES (?, ?, ?)');
$ins->execute([$siswa['id'], $token, $expires]);

json_out([
    'success' => true,
    'message' => 'Login berhasil.',
    'token'   => $token,
    'expires_at' => isoDate($expires),
    'siswa'   => [
        'id'          => (int)$siswa['id'],
        'nis'         => $siswa['nis'],
        'nisn'        => $siswa['nisn'],
        'nama_lengkap'=> $siswa['nama_lengkap'],
        'jenis_kelamin' => $siswa['jenis_kelamin'],
        'foto'        => $siswa['foto'],
        'kelas_id'    => $siswa['kelas_id'] ? (int)$siswa['kelas_id'] : null,
        'nama_kelas'  => $siswa['nama_kelas'],
        'tingkat'     => $siswa['tingkat'],
        'program'     => $siswa['program'],
        'tahun_ajaran'=> $siswa['tahun_ajaran'],
        'status'      => $siswa['status'],
    ],
]);

