<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireCsrf(); // Validasi CSRF token

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

// Sanitasi HTML untuk mencegah XSS
$isi = sanitizeHtmlFallback($isi, 
    ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'strike', 'del', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 
     'ul', 'ol', 'li', 'a', 'img', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 
     'div', 'span', 'blockquote', 'pre', 'code', 'iframe'],
    [
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'width', 'height', 'class'],
        'iframe' => ['src', 'width', 'height', 'frameborder', 'allowfullscreen', 'class'],
        'table' => ['class'], 'td' => ['colspan', 'rowspan', 'class'], 'th' => ['colspan', 'rowspan', 'class'],
        'div' => ['class'], 'span' => ['class'], 'p' => ['class'],
        'h1' => ['class'], 'h2' => ['class'], 'h3' => ['class'],
        'h4' => ['class'], 'h5' => ['class'], 'h6' => ['class'],
        'ul' => ['class'], 'ol' => ['class'], 'li' => ['class'],
        'blockquote' => ['class'], 'pre' => ['class'], 'code' => ['class']
    ]
);

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
        // Cek ownership: hanya admin atau pemilik yang bisa edit
        if (!isAdmin()) {
            $stmt = $pdo->prepare('SELECT dibuat_oleh FROM pengumuman WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing || (int)$existing['dibuat_oleh'] !== (int)$user['id']) {
                setFlash('gagal', 'Anda tidak memiliki akses untuk mengedit pengumuman ini.');
                redirect($redirectTo);
            }
        }
        
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
