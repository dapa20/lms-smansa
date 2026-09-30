<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/data_siswa.php?tab=struktur'); }

$id       = (int)($_POST['id'] ?? 0);
$kelasId  = (int)($_POST['kelas_id'] ?? 0);
$siswaId  = (int)($_POST['siswa_id'] ?? 0);
$jabatan  = trim($_POST['jabatan'] ?? '');
$urutan   = (int)($_POST['urutan'] ?? 1);

if (!$isAdmin) { setFlash('gagal', 'Hanya admin yang dapat mengelola struktur kelas.'); redirect('../../pages/data_siswa.php?tab=struktur'); }
if ($kelasId === 0 || $siswaId === 0 || $jabatan === '') {
    setFlash('gagal', 'Kelas, siswa, dan jabatan wajib diisi.');
    redirect('../../pages/data_siswa.php?tab=struktur');
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE struktur_kelas SET siswa_id=?, jabatan=?, urutan=? WHERE id=? AND kelas_id=?');
        $stmt->execute([$siswaId, $jabatan, $urutan, $id, $kelasId]);
        setFlash('sukses', 'Data struktur kelas berhasil diperbarui.');
    } else {
        // Cek apakah siswa sudah punya jabatan di kelas ini
        $cek = $pdo->prepare('SELECT id FROM struktur_kelas WHERE kelas_id=? AND siswa_id=?');
        $cek->execute([$kelasId, $siswaId]);
        if ($cek->fetch()) {
            setFlash('gagal', 'Siswa tersebut sudah memiliki jabatan di kelas ini.');
            redirect('../../pages/data_siswa.php?tab=struktur&kelas_id='.$kelasId);
        }
        $stmt = $pdo->prepare('INSERT INTO struktur_kelas (kelas_id, siswa_id, jabatan, urutan) VALUES (?,?,?,?)');
        $stmt->execute([$kelasId, $siswaId, $jabatan, $urutan]);
        setFlash('sukses', 'Anggota struktur kelas berhasil ditambahkan.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan: '.$e->getMessage());
}
redirect('../../pages/data_siswa.php?tab=struktur&kelas_id='.$kelasId);