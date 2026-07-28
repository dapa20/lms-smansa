<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../data_siswa.php?tab=guru');
}

$id            = (int)($_POST['id'] ?? 0);
$namaLengkap   = trim($_POST['nama_lengkap'] ?? '');
$email         = trim($_POST['email'] ?? '');
$nip           = trim($_POST['nip'] ?? '');
$password      = (string)($_POST['password'] ?? '');
$role          = in_array($_POST['role'] ?? '', ['guru', 'admin']) ? $_POST['role'] : 'guru';
$status        = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif']) ? $_POST['status'] : 'aktif';
$mapelKeahlian = trim($_POST['mapel_keahlian'] ?? '');

if ($namaLengkap === '' || $email === '') {
    setFlash('gagal', 'Nama dan email wajib diisi.');
    redirect('../../data_siswa.php?tab=guru');
}
if ($id === 0 && strlen($password) < 6) {
    setFlash('gagal', 'Kata sandi wajib diisi (minimal 6 karakter) untuk akun baru.');
    redirect('../../data_siswa.php?tab=guru');
}

try {
    if ($id > 0) {
        if ($password !== '') {
            if (strlen($password) < 6) {
                setFlash('gagal', 'Kata sandi baru minimal 6 karakter.');
                redirect('../../data_siswa.php?tab=guru');
            }
            $stmt = $pdo->prepare('UPDATE users SET nama_lengkap=?, email=?, nip=?, role=?, status=?, mapel_keahlian=?, password=? WHERE id=?');
            $stmt->execute([$namaLengkap, $email, $nip ?: null, $role, $status, $mapelKeahlian ?: null, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET nama_lengkap=?, email=?, nip=?, role=?, status=?, mapel_keahlian=? WHERE id=?');
            $stmt->execute([$namaLengkap, $email, $nip ?: null, $role, $status, $mapelKeahlian ?: null, $id]);
        }
        setFlash('sukses', "Akun \"$namaLengkap\" berhasil diperbarui.");
    } else {
        $stmt = $pdo->prepare('INSERT INTO users (nama_lengkap, email, password, nip, role, status, mapel_keahlian) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$namaLengkap, $email, password_hash($password, PASSWORD_DEFAULT), $nip ?: null, $role, $status, $mapelKeahlian ?: null]);
        setFlash('sukses', "Akun \"$namaLengkap\" berhasil ditambahkan.");
    }
} catch (PDOException $e) {
    $pesan = str_contains($e->getMessage(), 'email')
        ? 'Gagal menyimpan: email tersebut sudah dipakai akun lain.'
        : 'Gagal menyimpan data akun.';
    setFlash('gagal', $pesan);
}

redirect('../data_siswa.php?tab=guru');
