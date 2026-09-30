<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/data_siswa.php?tab=catatan'); }

$id       = (int)($_POST['id'] ?? 0);
$siswaId  = (int)($_POST['siswa_id'] ?? 0);
$kelasId  = (int)($_POST['kelas_id'] ?? 0);
$catatan  = trim($_POST['catatan'] ?? '');
$jenis    = in_array($_POST['jenis'] ?? '', ['positif','perhatian','pelanggaran','lainnya']) ? $_POST['jenis'] : 'perhatian';

if ($siswaId === 0 || $kelasId === 0 || $catatan === '') {
    setFlash('gagal', 'Siswa, kelas, dan catatan wajib diisi.');
    redirect('../../pages/data_siswa.php?tab=catatan&kelas_id='.$kelasId);
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE catatan_wali SET siswa_id=?, catatan=?, jenis=? WHERE id=?');
        $stmt->execute([$siswaId, $catatan, $jenis, $id]);
        setFlash('sukses', 'Catatan berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO catatan_wali (siswa_id, kelas_id, catatan, jenis, dibuat_oleh) VALUES (?,?,?,?,?)');
        $stmt->execute([$siswaId, $kelasId, $catatan, $jenis, $user['id']]);
        setFlash('sukses', 'Catatan baru berhasil ditambahkan.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan catatan.');
}
redirect('../../pages/data_siswa.php?tab=catatan&kelas_id='.$kelasId);