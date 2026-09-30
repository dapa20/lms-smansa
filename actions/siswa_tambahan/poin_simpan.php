<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/data_siswa.php?tab=poin'); }

$id          = (int)($_POST['id'] ?? 0);
$siswaId     = (int)($_POST['siswa_id'] ?? 0);
$kelasId     = (int)($_POST['kelas_id'] ?? 0);
$jenis       = in_array($_POST['jenis'] ?? '', ['pelanggaran','prestasi']) ? $_POST['jenis'] : 'pelanggaran';
$poin        = (int)($_POST['poin'] ?? 0);
$kategori    = trim($_POST['kategori'] ?? '');
$keterangan  = trim($_POST['keterangan'] ?? '');
$tanggal     = $_POST['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = date('Y-m-d');

if ($siswaId === 0 || $kelasId === 0 || $kategori === '') {
    setFlash('gagal', 'Siswa, kelas, dan kategori wajib diisi.');
    redirect('../../pages/data_siswa.php?tab=poin&kelas_id='.$kelasId);
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE poin_siswa SET siswa_id=?, jenis=?, poin=?, kategori=?, keterangan=?, tanggal=? WHERE id=?');
        $stmt->execute([$siswaId, $jenis, $poin, $kategori, $keterangan, $tanggal, $id]);
        setFlash('sukses', 'Data poin berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO poin_siswa (siswa_id, kelas_id, jenis, poin, kategori, keterangan, tanggal, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$siswaId, $kelasId, $jenis, $poin, $kategori, $keterangan, $tanggal, $user['id']]);
        setFlash('sukses', 'Data poin berhasil ditambahkan.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan data poin.');
}
redirect('../../pages/data_siswa.php?tab=poin&kelas_id='.$kelasId);