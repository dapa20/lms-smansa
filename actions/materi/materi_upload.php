<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../materi.php');
}

$judul        = trim($_POST['judul'] ?? '');
$mapelId      = (int)($_POST['mapel_id'] ?? 0);
$kelasTingkat = in_array($_POST['kelas_tingkat'] ?? '', ['X', 'XI', 'XII']) ? $_POST['kelas_tingkat'] : 'X';
$semester     = in_array($_POST['semester'] ?? '', ['Ganjil', 'Genap']) ? $_POST['semester'] : 'Ganjil';

if ($judul === '' || $mapelId === 0 || empty($_FILES['file_materi']) || $_FILES['file_materi']['error'] !== UPLOAD_ERR_OK) {
    setFlash('gagal', 'Upload gagal. Pastikan judul diisi dan file berhasil dipilih.');
    redirect('../../materi.php');
}

$file        = $_FILES['file_materi'];
$ukuranMax   = 50 * 1024 * 1024; // 50 MB
$ekstensiMap = [
    'pdf' => 'pdf',
    'doc' => 'docx', 'docx' => 'docx',
    'ppt' => 'pptx', 'pptx' => 'pptx',
    'xls' => 'xlsx', 'xlsx' => 'xlsx',
    'mp4' => 'video', 'mov' => 'video', 'avi' => 'video',
];
$ekstensiAsli = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($file['size'] > $ukuranMax) {
    setFlash('gagal', 'Upload gagal. Ukuran file maksimal adalah 50MB.');
    redirect('../../materi.php');
}
if (!isset($ekstensiMap[$ekstensiAsli])) {
    setFlash('gagal', 'Upload gagal. Format file tidak didukung.');
    redirect('../../materi.php');
}

$tipeFile     = $ekstensiMap[$ekstensiAsli];
$namaFileBaru = bin2hex(random_bytes(12)) . '.' . $ekstensiAsli;
$tujuan       = __DIR__ . '/../../uploads/materi/' . $namaFileBaru;

if (!move_uploaded_file($file['tmp_name'], $tujuan)) {
    setFlash('gagal', 'Upload gagal. File tidak dapat disimpan di server.');
    redirect('../../materi.php');
}

$stmt = $pdo->prepare('INSERT INTO materi (judul, mapel_id, kelas_tingkat, semester, tipe_file, nama_file, nama_file_asli, ukuran_file, diunggah_oleh)
                        VALUES (?,?,?,?,?,?,?,?,?)');
$stmt->execute([$judul, $mapelId, $kelasTingkat, $semester, $tipeFile, $namaFileBaru, $file['name'], $file['size'], $_SESSION['user_id']]);

setFlash('sukses', "Materi \"$judul\" berhasil diunggah.");
redirect('../materi.php');
