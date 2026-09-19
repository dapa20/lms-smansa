<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$redirectTo = trim($_POST['redirect_to'] ?? '../../index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($redirectTo);
}

$user = currentUser();
$id       = (int)($_POST['id'] ?? 0);
$kepada   = trim($_POST['kepada'] ?? '');
$isi      = trim($_POST['isi'] ?? '');
$kategori = in_array($_POST['kategori'] ?? '', ['penting', 'informasi']) ? $_POST['kategori'] : 'informasi';

if ($isi === '') {
    setFlash('gagal', 'Isi pengumuman tidak boleh kosong.');
    redirect('../../index.php');
}

// Auto title from kepada or strip html of isi if empty
$judul = trim($_POST['judul'] ?? '');
if ($judul === '') {
    $plainText = trim(strip_tags($isi));
    if (!empty($kepada)) {
        $judul = 'Pengumuman (' . $kepada . ')';
    } else {
        $judul = mb_substr($plainText, 0, 50) ?: 'Pengumuman Sekolah';
    }
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE pengumuman SET kepada = ?, judul = ?, isi = ?, kategori = ? WHERE id = ?');
        $stmt->execute([$kepada, $judul, $isi, $kategori, $id]);
        setFlash('sukses', 'Pengumuman berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO pengumuman (kepada, judul, isi, kategori, dibuat_oleh) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$kepada, $judul, $isi, $kategori, $user['id']]);
        setFlash('sukses', 'Info/Pengumuman berhasil disimpan.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan pengumuman: ' . $e->getMessage());
}

redirect($redirectTo);
