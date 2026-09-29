<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireCsrf(); // Validasi CSRF token

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/tugas_ujian.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT mapel_id, kelas_id, dibuat_oleh, file_lampiran FROM tugas_ujian WHERE id = ?');
    $stmt->execute([$id]);
    $tugas = $stmt->fetch();
    
    if ($tugas) {
        if (!isAdmin() && (int)$tugas['dibuat_oleh'] !== (int)$_SESSION['user_id']) {
            setFlash('gagal', 'Anda hanya bisa menghapus tugas/ujian yang Anda buat sendiri.');
            redirect("../../pages/tugas_detail.php?kelas_id={$tugas['kelas_id']}&mapel_id={$tugas['mapel_id']}");
        }
        
        // Hapus file lampiran jika ada
        if (!empty($tugas['file_lampiran'])) {
            $filePath = __DIR__ . '/../../uploads/tugas/' . $tugas['file_lampiran'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        $stmtDel = $pdo->prepare('DELETE FROM tugas_ujian WHERE id = ?');
        $stmtDel->execute([$id]);
        setFlash('sukses', 'Tugas/ujian berhasil dihapus.');
        
        redirect("../../pages/tugas_detail.php?kelas_id={$tugas['kelas_id']}&mapel_id={$tugas['mapel_id']}");
    }
}

redirect('../../pages/tugas_ujian.php');
