<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/kelas_jadwal.php');
}

$id         = (int)($_POST['id'] ?? 0);
$guruId     = (int)($_POST['guru_id'] ?? 0);
$mapelId    = (int)($_POST['mapel_id'] ?? 0);
$kelasId    = (int)($_POST['kelas_id'] ?? 0);
$jenis      = in_array($_POST['jenis'] ?? '', ['reguler', 'ujian', 'rapat', 'lainnya']) ? $_POST['jenis'] : 'reguler';
$jamMulai   = $_POST['jam_mulai'] ?? '';
$jamSelesai = $_POST['jam_selesai'] ?? '';
$ruang      = trim($_POST['ruang'] ?? '');
$keterangan = trim($_POST['keterangan'] ?? '');
$tanggal    = trim($_POST['tanggal'] ?? '');
$hariInput  = $_POST['hari'] ?? '';

$hariNamaIndo = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

if ($guruId === 0 || $mapelId === 0 || $kelasId === 0 || $jamMulai === '' || $jamSelesai === '') {
    setFlash('gagal', 'Data tidak lengkap. Jadwal tidak disimpan.');
    redirect('../../pages/kelas_jadwal.php');
}

// Jika tanggal spesifik diisi, hari otomatis dihitung dari tanggal tsb.
// Jika tidak, dipakai jadwal rutin mingguan sesuai hari yang dipilih.
if ($tanggal !== '') {
    $namaHari = $hariNamaIndo[(int)date('N', strtotime($tanggal)) - 1];
    if ($namaHari === 'Minggu') {
        setFlash('gagal', 'Tanggal yang dipilih jatuh pada hari Minggu (libur sekolah).');
        redirect('../../pages/kelas_jadwal.php');
    }
    $hari = $namaHari;
} else {
    $hari = in_array($hariInput, $hariNamaIndo) ? $hariInput : 'Senin';
    $tanggal = null;
}

if ($id > 0) {
    // UPDATE jadwal yang sudah ada
    $stmt = $pdo->prepare('UPDATE jadwal_mengajar SET guru_id=?, mapel_id=?, kelas_id=?, hari=?, tanggal=?, jam_mulai=?, jam_selesai=?, ruang=?, jenis=?, keterangan=? WHERE id=?');
    $stmt->execute([$guruId, $mapelId, $kelasId, $hari, $tanggal, $jamMulai, $jamSelesai, $ruang ?: null, $jenis, $keterangan ?: null, $id]);
    setFlash('sukses', 'Jadwal berhasil diperbarui.');
} else {
    // INSERT jadwal baru
    $stmt = $pdo->prepare('INSERT INTO jadwal_mengajar (guru_id, mapel_id, kelas_id, hari, tanggal, jam_mulai, jam_selesai, ruang, jenis, keterangan)
                            VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$guruId, $mapelId, $kelasId, $hari, $tanggal, $jamMulai, $jamSelesai, $ruang ?: null, $jenis, $keterangan ?: null]);
    setFlash('sukses', 'Jadwal berhasil ditambahkan.');
}

redirect('../../pages/kelas_jadwal.php');

