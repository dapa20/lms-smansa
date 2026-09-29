<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCsrf(); // Validasi CSRF token

$user = currentUser();
$isAdmin = isAdmin();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/**
 * Helper: Cek apakah user adalah guru yang mengajar kelas+mapel ini
 */
function isGuruPengampu(int $guruId, int $kelasId, int $mapelId): bool {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ?");
    $stmt->execute([$guruId, $kelasId, $mapelId]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Helper: Cek apakah user boleh akses section ini (admin atau guru pengampu)
 */
function canAccessSection(int $sectionId, int $userId, bool $isAdmin): bool {
    if ($isAdmin) return true;
    global $pdo;
    $stmt = $pdo->prepare("SELECT ms.kelas_id, ms.mapel_id FROM materi_section ms WHERE ms.id = ?");
    $stmt->execute([$sectionId]);
    $section = $stmt->fetch();
    if (!$section) return false;
    return isGuruPengampu($userId, (int)$section['kelas_id'], (int)$section['mapel_id']);
}

/**
 * Helper: Cek apakah user boleh akses item ini (admin atau pemilik/guru pengampu)
 */
function canAccessItem(int $itemId, int $userId, bool $isAdmin): bool {
    if ($isAdmin) return true;
    global $pdo;
    $stmt = $pdo->prepare("SELECT mi.diunggah_oleh, ms.kelas_id, ms.mapel_id 
                           FROM materi_item mi 
                           JOIN materi_section ms ON ms.id = mi.section_id 
                           WHERE mi.id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) return false;
    // Boleh jika: pemilik item ATAU guru pengampu
    if ((int)$item['diunggah_oleh'] === $userId) return true;
    return isGuruPengampu($userId, (int)$item['kelas_id'], (int)$item['mapel_id']);
}

if ($action === 'add_section') {
    $kelasId = (int)($_POST['kelas_id'] ?? 0);
    $mapelId = (int)($_POST['mapel_id'] ?? 0);
    $judul   = trim($_POST['judul'] ?? 'New section');

    // Validasi: hanya admin atau guru pengampu yang boleh tambah section
    if (!$isAdmin && !isGuruPengampu($user['id'], $kelasId, $mapelId)) {
        setFlash('error', 'Anda tidak memiliki akses untuk kelas/mapel ini.');
        redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
    }

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

    // Validasi ownership
    if (!canAccessSection($sectionId, $user['id'], $isAdmin)) {
        setFlash('error', 'Anda tidak memiliki akses untuk mengedit section ini.');
        redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
    }

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

    // Validasi ownership
    if (!canAccessSection($sectionId, $user['id'], $isAdmin)) {
        setFlash('error', 'Anda tidak memiliki akses untuk menghapus section ini.');
        redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
    }

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

    // Validasi: hanya admin atau guru pengampu yang boleh tambah item
    if (!$isAdmin && !canAccessSection($sectionId, $user['id'], $isAdmin)) {
        setFlash('error', 'Anda tidak memiliki akses untuk menambah konten di section ini.');
        redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
    }

    $namaFile = null;
    $namaFileAsli = null;
    $ukuranFile = 0;

    // Handle File Upload dengan validasi keamanan
    if (!empty($_FILES['file_upload']['name'])) {
        $file = $_FILES['file_upload'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validasi extension yang diperbolehkan
            $allowedExt = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'zip', 'rar'];
            if (!in_array($ext, $allowedExt)) {
                setFlash('error', 'Tipe file tidak diperbolehkan. Extension yang diizinkan: ' . implode(', ', $allowedExt));
                redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
            }
            
            // Validasi MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowedMime = [
                'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'audio/mpeg', 'application/zip', 'application/x-rar'
            ];
            
            if (!in_array($mimeType, $allowedMime)) {
                setFlash('error', 'Tipe file tidak valid.');
                redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
            }
            
            // Validasi ukuran file (max 50MB)
            $maxSize = 50 * 1024 * 1024; // 50MB
            if ($file['size'] > $maxSize) {
                setFlash('error', 'Ukuran file terlalu besar. Maksimal 50MB.');
                redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
            }
            
            $namaFileAsli = $file['name'];
            $ukuranFile = $file['size'];
            // Generate nama file random untuk keamanan
            $namaFile = bin2hex(random_bytes(16)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../../uploads/materi/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $namaFile)) {
                setFlash('error', 'Gagal mengupload file.');
                redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
            }
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

    // Validasi ownership
    if (!canAccessItem($itemId, $user['id'], $isAdmin)) {
        setFlash('error', 'Anda tidak memiliki akses untuk menghapus item ini.');
        redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
    }

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

    // Validasi: user harus bisa akses item untuk bisa reply
    if (!$isAdmin && !canAccessItem($itemId, $user['id'], $isAdmin)) {
        setFlash('error', 'Anda tidak memiliki akses untuk membalas diskusi ini.');
        redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId");
    }

    if ($itemId > 0 && $pesan !== '') {
        $ins = $pdo->prepare("INSERT INTO materi_diskusi_balasan (item_id, user_id, pesan) VALUES (?, ?, ?)");
        $ins->execute([$itemId, $user['id'], $pesan]);
        setFlash('success', 'Balasan diskusi berhasil dikirim.');
    }
    redirect("../../pages/materi_detail.php?kelas_id=$kelasId&mapel_id=$mapelId#item-$itemId");
}

redirect('../../pages/materi.php');
