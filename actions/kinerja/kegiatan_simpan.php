<?php
/**
 * Action: Simpan Kegiatan Kinerja Harian
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/kinerja_harian.php');
}

$user = currentUser();
$isAdmin = isAdmin();

$id         = (int)($_POST['id'] ?? 0);
$guruId     = $isAdmin ? (int)($_POST['guru_id'] ?? $user['id']) : $user['id'];
$tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
$kegiatan   = trim($_POST['kegiatan'] ?? '');
$volume     = trim($_POST['volume'] ?? '1 Kegiatan');
$keterangan = trim($_POST['keterangan'] ?? '');

$bulan = date('m', strtotime($tanggal));
$tahun = date('Y', strtotime($tanggal));
$redirectUrl = "../../pages/kinerja_harian.php?bulan=$bulan&tahun=$tahun&guru_id=$guruId";

if ($kegiatan === '') {
    setFlash('gagal', 'Uraian kegiatan tidak boleh kosong.');
    redirect($redirectUrl);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

try {
    if ($id > 0) {
        // Cek kepemilikan
        $stmtCek = $pdo->prepare("SELECT * FROM kinerja_harian_kegiatan WHERE id = ?");
        $stmtCek->execute([$id]);
        $existing = $stmtCek->fetch();

        if (!$existing || (!$isAdmin && (int)$existing['guru_id'] !== (int)$user['id'])) {
            setFlash('gagal', 'Anda tidak memiliki hak untuk mengubah data ini.');
            redirect($redirectUrl);
        }

        $stmt = $pdo->prepare("UPDATE kinerja_harian_kegiatan 
                               SET tanggal = ?, kegiatan = ?, volume = ?, keterangan = ?
                               WHERE id = ?");
        $stmt->execute([$tanggal, $kegiatan, $volume, $keterangan, $id]);
        setFlash('sukses', 'Kegiatan kinerja harian berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO kinerja_harian_kegiatan (guru_id, tanggal, kegiatan, volume, keterangan)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$guruId, $tanggal, $kegiatan, $volume, $keterangan]);
        setFlash('sukses', 'Kegiatan baru berhasil ditambahkan ke laporan kinerja harian.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan kegiatan: ' . $e->getMessage());
}

redirect($redirectUrl);
