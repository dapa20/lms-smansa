<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/rekap_nilai.php?tab=indikator'); }

$id              = (int)($_POST['id'] ?? 0);
$mapelId         = (int)($_POST['mapel_id'] ?? 0);
$kelasTingkat    = in_array($_POST['kelas_tingkat'] ?? '', ['X','XI','XII']) ? $_POST['kelas_tingkat'] : 'X';
$semester        = in_array($_POST['semester'] ?? '', ['Ganjil','Genap']) ? $_POST['semester'] : 'Ganjil';
$kodeIndikator   = trim($_POST['kode_indikator'] ?? '');
$deskripsi       = trim($_POST['deskripsi'] ?? '');
$urutan          = (int)($_POST['urutan'] ?? 1);

if ($mapelId === 0 || $kodeIndikator === '' || $deskripsi === '') {
    setFlash('gagal', 'Mata pelajaran, kode, dan deskripsi wajib diisi.');
    redirect('../../pages/rekap_nilai.php?tab=indikator');
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE indikator_nilai SET mapel_id=?, kelas_tingkat=?, semester=?, kode_indikator=?, deskripsi=?, urutan=? WHERE id=?');
        $stmt->execute([$mapelId, $kelasTingkat, $semester, $kodeIndikator, $deskripsi, $urutan, $id]);
        setFlash('sukses', 'Indikator nilai berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO indikator_nilai (mapel_id, kelas_tingkat, semester, kode_indikator, deskripsi, urutan) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$mapelId, $kelasTingkat, $semester, $kodeIndikator, $deskripsi, $urutan]);
        setFlash('sukses', 'Indikator nilai berhasil ditambahkan.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan indikator nilai.');
}
redirect('../../pages/rekap_nilai.php?tab=indikator&mapel_id='.$mapelId);