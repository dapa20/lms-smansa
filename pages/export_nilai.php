<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$kelasId     = (int)($_GET['kelas_id'] ?? 0);
$mapelId     = (int)($_GET['mapel_id'] ?? 0);
$semester    = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? '2024/2025';
$format      = $_GET['format'] ?? 'excel'; // 'excel' (Formatted .xls) atau 'csv' (Semicolon .csv)

if ($kelasId === 0 || $mapelId === 0) {
    redirect('rekap_nilai.php');
}

if (!isAdmin()) {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ?');
    $stmt->execute([$_SESSION['user_id'], $kelasId, $mapelId]);
    if ((int)$stmt->fetch()['c'] === 0) {
        redirect('rekap_nilai.php');
    }
}

$stmtInfo = $pdo->prepare('SELECT nama_kelas FROM kelas WHERE id = ?');
$stmtInfo->execute([$kelasId]);
$namaKelas = $stmtInfo->fetch()['nama_kelas'] ?? 'Kelas';

$stmtInfo = $pdo->prepare('SELECT nama_mapel FROM mata_pelajaran WHERE id = ?');
$stmtInfo->execute([$mapelId]);
$namaMapel = $stmtInfo->fetch()['nama_mapel'] ?? 'Mapel';

$stmt = $pdo->prepare("SELECT s.nis, s.nisn, s.nama_lengkap,
                               n.tugas_1, n.tugas_2, n.tugas_3, n.uts, n.uas, n.catatan
                        FROM siswa s
                        LEFT JOIN nilai n ON n.siswa_id = s.id AND n.mapel_id = ? AND n.semester = ? AND n.tahun_ajaran = ?
                        WHERE s.kelas_id = ? AND s.status = 'aktif'
                        ORDER BY s.nama_lengkap");
$stmt->execute([$mapelId, $semester, $tahunAjaran, $kelasId]);
$data = $stmt->fetchAll();

// Hitung nilai akhir & statistik
$nilaiAkhirList = [];
foreach ($data as &$row) {
    $row['nilai_akhir'] = hitungNilaiAkhir($row['tugas_1'], $row['tugas_2'], $row['tugas_3'], $row['uts'], $row['uas']);
    if ($row['nilai_akhir'] !== null) {
        $nilaiAkhirList[] = $row['nilai_akhir'];
    }
}
unset($row);

$rataKelas = count($nilaiAkhirList) ? round(array_sum($nilaiAkhirList) / count($nilaiAkhirList), 1) : '-';

// ---------------------------------------------------------------------
// OPTION 1: MODE CSV RAPI (Semicolon ; Delimiter + UTF-8 BOM)
// ---------------------------------------------------------------------
if ($format === 'csv') {
    $namaFile = 'Rekap_Nilai_' . preg_replace('/[^A-Za-z0-9_]/', '_', "{$namaKelas}_{$namaMapel}_{$semester}") . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputs($out, "sep=;\n");       // Explicit delimiter declaration for Excel
    
    fputcsv($out, ['REKAP NILAI SISWA', $namaMapel, $namaKelas, "Semester $semester $tahunAjaran"], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['NIS', 'NISN', 'Nama Lengkap', 'Tugas 1', 'Tugas 2', 'Tugas 3', 'UTS', 'UAS', 'Nilai Akhir', 'Catatan'], ';');
    
    foreach ($data as $row) {
        fputcsv($out, [
            $row['nis'], $row['nisn'] ?? '', $row['nama_lengkap'],
            $row['tugas_1'] ?? '', $row['tugas_2'] ?? '', $row['tugas_3'] ?? '',
            $row['uts'] ?? '', $row['uas'] ?? '', $row['nilai_akhir'] ?? '', $row['catatan'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------------
// OPTION 2: MODE FORMATTED EXCEL (.XLS) - LENGKAP WARNA & BORDER TABEL
// ---------------------------------------------------------------------
$namaFile = 'Rekap_Nilai_' . preg_replace('/[^A-Za-z0-9_]/', '_', "{$namaKelas}_{$namaMapel}_{$semester}") . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Cache-Control: max-age=0');

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'Segoe UI', Calibri, Arial, sans-serif; font-size: 11pt; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #94a3b8; padding: 8px 12px; vertical-align: middle; }
    .title-row { background-color: #107c41; color: #ffffff; font-size: 14pt; font-weight: bold; text-align: left; height: 35px; }
    .meta-row { background-color: #f1f5f9; font-weight: bold; color: #334155; font-size: 10pt; }
    .header-row th { background-color: #107c41; color: #ffffff; font-weight: bold; text-align: center; font-size: 10.5pt; height: 30px; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .number-cell { mso-number-format: "\@"; } /* Format NIS/NISN sebagai Teks agar 0 depan tidak hilang */
    .score-cell { font-weight: bold; background-color: #dcfce7; color: #15803d; text-align: center; font-size: 11pt; }
    .summary-row td { background-color: #e2e8f0; font-weight: bold; color: #1e293b; height: 30px; }
    .even-row { background-color: #f8fafc; }
</style>
</head>
<body>
<table>
    <tr class="title-row">
        <td colspan="10" style="padding-left: 12px;">REKAP NILAI SISWA — SMAN 1 BUMIAYU</td>
    </tr>
    <tr class="meta-row">
        <td colspan="3">Mata Pelajaran: <?= h($namaMapel) ?></td>
        <td colspan="3">Kelas: <?= h($namaKelas) ?></td>
        <td colspan="4">Semester: <?= h($semester) ?> (TA <?= h($tahunAjaran) ?>)</td>
    </tr>
    <tr><td colspan="10" style="border:none; height:10px;"></td></tr>
    <tr class="header-row">
        <th style="width: 100px;">NIS</th>
        <th style="width: 120px;">NISN</th>
        <th style="width: 250px;">Nama Lengkap</th>
        <th style="width: 70px;">Tugas 1</th>
        <th style="width: 70px;">Tugas 2</th>
        <th style="width: 70px;">Tugas 3</th>
        <th style="width: 70px;">UTS</th>
        <th style="width: 70px;">UAS</th>
        <th style="width: 100px;">Nilai Akhir</th>
        <th style="width: 200px;">Catatan</th>
    </tr>
    <?php foreach ($data as $idx => $row): ?>
    <tr class="<?= $idx % 2 === 0 ? '' : 'even-row' ?>">
        <td class="number-cell text-center"><?= h($row['nis']) ?></td>
        <td class="number-cell text-center"><?= h($row['nisn'] ?? '-') ?></td>
        <td class="text-left" style="font-weight:bold; color:#0f172a;"><?= h($row['nama_lengkap']) ?></td>
        <td class="text-center"><?= $row['tugas_1'] ?? '-' ?></td>
        <td class="text-center"><?= $row['tugas_2'] ?? '-' ?></td>
        <td class="text-center"><?= $row['tugas_3'] ?? '-' ?></td>
        <td class="text-center"><?= $row['uts'] ?? '-' ?></td>
        <td class="text-center"><?= $row['uas'] ?? '-' ?></td>
        <td class="score-cell"><?= $row['nilai_akhir'] ?? '-' ?></td>
        <td class="text-left" style="font-style:italic; color:#64748b;"><?= h($row['catatan'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="summary-row">
        <td colspan="8" class="text-right">RATA-RATA KELAS:</td>
        <td class="text-center" style="font-size:12pt; color:#094cb2;"><?= $rataKelas ?></td>
        <td></td>
    </tr>
</table>
</body>
</html>
<?php
exit;
