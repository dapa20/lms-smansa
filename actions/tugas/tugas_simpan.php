<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireCsrf(); // Validasi CSRF token

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/tugas_ujian.php');
}

$judul    = trim($_POST['judul'] ?? '');
$jenis    = in_array($_POST['jenis'] ?? '', ['tugas', 'kuis', 'uts', 'uas']) ? $_POST['jenis'] : 'tugas';
$mapelId  = (int)($_POST['mapel_id'] ?? 0);
$kelasId  = (int)($_POST['kelas_id'] ?? 0);
$deadline = $_POST['tanggal_deadline'] ?? '';
$deskripsi = trim($_POST['deskripsi'] ?? '');

$redirectUrl = "../../pages/tugas_detail.php?kelas_id=$kelasId&mapel_id=$mapelId";

if ($judul === '' || $mapelId === 0 || $kelasId === 0 || $deadline === '') {
    setFlash('gagal', 'Data tidak lengkap. Tugas/ujian tidak disimpan.');
    redirect($redirectUrl);
}

// Upload file lampiran jika ada
$fileLampiran = null;
if (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../uploads/tugas/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $ext = strtolower(pathinfo($_FILES['file_lampiran']['name'], PATHINFO_EXTENSION));
    $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    
    if (in_array($ext, $allowedExt)) {
        $filename = uniqid('lampiran_') . '.' . $ext;
        if (move_uploaded_file($_FILES['file_lampiran']['tmp_name'], $uploadDir . $filename)) {
            $fileLampiran = $filename;
        }
    } else {
        setFlash('gagal', 'Ekstensi file tidak diizinkan.');
        redirect($redirectUrl);
    }
}

$stmt = $pdo->prepare('INSERT INTO tugas_ujian (judul, jenis, mapel_id, kelas_id, deskripsi, tanggal_deadline, dibuat_oleh, status, file_lampiran)
                        VALUES (?,?,?,?,?,?,?,?,?)');
$stmt->execute([$judul, $jenis, $mapelId, $kelasId, $deskripsi ?: null, str_replace('T', ' ', $deadline), $_SESSION['user_id'], 'aktif', $fileLampiran]);

setFlash('sukses', "\"$judul\" berhasil ditugaskan ke kelas.");
redirect($redirectUrl);
