<?php
/**
 * Export Laporan Kinerja Harian ke Excel / CSV
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user    = currentUser();
$isAdmin = isAdmin();

$guruId  = $isAdmin ? (int)($_GET['guru_id'] ?? $user['id']) : $user['id'];
$bulan   = (int)($_GET['bulan'] ?? date('m'));
$tahun   = (int)($_GET['tahun'] ?? date('Y'));

$bulan = max(1, min(12, $bulan));
$tahun = max(2020, min(2035, $tahun));

// Data Guru
$stmtGuru = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch();
if (!$guru) {
    die("Guru tidak ditemukan");
}

// Nama Bulan
$namaBulanIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$bulanTeks = $namaBulanIndo[$bulan];

// Ambil jadwal mengajar mingguan
$stmtJadwal = $pdo->prepare("SELECT j.*, m.nama_mapel, k.nama_kelas
                             FROM jadwal_mengajar j
                             JOIN mata_pelajaran m ON m.id = j.mapel_id
                             JOIN kelas k ON k.id = j.kelas_id
                             WHERE j.guru_id = ?
                             ORDER BY FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai");
$stmtJadwal->execute([$guruId]);
$jadwalMingguan = $stmtJadwal->fetchAll();

$jadwalPerHari = [];
foreach ($jadwalMingguan as $j) {
    $jadwalPerHari[$j['hari']][] = $j;
}

// Ambil kegiatan manual
$stmtManual = $pdo->prepare("SELECT * FROM kinerja_harian_kegiatan
                             WHERE guru_id = ? 
                               AND MONTH(tanggal) = ? 
                               AND YEAR(tanggal) = ?
                             ORDER BY tanggal ASC, id ASC");
$stmtManual->execute([$guruId, $bulan, $tahun]);
$manualKegiatan = $stmtManual->fetchAll();

$manualPerTanggal = [];
foreach ($manualKegiatan as $mk) {
    $manualPerTanggal[$mk['tanggal']][] = $mk;
}

// Map nama hari PHP ke Indonesia
$hariEnToId = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];

$jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
$rows = [];
$no = 1;

for ($d = 1; $d <= $jumlahHari; $d++) {
    $tanggalStr = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
    $hariEn = date('l', strtotime($tanggalStr));
    $hariId = $hariEnToId[$hariEn] ?? '';

    if ($hariId === 'Minggu') {
        continue;
    }

    if (!empty($jadwalPerHari[$hariId])) {
        $kelasUnik = [];
        $mapelUnik = [];
        $totalSesi = count($jadwalPerHari[$hariId]);

        foreach ($jadwalPerHari[$hariId] as $itemJadwal) {
            $kelasUnik[] = $itemJadwal['nama_kelas'];
            $mapelUnik[] = $itemJadwal['nama_mapel'];
        }
        $kelasUnik = array_unique($kelasUnik);
        $mapelUnik = array_unique($mapelUnik);

        $teksKegiatan = "Melaksanakan KBM " . implode(', ', $mapelUnik) . " di Kelas " . implode(' & ', $kelasUnik);
        $rows[] = [
            'no' => $no++,
            'tanggal' => date('d/m/Y', strtotime($tanggalStr)),
            'hari' => $hariId,
            'kegiatan' => $teksKegiatan,
            'volume' => $totalSesi . ' Kegiatan (' . ($totalSesi * 2) . ' JP)',
            'keterangan' => 'Jurnal KBM & Presensi Siswa'
        ];
    }

    if (!empty($manualPerTanggal[$tanggalStr])) {
        foreach ($manualPerTanggal[$tanggalStr] as $mk) {
            $rows[] = [
                'no' => $no++,
                'tanggal' => date('d/m/Y', strtotime($tanggalStr)),
                'hari' => $hariId,
                'kegiatan' => $mk['kegiatan'],
                'volume' => $mk['volume'],
                'keterangan' => $mk['keterangan'] ?: 'Tugas Tambahan / Dinas'
            ];
        }
    }
}

// Kirim header download CSV/Excel
$filename = "Laporan_Kinerja_Harian_" . preg_replace('/[^a-zA-Z0-9]/', '_', $guru['nama_lengkap']) . "_{$bulan}_{$tahun}.csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output UTF-8 BOM agar Microsoft Excel membuka karakter dengan benar
echo "\xEF\xBB\xBF";

$fp = fopen('php://output', 'w');

fputcsv($fp, ['LAPORAN KINERJA HARIAN (LKH)']);
fputcsv($fp, ['SEKOLAH', 'SMA NEGERI 1 BUMIAYU']);
fputcsv($fp, ['BULAN', strtoupper($bulanTeks) . ' ' . $tahun]);
fputcsv($fp, ['GURU', $guru['nama_lengkap']]);
fputcsv($fp, ['NIP', $guru['nip'] ?: '-']);
fputcsv($fp, []);

fputcsv($fp, ['No', 'Hari', 'Tanggal', 'Uraian Kegiatan', 'Volume / Beban Kerja', 'Keterangan']);

foreach ($rows as $r) {
    fputcsv($fp, [
        $r['no'],
        $r['hari'],
        $r['tanggal'],
        $r['kegiatan'],
        $r['volume'],
        $r['keterangan']
    ]);
}

fputcsv($fp, []);
fputcsv($fp, ['', '', '', 'Brebes, ' . date('t', strtotime("$tahun-$bulan-01")) . ' ' . $bulanTeks . ' ' . $tahun]);
fputcsv($fp, ['', 'Pejabat Penilai,', '', '', 'Pegawai yang Dinilai,']);
fputcsv($fp, []);
fputcsv($fp, []);
fputcsv($fp, ['', 'Dr. IHDI AMIN, M.Pd', '', '', $guru['nama_lengkap']]);
fputcsv($fp, ['', 'NIP. 197210071998021002', '', '', 'NIP. ' . ($guru['nip'] ?: '-')]);

fclose($fp);
exit;
