<?php
/**
 * =====================================================================
 * EXPORT TO GOOGLE SHEETS
 * =====================================================================
 * Endpoint ini dipanggil ketika guru mengklik tombol "Buka di Google Sheets".
 * Proses:
 *   1. Ambil parameter filter dari URL
 *   2. Query data nilai dari database
 *   3. Buat Google Spreadsheet baru via API (judul + data sudah terisi)
 *   4. Redirect langsung ke URL spreadsheet yang baru dibuat
 * =====================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../../includes/google_sheets_service.php';

// -----------------------------------------------------------------------
// Fungsi hitung nilai akhir (sama seperti di rekap_nilai.php)
// -----------------------------------------------------------------------
if (!function_exists('hitungNilaiAkhir')) {
    function hitungNilaiAkhir($t1, $t2, $t3, $uts, $uas): ?float
    {
        if ($t1 === null && $t2 === null && $t3 === null && $uts === null && $uas === null) {
            return null;
        }
        $t1  = (float)($t1 ?? 0);
        $t2  = (float)($t2 ?? 0);
        $t3  = (float)($t3 ?? 0);
        $uts = (float)($uts ?? 0);
        $uas = (float)($uas ?? 0);
        $rataRataTugas = ($t1 + $t2 + $t3) / 3;
        return round($rataRataTugas * 0.3 + $uts * 0.3 + $uas * 0.4, 1);
    }
}

// -----------------------------------------------------------------------
// Ambil parameter dari URL
// -----------------------------------------------------------------------
$kelasId     = (int)($_GET['kelas_id'] ?? 0);
$mapelId     = (int)($_GET['mapel_id'] ?? 0);
$semester    = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? '2024/2025';

if (!$kelasId || !$mapelId) {
    header('Location: ../../pages/rekap_nilai.php');
    exit;
}

$user    = currentUser();
$isAdmin = isAdmin();

// -----------------------------------------------------------------------
// Proteksi: guru tidak boleh akses kelas/mapel di luar yang ia ajar
// -----------------------------------------------------------------------
if (!$isAdmin) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ?");
    $chk->execute([$user['id'], $kelasId, $mapelId]);
    if ($chk->fetchColumn() == 0) {
        header('Location: ../../pages/rekap_nilai.php');
        exit;
    }
}

// -----------------------------------------------------------------------
// Ambil nama kelas & mapel untuk judul spreadsheet
// -----------------------------------------------------------------------
$stmtKelas = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
$stmtKelas->execute([$kelasId]);
$namaKelas = $stmtKelas->fetchColumn() ?: 'Kelas';

$stmtMapel = $pdo->prepare("SELECT nama_mapel FROM mata_pelajaran WHERE id = ?");
$stmtMapel->execute([$mapelId]);
$namaMapel = $stmtMapel->fetchColumn() ?: 'Mapel';

// -----------------------------------------------------------------------
// Ambil data nilai siswa dari database
// -----------------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT s.nama_lengkap, s.nis, s.nisn,
            n.tugas_1, n.tugas_2, n.tugas_3, n.uts, n.uas, n.catatan
     FROM siswa s
     LEFT JOIN nilai n ON n.siswa_id = s.id
         AND n.mapel_id = ? AND n.semester = ? AND n.tahun_ajaran = ?
     WHERE s.kelas_id = ? AND s.status = 'aktif'
     ORDER BY s.nama_lengkap"
);
$stmt->execute([$mapelId, $semester, $tahunAjaran, $kelasId]);
$daftarSiswa = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung nilai akhir tiap siswa
$rows = [];
foreach ($daftarSiswa as $s) {
    $nilaiAkhir = hitungNilaiAkhir($s['tugas_1'], $s['tugas_2'], $s['tugas_3'], $s['uts'], $s['uas']);
    $rows[] = [
        $s['nis']           ?? '-',
        $s['nisn']          ?? '-',
        $s['nama_lengkap'],
        $s['tugas_1']       !== null ? (float)$s['tugas_1']  : '-',
        $s['tugas_2']       !== null ? (float)$s['tugas_2']  : '-',
        $s['tugas_3']       !== null ? (float)$s['tugas_3']  : '-',
        $s['uts']           !== null ? (float)$s['uts']      : '-',
        $s['uas']           !== null ? (float)$s['uas']      : '-',
        $nilaiAkhir         !== null ? $nilaiAkhir           : '-',
        $s['catatan']       ?? '-',
    ];
}

// -----------------------------------------------------------------------
// Susun judul spreadsheet
// -----------------------------------------------------------------------
$title = "Rekap Nilai - {$namaMapel} - {$namaKelas} - Semester {$semester} {$tahunAjaran}";

$headers = [
    'NIS', 'NISN', 'Nama Lengkap',
    'Tugas 1', 'Tugas 2', 'Tugas 3',
    'UTS', 'UAS', 'Nilai Akhir', 'Catatan',
];

// -----------------------------------------------------------------------
// Buat spreadsheet via Google Sheets API
// -----------------------------------------------------------------------
try {
    $service = new GoogleSheetsService();
    $spreadsheetUrl = $service->createSpreadsheet($title, $headers, $rows, 'Sheet1');

    // Redirect langsung ke spreadsheet yang baru dibuat
    header("Location: {$spreadsheetUrl}");
    exit;

} catch (RuntimeException $e) {
    // Service account belum dikonfigurasi — tampilkan pesan jelas
    $pesan = urlencode('❌ Google Sheets belum dikonfigurasi: ' . $e->getMessage() . ' — Ikuti panduan di config/google_sheets.php');
    header("Location: ../../pages/rekap_nilai.php?kelas_id={$kelasId}&mapel_id={$mapelId}&semester=" . urlencode($semester) . "&tahun_ajaran=" . urlencode($tahunAjaran) . "&error={$pesan}");
    exit;

} catch (Exception $e) {
    $pesan = urlencode('❌ Gagal membuat Google Spreadsheet: ' . $e->getMessage());
    header("Location: ../../pages/rekap_nilai.php?kelas_id={$kelasId}&mapel_id={$mapelId}&semester=" . urlencode($semester) . "&tahun_ajaran=" . urlencode($tahunAjaran) . "&error={$pesan}");
    exit;
}
