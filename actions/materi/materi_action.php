<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user = currentUser();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'add_section') {
    $kelasId = (int)($_POST['kelas_id'] ?? 0);
    $mapelId = (int)($_POST['mapel_id'] ?? 0);
    $judul   = trim($_POST['judul'] ?? 'New section');

    if ($kelasId > 0 && $mapelId > 0) {
        $stmt = $pdo->prepare("SELECT MAX(urutan) FROM materi_section WHERE kelas_id = ? AND mapel_id = ?");
        $stmt->execute([$kelasId, $mapelId]);
        $maxUrutan = (int)$stmt->fetchColumn() + 1;

        $ins = $pdo->prepare("INSERT INTO materi_section (kelas_id, mapel_id, judul, urutan, dibuat_oleh) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$kelasId, $mapelId, $judul, $maxUrutan, $user['id']]);
        setFlash('success', 'Section baru berhasil ditambahkan.');
    }
    redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
}

elseif ($action === 'edit_section') {
    $sectionId = (int)($_POST['section_id'] ?? 0);
    $judul     = trim($_POST['judul'] ?? '');
    $kelasId   = (int)($_POST['kelas_id'] ?? 0);
    $mapelId   = (int)($_POST['mapel_id'] ?? 0);

    if ($sectionId > 0 && $judul !== '') {
        $upd = $pdo->prepare("UPDATE materi_section SET judul = ? WHERE id = ?");
        $upd->execute([$judul, $sectionId]);
        setFlash('success', 'Judul section berhasil diperbarui.');
    }
    redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
}

elseif ($action === 'delete_section') {
    $sectionId = (int)($_POST['section_id'] ?? 0);
    $kelasId   = (int)($_POST['kelas_id'] ?? 0);
    $mapelId   = (int)($_POST['mapel_id'] ?? 0);

    if ($sectionId > 0) {
        $del = $pdo->prepare("DELETE FROM materi_section WHERE id = ?");
        $del->execute([$sectionId]);
        setFlash('success', 'Section berhasil dihapus.');
    }
    redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
}

elseif ($action === 'add_item') {
    $sectionId = (int)($_POST['section_id'] ?? 0);
    $kelasId   = (int)($_POST['kelas_id'] ?? 0);
    $mapelId   = (int)($_POST['mapel_id'] ?? 0);
    $tipe      = $_POST['tipe'] ?? 'file'; // file, link, gambar, video, diskusi
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $urlLink   = trim($_POST['url_link'] ?? '');

    $namaFile = null;
    $namaFileAsli = null;
    $ukuranFile = 0;

    // Handle File Upload
    if (!empty($_FILES['file_upload']['name'])) {
        $file = $_FILES['file_upload'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $namaFileAsli = $file['name'];
            $ukuranFile = $file['size'];
            $namaFile = 'materi_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $uploadDir = __DIR__ . '/../../uploads/materi/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            move_uploaded_file($file['tmp_name'], $uploadDir . $namaFile);
        }
    }

    if ($sectionId > 0 && $judul !== '') {
        $ins = $pdo->prepare("INSERT INTO materi_item (section_id, tipe, judul, deskripsi, url_link, nama_file, nama_file_asli, ukuran_file, diunggah_oleh) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$sectionId, $tipe, $judul, $deskripsi, $urlLink, $namaFile, $namaFileAsli, $ukuranFile, $user['id']]);
        setFlash('success', 'Item materi/konten berhasil ditambahkan.');
    }
    redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
}

elseif ($action === 'delete_item') {
    $itemId  = (int)($_POST['item_id'] ?? 0);
    $kelasId = (int)($_POST['kelas_id'] ?? 0);
    $mapelId = (int)($_POST['mapel_id'] ?? 0);

    if ($itemId > 0) {
        $del = $pdo->prepare("DELETE FROM materi_item WHERE id = ?");
        $del->execute([$itemId]);
        setFlash('success', 'Item materi berhasil dihapus.');
    }
    redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
}

elseif ($action === 'add_reply') {
    $itemId  = (int)($_POST['item_id'] ?? 0);
    $kelasId = (int)($_POST['kelas_id'] ?? 0);
    $mapelId = (int)($_POST['mapel_id'] ?? 0);
    $pesan   = trim($_POST['pesan'] ?? '');

    if ($itemId > 0 && $pesan !== '') {
        $ins = $pdo->prepare("INSERT INTO materi_diskusi_balasan (item_id, user_id, pesan) VALUES (?, ?, ?)");
        $ins->execute([$itemId, $user['id'], $pesan]);
        setFlash('success', 'Balasan diskusi berhasil dikirim.');
    }
    redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId#item-$itemId");
}

redirect('../../pages/materi.php');
