<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/rekap_nilai.php?tab=prestasi'); }

$id            = (int)($_POST['id'] ?? 0);
$siswaId       = (int)($_POST['siswa_id'] ?? 0);
$kelasId       = (int)($_POST['kelas_id'] ?? 0);
$jenisPrestasi = in_array($_POST['jenis_prestasi'] ?? '', ['akademik','non-akademik','olahraga','seni','lainnya']) ? $_POST['jenis_prestasi'] : 'akademik';
$namaPrestasi  = trim($_POST['nama_prestasi'] ?? '');
$tingkat       = in_array($_POST['tingkat'] ?? '', ['Sekolah','Kecamatan','Kabupaten','Provinsi','Nasional','Internasional']) ? $_POST['tingkat'] : 'Sekolah';
$peringkat     = trim($_POST['peringkat'] ?? '');
$tahun         = (int)($_POST['tahun'] ?? date('Y'));
$keterangan    = trim($_POST['keterangan'] ?? '');

if ($siswaId === 0 || $kelasId === 0 || $namaPrestasi === '') {
    setFlash('gagal', 'Siswa, kelas, dan nama prestasi wajib diisi.');
    redirect('../../pages/rekap_nilai.php?tab=prestasi');
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE prestasi_siswa SET siswa_id=?, kelas_id=?, jenis_prestasi=?, nama_prestasi=?, tingkat=?, peringkat=?, tahun=?, keterangan=? WHERE id=?');
        $stmt->execute([$siswaId, $kelasId, $jenisPrestasi, $namaPrestasi, $tingkat, $peringkat, $tahun, $keterangan, $id]);
        setFlash('sukses', 'Prestasi siswa berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO prestasi_siswa (siswa_id, kelas_id, jenis_prestasi, nama_prestasi, tingkat, peringkat, tahun, keterangan, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$siswaId, $kelasId, $jenisPrestasi, $namaPrestasi, $tingkat, $peringkat, $tahun, $keterangan, $user['id']]);
        setFlash('sukses', 'Prestasi siswa berhasil ditambahkan.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan prestasi siswa.');
}
redirect('../../pages/rekap_nilai.php?tab=prestasi&kelas_id='.$kelasId);