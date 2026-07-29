<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../pages/rekap_nilai.php');
}

$siswaId     = (int)($_POST['siswa_id'] ?? 0);
$mapelId     = (int)($_POST['mapel_id'] ?? 0);
$kelasId     = (int)($_POST['kelas_id'] ?? 0);
$semester    = in_array($_POST['semester'] ?? '', ['Ganjil', 'Genap']) ? $_POST['semester'] : 'Ganjil';
$tahunAjaran = trim($_POST['tahun_ajaran'] ?? '2024/2025');
$catatan     = trim($_POST['catatan'] ?? '');

// Helper kecil: string kosong -> NULL, selain itu di-clamp 0-100.
$bersihkan = function ($val) {
    if ($val === '' || $val === null) return null;
    return max(0, min(100, (float)$val));
};
$tugas1 = $bersihkan($_POST['tugas_1'] ?? '');
$tugas2 = $bersihkan($_POST['tugas_2'] ?? '');
$tugas3 = $bersihkan($_POST['tugas_3'] ?? '');
$uts    = $bersihkan($_POST['uts'] ?? '');
$uas    = $bersihkan($_POST['uas'] ?? '');

if ($siswaId === 0 || $mapelId === 0 || $kelasId === 0) {
    redirect('../../pages/rekap_nilai.php');
}

if (!isAdmin()) {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ?');
    $stmt->execute([$_SESSION['user_id'], $kelasId, $mapelId]);
    if ((int)$stmt->fetch()['c'] === 0) {
        setFlash('gagal', 'Anda tidak mengajar mata pelajaran ini di kelas tersebut.');
        redirect('../../pages/rekap_nilai.php');
    }
}

$stmt = $pdo->prepare("INSERT INTO nilai (siswa_id, mapel_id, kelas_id, semester, tahun_ajaran, tugas_1, tugas_2, tugas_3, uts, uas, catatan)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE
                            tugas_1 = VALUES(tugas_1), tugas_2 = VALUES(tugas_2), tugas_3 = VALUES(tugas_3),
                            uts = VALUES(uts), uas = VALUES(uas), catatan = VALUES(catatan)");
$stmt->execute([$siswaId, $mapelId, $kelasId, $semester, $tahunAjaran, $tugas1, $tugas2, $tugas3, $uts, $uas, $catatan ?: null]);

setFlash('sukses', 'Nilai berhasil disimpan.');
redirect('../../pages/rekap_nilai.php?' . http_build_query(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]));
