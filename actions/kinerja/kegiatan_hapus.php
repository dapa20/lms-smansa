<?php
/**
 * Action: Hapus Kegiatan Kinerja Harian
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$user = currentUser();
$isAdmin = isAdmin();

$id      = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$bulan   = $_POST['bulan'] ?? $_GET['bulan'] ?? date('m');
$tahun   = $_POST['tahun'] ?? $_GET['tahun'] ?? date('Y');
$guruId  = (int)($_POST['guru_id'] ?? $_GET['guru_id'] ?? $user['id']);

$redirectUrl = "../../pages/kinerja_harian.php?bulan=$bulan&tahun=$tahun&guru_id=$guruId";

if ($id <= 0) {
    redirect($redirectUrl);
}

try {
    $stmtCek = $pdo->prepare("SELECT * FROM kinerja_harian_kegiatan WHERE id = ?");
    $stmtCek->execute([$id]);
    $existing = $stmtCek->fetch();

    if (!$existing || (!$isAdmin && (int)$existing['guru_id'] !== (int)$user['id'])) {
        setFlash('gagal', 'Anda tidak memiliki hak untuk menghapus data ini.');
        redirect($redirectUrl);
    }

    $stmtDel = $pdo->prepare("DELETE FROM kinerja_harian_kegiatan WHERE id = ?");
    $stmtDel->execute([$id]);
    setFlash('sukses', 'Kegiatan berhasil dihapus dari laporan.');
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menghapus kegiatan: ' . $e->getMessage());
}

redirect($redirectUrl);
