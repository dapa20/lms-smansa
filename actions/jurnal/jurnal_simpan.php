<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../../pages/kelas_jadwal.php?tab=jurnal'); }

$id          = (int)($_POST['id'] ?? 0);
$jadwalId    = (int)($_POST['jadwal_id'] ?? 0);
$tanggal     = $_POST['tanggal'] ?? date('Y-m-d');
$materi      = trim($_POST['materi'] ?? '');
$kegiatan    = trim($_POST['kegiatan'] ?? '');
$catatan     = trim($_POST['catatan'] ?? '');
$jamMulai    = $_POST['jam_mulai'] ?? '';
$jamSelesai  = $_POST['jam_selesai'] ?? '';
$sh          = (int)($_POST['siswa_hadir'] ?? 0);
$si          = (int)($_POST['siswa_izin'] ?? 0);
$ss          = (int)($_POST['siswa_sakit'] ?? 0);
$sa          = (int)($_POST['siswa_alpa'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = date('Y-m-d');
if ($jadwalId === 0 || $materi === '') {
    setFlash('gagal', 'Jadwal dan materi wajib diisi.');
    redirect('../../pages/kelas_jadwal.php?tab=jurnal');
}

// Ambil info jadwal
$stmtJ = $pdo->prepare('SELECT * FROM jadwal_mengajar WHERE id=?');
$stmtJ->execute([$jadwalId]);
$jadwal = $stmtJ->fetch();
if (!$jadwal) {
    setFlash('gagal', 'Jadwal tidak ditemukan.');
    redirect('../../pages/kelas_jadwal.php?tab=jurnal');
}

// Validasi: guru hanya boleh mengisi jadwal miliknya sendiri
if (!$isAdmin && (int)$jadwal['guru_id'] !== (int)$user['id']) {
    setFlash('gagal', 'Anda tidak berwenang mengisi jurnal untuk jadwal ini.');
    redirect('../../pages/kelas_jadwal.php?tab=jurnal');
}

// Default jam dari jadwal
if ($jamMulai === '') $jamMulai = $jadwal['jam_mulai'];
if ($jamSelesai === '') $jamSelesai = $jadwal['jam_selesai'];

try {
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE jurnal_mengajar SET jadwal_id=?, tanggal=?, jam_mulai=?, jam_selesai=?, materi=?, kegiatan=?, catatan=?, siswa_hadir=?, siswa_izin=?, siswa_sakit=?, siswa_alpa=? WHERE id=?');
        $stmt->execute([$jadwalId, $tanggal, $jamMulai, $jamSelesai, $materi, $kegiatan, $catatan, $sh, $si, $ss, $sa, $id]);
        setFlash('sukses', 'Jurnal mengajar berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO jurnal_mengajar (jadwal_id, guru_id, kelas_id, mapel_id, tanggal, jam_mulai, jam_selesai, materi, kegiatan, catatan, siswa_hadir, siswa_izin, siswa_sakit, siswa_alpa) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$jadwalId, $jadwal['guru_id'], $jadwal['kelas_id'], $jadwal['mapel_id'], $tanggal, $jamMulai, $jamSelesai, $materi, $kegiatan, $catatan, $sh, $si, $ss, $sa]);
        setFlash('sukses', 'Jurnal mengajar berhasil ditambahkan.');
    }
} catch (PDOException $e) {
    setFlash('gagal', 'Gagal menyimpan jurnal.');
}
redirect('../../pages/kelas_jadwal.php?tab=jurnal');