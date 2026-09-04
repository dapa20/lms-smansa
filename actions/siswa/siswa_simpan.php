<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/data_siswa.php');
}

$id           = (int)($_POST['id'] ?? 0);
$namaLengkap  = trim($_POST['nama_lengkap'] ?? '');
$nis          = trim($_POST['nis'] ?? '');
$nisn         = trim($_POST['nisn'] ?? '');
$jenisKelamin = in_array($_POST['jenis_kelamin'] ?? '', ['L', 'P']) ? $_POST['jenis_kelamin'] : 'L';
$status       = in_array($_POST['status'] ?? '', ['aktif', 'pindah', 'lulus']) ? $_POST['status'] : 'aktif';
$kelasId      = (int)($_POST['kelas_id'] ?? 0);

if ($namaLengkap === '' || $nis === '' || $nisn === '' || $kelasId === 0) {
    setFlash('gagal', 'Semua kolom wajib diisi. Data siswa tidak disimpan.');
    redirect('../../pages/data_siswa.php');
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE siswa SET nama_lengkap=?, nis=?, nisn=?, jenis_kelamin=?, status=?, kelas_id=? WHERE id=?');
        $stmt->execute([$namaLengkap, $nis, $nisn, $jenisKelamin, $status, $kelasId, $id]);
        setFlash('sukses', "Data siswa \"$namaLengkap\" berhasil diperbarui.");
    } else {
        // Password default untuk siswa baru: siswa123 (di-hash bcrypt)
        // Password ini bisa diganti/dikelola kemudian melalui fitur reset di aplikasi.
        $passwordDefault = password_hash('siswa123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO siswa (nama_lengkap, nis, nisn, jenis_kelamin, status, kelas_id, password) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$namaLengkap, $nis, $nisn, $jenisKelamin, $status, $kelasId, $passwordDefault]);
        setFlash('sukses', "Siswa \"$namaLengkap\" berhasil ditambahkan. Password default: siswa123");
    }
} catch (PDOException $e) {
    // Kemungkinan besar NISN duplikat (UNIQUE constraint)
    $pesan = str_contains($e->getMessage(), 'uniq_nisn')
        ? 'Gagal menyimpan: NISN tersebut sudah terdaftar untuk siswa lain.'
        : 'Gagal menyimpan data siswa. Silakan periksa kembali data yang dimasukkan.';
    setFlash('gagal', $pesan);
}

redirect('../../pages/data_siswa.php');
