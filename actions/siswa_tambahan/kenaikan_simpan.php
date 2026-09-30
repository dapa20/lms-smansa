<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/data_siswa.php?tab=kenaikan'); }

$id              = (int)($_POST['id'] ?? 0);
$siswaId         = (int)($_POST['siswa_id'] ?? 0);
$kelasAsalId     = (int)($_POST['kelas_asal_id'] ?? 0);
$kelasTujuanId   = ($_POST['kelas_tujuan_id'] ?? '') !== '' ? (int)$_POST['kelas_tujuan_id'] : null;
$tahunAjaran     = trim($_POST['tahun_ajaran'] ?? '2024/2025');
$status          = in_array($_POST['status'] ?? '', ['naik','tinggal','lulus','pindah']) ? $_POST['status'] : 'naik';
$catatan         = trim($_POST['catatan'] ?? '');

if (!$isAdmin) { setFlash('gagal', 'Hanya admin yang dapat mengelola kenaikan kelas.'); redirect('../../pages/data_siswa.php?tab=kenaikan'); }
if ($siswaId === 0 || $kelasAsalId === 0) {
    setFlash('gagal', 'Siswa dan kelas asal wajib diisi.');
    redirect('../../pages/data_siswa.php?tab=kenaikan');
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE kenaikan_kelas SET siswa_id=?, kelas_asal_id=?, kelas_tujuan_id=?, tahun_ajaran=?, status=?, catatan=? WHERE id=?');
        $stmt->execute([$siswaId, $kelasAsalId, $kelasTujuanId, $tahunAjaran, $status, $catatan, $id]);
        setFlash('sukses', 'Data kenaikan berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO kenaikan_kelas (siswa_id, kelas_asal_id, kelas_tujuan_id, tahun_ajaran, status, catatan, dibuat_oleh) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$siswaId, $kelasAsalId, $kelasTujuanId, $tahunAjaran, $status, $catatan, $user['id']]);

        // Jika status = 'naik' atau 'lulus', pindahkan kelas siswa
        if (in_array($status, ['naik','lulus']) && $kelasTujuanId) {
            $upd = $pdo->prepare('UPDATE siswa SET kelas_id=?, status=? WHERE id=?');
            $statusSiswa = $status === 'lulus' ? 'lulus' : 'aktif';
            $upd->execute([$kelasTujuanId, $statusSiswa, $siswaId]);
        }
        setFlash('sukses', 'Data kenaikan berhasil disimpan.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan data kenaikan.');
}
redirect('../../pages/data_siswa.php?tab=kenaikan');