<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/pengaturan.php');
}

$userId        = (int)$_SESSION['user_id'];
$namaLengkap   = trim($_POST['nama_lengkap'] ?? '');
$email         = trim($_POST['email'] ?? '');
$nip           = trim($_POST['nip'] ?? '');
$mapelKeahlian = trim($_POST['mapel_keahlian'] ?? '');
$bio           = trim($_POST['bio'] ?? '');

if ($namaLengkap === '' || $email === '') {
    setFlash('gagal', 'Nama dan email wajib diisi.');
    redirect('../../pages/pengaturan.php');
}

// Upload foto baru (opsional)
$namaFileFoto = null;
if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $ekstensi = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    if (in_array($ekstensi, ['jpg', 'jpeg', 'png']) && $_FILES['foto']['size'] <= 2 * 1024 * 1024) {
        $namaFileFoto = 'avatar_' . $userId . '_' . time() . '.' . $ekstensi;
        move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../../uploads/avatar/' . $namaFileFoto);
    } else {
        setFlash('gagal', 'Foto gagal diunggah: pastikan format JPG/PNG dan ukuran maksimal 2MB. Profil lainnya tetap disimpan.');
    }
}

try {
    if ($namaFileFoto) {
        $stmt = $pdo->prepare('UPDATE users SET nama_lengkap=?, email=?, nip=?, mapel_keahlian=?, bio=?, foto=? WHERE id=?');
        $stmt->execute([$namaLengkap, $email, $nip ?: null, $mapelKeahlian ?: null, $bio ?: null, $namaFileFoto, $userId]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET nama_lengkap=?, email=?, nip=?, mapel_keahlian=?, bio=? WHERE id=?');
        $stmt->execute([$namaLengkap, $email, $nip ?: null, $mapelKeahlian ?: null, $bio ?: null, $userId]);
    }
    $_SESSION['user_name'] = $namaLengkap;
    if (empty($_SESSION['flash'])) {
        setFlash('sukses', 'Profil berhasil diperbarui.');
    }
} catch (PDOException $e) {
    setFlash('gagal', str_contains($e->getMessage(), 'email') ? 'Email tersebut sudah dipakai akun lain.' : 'Gagal menyimpan profil.');
}

redirect('../../pages/pengaturan.php');
