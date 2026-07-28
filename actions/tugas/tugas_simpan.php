<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../tugas_ujian.php');
}

$judul    = trim($_POST['judul'] ?? '');
$jenis    = in_array($_POST['jenis'] ?? '', ['tugas', 'kuis', 'uts', 'uas']) ? $_POST['jenis'] : 'tugas';
$mapelId  = (int)($_POST['mapel_id'] ?? 0);
$kelasId  = (int)($_POST['kelas_id'] ?? 0);
$deadline = $_POST['tanggal_deadline'] ?? '';
$deskripsi = trim($_POST['deskripsi'] ?? '');

if ($judul === '' || $mapelId === 0 || $kelasId === 0 || $deadline === '') {
    setFlash('gagal', 'Data tidak lengkap. Tugas/ujian tidak disimpan.');
    redirect('../../tugas_ujian.php');
}

$stmt = $pdo->prepare('INSERT INTO tugas_ujian (judul, jenis, mapel_id, kelas_id, deskripsi, tanggal_deadline, dibuat_oleh, status)
                        VALUES (?,?,?,?,?,?,?,\'aktif\')');
$stmt->execute([$judul, $jenis, $mapelId, $kelasId, $deskripsi ?: null, str_replace('T', ' ', $deadline), $_SESSION['user_id']]);

setFlash('sukses', "\"$judul\" berhasil dibuat.");
redirect('../tugas_ujian.php');
