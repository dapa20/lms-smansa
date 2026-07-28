<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../materi.php');
}

$id           = (int)($_POST['id'] ?? 0);
$judul        = trim($_POST['judul'] ?? '');
$mapelId      = (int)($_POST['mapel_id'] ?? 0);
$kelasTingkat = in_array($_POST['kelas_tingkat'] ?? '', ['X', 'XI', 'XII']) ? $_POST['kelas_tingkat'] : 'X';
$semester     = in_array($_POST['semester'] ?? '', ['Ganjil', 'Genap']) ? $_POST['semester'] : 'Ganjil';

if ($id === 0 || $judul === '' || $mapelId === 0) {
    setFlash('gagal', 'Data tidak lengkap, perubahan tidak disimpan.');
    redirect('../../materi.php');
}

$stmt = $pdo->prepare('SELECT diunggah_oleh FROM materi WHERE id = ?');
$stmt->execute([$id]);
$pemilik = $stmt->fetch();
if (!$pemilik || (!isAdmin() && (int)$pemilik['diunggah_oleh'] !== (int)$_SESSION['user_id'])) {
    setFlash('gagal', 'Anda hanya bisa mengubah materi yang Anda unggah sendiri.');
    redirect('../../materi.php');
}

$stmt = $pdo->prepare('UPDATE materi SET judul=?, mapel_id=?, kelas_tingkat=?, semester=? WHERE id=?');
$stmt->execute([$judul, $mapelId, $kelasTingkat, $semester, $id]);

setFlash('sukses', 'Info materi berhasil diperbarui.');
redirect('../materi.php');
