<?php
/**
 * =====================================================================
 * HALAMAN LAPORAN KINERJA HARIAN (LKH) GURU
 * =====================================================================
 * Menampilkan rekapan kegiatan kinerja harian guru per bulan secara
 * profesional, dilengkapi filter periode, ringkasan capaian, ekspor Excel,
 * serta mode cetak standar format kepegawaian sekolah.
 * =====================================================================
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'Laporan Kinerja Harian';
$currentPage = 'kinerja_harian';
$user        = currentUser();
$isAdmin     = isAdmin();

// 1. Ambil Parameter Filter
$bulanDipilih = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahunDipilih = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$bulanDipilih = max(1, min(12, $bulanDipilih));
$tahunDipilih = max(2020, min(2035, $tahunDipilih));

// Daftar Guru (untuk admin memilih guru)
$daftarGuru = [];
if ($isAdmin) {
    $daftarGuru = $pdo->query("SELECT id, nama_lengkap, nip FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC")->fetchAll();
    $guruId = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : ($daftarGuru[0]['id'] ?? $user['id']);
} else {
    $guruId = (int)$user['id'];
}

// Ambil Data Guru Terpilih
$stmtGuru = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtGuru->execute([$guruId]);
$guruTerpilih = $stmtGuru->fetch() ?: $user;

// Data Pejabat Penilai (Default Kepala Sekolah)
$pejabatPenilai = [
    'nama'     => $_GET['pejabat_nama'] ?? 'Dr. IHDI AMIN, M.Pd',
    'nip'      => $_GET['pejabat_nip'] ?? '197210071998021002',
    'pangkat'  => $_GET['pejabat_pangkat'] ?? 'Pembina Tk. I, IV/b',
    'jabatan'  => $_GET['pejabat_jabatan'] ?? 'Kepala Sekolah',
    'unit'     => 'SMA NEGERI 1 BUMIAYU',
];

// Data Pegawai yang Dinilai
$pegawaiDinilai = [
    'nama'     => $guruTerpilih['nama_lengkap'],
    'nip'      => $guruTerpilih['nip'] ?: '-',
    'pangkat'  => $guruTerpilih['pangkat_golongan'] ?? 'Penata Muda, III/a',
    'jabatan'  => !empty($guruTerpilih['mapel_keahlian']) ? 'Guru ' . $guruTerpilih['mapel_keahlian'] : 'Guru',
    'unit'     => 'SMA NEGERI 1 BUMIAYU',
];

// Nama Bulan Bahasa Indonesia
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$bulanTeks = $namaBulan[$bulanDipilih];

// 2. Ambil Jadwal Mengajar Mingguan Guru
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

// 3. Ambil Kegiatan Tambahan / Manual
$stmtManual = $pdo->prepare("SELECT * FROM kinerja_harian_kegiatan
                             WHERE guru_id = ? 
                               AND MONTH(tanggal) = ? 
                               AND YEAR(tanggal) = ?
                             ORDER BY tanggal DESC, id DESC");
$stmtManual->execute([$guruId, $bulanDipilih, $tahunDipilih]);
$kegiatanManualList = $stmtManual->fetchAll();

$manualPerTanggal = [];
foreach ($kegiatanManualList as $km) {
    $manualPerTanggal[$km['tanggal']][] = $km;
}

// 4. Susun Daftar Kegiatan Harian di Bulan Tersebut
$hariEnToId = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];

$jumlahHariDalamBulan = cal_days_in_month(CAL_GREGORIAN, $bulanDipilih, $tahunDipilih);
$daftarKegiatan = [];
$totalHariEfektif = 0;
$totalKegiatan = 0;
$totalJP = 0;

for ($d = $jumlahHariDalamBulan; $d >= 1; $d--) {
    $tglStr = sprintf('%04d-%02d-%02d', $tahunDipilih, $bulanDipilih, $d);
    $hariEn = date('l', strtotime($tglStr));
    $hariId = $hariEnToId[$hariEn] ?? '';

    // Lewatkan hari Minggu
    if ($hariId === 'Minggu') {
        continue;
    }

    $adaKegiatanHariIni = false;

    // A. Kegiatan KBM dari Jadwal
    if (!empty($jadwalPerHari[$hariId])) {
        $adaKegiatanHariIni = true;
        $sesiCount = count($jadwalPerHari[$hariId]);
        $totalKegiatan += $sesiCount;
        $totalJP += ($sesiCount * 2);

        $kelasUnik = [];
        $mapelUnik = [];
        foreach ($jadwalPerHari[$hariId] as $itemJ) {
            $kelasUnik[] = $itemJ['nama_kelas'];
            $mapelUnik[] = $itemJ['nama_mapel'];
        }
        $kelasUnik = array_unique($kelasUnik);
        $mapelUnik = array_unique($mapelUnik);

        $uraian = "Mengajar di Kelas " . implode(' & ', $kelasUnik) . " (" . implode(', ', $mapelUnik) . ")";
        
        $daftarKegiatan[] = [
            'id'         => null,
            'is_manual'  => false,
            'tanggal'    => $tglStr,
            'hari'       => $hariId,
            'tgl_format' => date('d/m/Y', strtotime($tglStr)),
            'kegiatan'   => $uraian,
            'volume'     => $sesiCount . " Kegiatan",
            'detail_vol' => ($sesiCount * 2) . " JP",
            'keterangan' => 'KBM Reguler & Absensi Kelas',
        ];
    }

    // B. Kegiatan Tambahan (Manual)
    if (!empty($manualPerTanggal[$tglStr])) {
        $adaKegiatanHariIni = true;
        foreach ($manualPerTanggal[$tglStr] as $mk) {
            $totalKegiatan++;
            $daftarKegiatan[] = [
                'id'         => $mk['id'],
                'is_manual'  => true,
                'tanggal'    => $tglStr,
                'hari'       => $hariId,
                'tgl_format' => date('d/m/Y', strtotime($tglStr)),
                'kegiatan'   => $mk['kegiatan'],
                'volume'     => $mk['volume'],
                'detail_vol' => '',
                'keterangan' => $mk['keterangan'] ?: 'Tugas Tambahan / Dinas',
            ];
        }
    }

    if ($adaKegiatanHariIni) {
        $totalHariEfektif++;
    }
}

// Logo Sekolah
$sidebarLogo = null;
$logos = glob(__DIR__ . '/../assets/img/logo*');
if (!empty($logos)) {
    $sidebarLogo = APP_URL . '/assets/img/' . basename($logos[0]);
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>

<style>
/* Styling khusus Print Media */
@media print {
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-size: 11pt !important;
    }
    aside, #sidebar, header, nav, #topbar, #sidebar-overlay,
    .no-print, .btn-action, .filter-toolbar, footer {
        display: none !important;
    }
    main {
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
    }
    .print-card {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }
    .print-table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .print-table th, .print-table td {
        border: 1px solid #1f2937 !important;
        padding: 6px 8px !important;
    }
    .page-break {
        page-break-after: always;
    }
}
</style>

<main class="pt-16 md:ml-[280px] min-h-screen bg-slate-50/70 pb-20">
    <div class="p-4 sm:p-6 lg:p-8 max-w-[1280px] mx-auto space-y-6">
        
        <?php renderFlash(); ?>

        <!-- ============================================================== -->
        <!-- HEADER & BREADCRUMB (NO PRINT) -->
        <!-- ============================================================== -->
        <div class="no-print bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-[24px]">description</span>
                    </span>
                    <h1 class="text-xl font-bold text-slate-800 tracking-tight">Laporan Kinerja Harian (LKH)</h1>
                    <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">
                        TP: <?= date('Y') ?>/<?= date('Y')+1 ?> Smt: I (Ganjil)
                    </span>
                </div>
                <p class="text-xs text-slate-500 max-w-2xl">
                    Rekapitulasi aktivitas pembelajaran, beban mengajar, dan tugas kepegawaian bulanan sebagai bukti kinerja resmi.
                </p>
            </div>

            <!-- Jam Digital & Status -->
            <div class="flex items-center gap-3">
                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Waktu Sistem</span>
                    <span id="digital-clock" class="font-mono text-sm font-bold text-slate-700"><?= date('H:i:s') ?> WIB</span>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- METRIC CARDS (RINGKASAN KINERJA) (NO PRINT) -->
        <!-- ============================================================== -->
        <div class="no-print grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Hari Efektif KBM</span>
                    <p class="text-2xl font-bold text-slate-800"><?= $totalHariEfektif ?> Hari</p>
                    <span class="text-[11px] text-emerald-600 font-semibold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">calendar_today</span> Bulan <?= $bulanTeks ?>
                    </span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[26px]">event_available</span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Kegiatan</span>
                    <p class="text-2xl font-bold text-slate-800"><?= $totalKegiatan ?> Kegiatan</p>
                    <span class="text-[11px] text-slate-500 font-medium">KBM &amp; Tugas Tambahan</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[26px]">task_alt</span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Beban Mengajar</span>
                    <p class="text-2xl font-bold text-slate-800"><?= $totalJP ?> JP</p>
                    <span class="text-[11px] text-slate-500 font-medium">Jam Tatap Muka</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[26px]">hourglass_bottom</span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Status Laporan</span>
                    <p class="text-lg font-bold text-emerald-600 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[20px]">verified</span> Siap Dicetak
                    </p>
                    <span class="text-[11px] text-slate-400 font-mono">Format BKD / SKP</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[26px]">approval</span>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- FILTER TOOLBAR CARD (NO PRINT) -->
        <!-- ============================================================== -->
        <div class="no-print bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-600 text-[18px]">tune</span>
                    Filter &amp; Kontrol Laporan
                </h3>
                <span class="text-xs text-slate-400">Pilih periode bulan dan tahun laporan</span>
            </div>

            <form method="GET" action="kinerja_harian.php" class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1">
                    <!-- Bulan -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">Bulan</label>
                        <select name="bulan" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $bulanDipilih ? 'selected' : '' ?>>
                                    <?= $namaBulan[$m] ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Tahun -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">Tahun</label>
                        <select name="tahun" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                            <?php for ($y = date('Y') + 1; $y >= 2024; $y--): ?>
                                <option value="<?= $y ?>" <?= $y === $tahunDipilih ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Pilih Guru (Khusus Admin) -->
                    <?php if ($isAdmin): ?>
                        <div class="space-y-1">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">Pilih Guru</label>
                            <select name="guru_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                                <?php foreach ($daftarGuru as $dg): ?>
                                    <option value="<?= $dg['id'] ?>" <?= (int)$dg['id'] === $guruId ? 'selected' : '' ?>>
                                        <?= h($dg['nama_lengkap']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Button Group -->
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[18px]">search</span>
                        Tampilkan
                    </button>

                    <a href="<?= APP_URL ?>/pages/export_kinerja.php?bulan=<?= $bulanDipilih ?>&tahun=<?= $tahunDipilih ?>&guru_id=<?= $guruId ?>" 
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold rounded-xl transition">
                        <span class="material-symbols-outlined text-[18px] text-emerald-600">table_view</span>
                        Export Excel
                    </a>

                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[18px]">print</span>
                        Cetak Laporan (PDF)
                    </button>

                    <button type="button" onclick="openModalTambah()" class="inline-flex items-center gap-2 px-3.5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition">
                        <span class="material-symbols-outlined text-[18px]">add_circle</span>
                        Tambah Kegiatan
                    </button>
                </div>
            </form>
        </div>

        <!-- ============================================================== -->
        <!-- DOKUMEN RESMI KERTAS LAPORAN KINERJA HARIAN (PRINTABLE) -->
        <!-- ============================================================== -->
        <div class="print-card bg-white p-6 sm:p-10 rounded-2xl border border-slate-200/80 shadow-sm max-w-[1050px] mx-auto space-y-6">
            
            <!-- KOP SURAT RESMI SEKOLAH -->
            <div class="border-b-2 border-slate-800 pb-4 flex items-center justify-between gap-6">
                <div class="w-16 h-16 sm:w-20 sm:h-20 flex-shrink-0 flex items-center justify-center">
                    <?php if ($sidebarLogo): ?>
                        <img src="<?= $sidebarLogo ?>" alt="Logo Sekolah" class="w-full h-full object-contain">
                    <?php else: ?>
                        <span class="material-symbols-outlined text-[48px] text-emerald-700">school</span>
                    <?php endif; ?>
                </div>

                <div class="text-center flex-1 space-y-0.5">
                    <h3 class="text-xs sm:text-sm font-semibold uppercase tracking-wider text-slate-600">PEMERINTAH PROVINSI JAWA TENGAH</h3>
                    <h2 class="text-sm sm:text-base font-bold uppercase tracking-tight text-slate-700">DINAS PENDIDIKAN DAN KEBUDAYAAN</h2>
                    <h1 class="text-lg sm:text-2xl font-black uppercase tracking-tight text-slate-900">SMA NEGERI 1 BUMIAYU</h1>
                    <p class="text-[10px] sm:text-xs text-slate-500">
                        Jalan P. Diponegoro No. 2 Bumiayu, Kab. Brebes, Jawa Tengah 52273 | Telp. (0289) 432123
                    </p>
                </div>

                <div class="w-16 h-16 sm:w-20 sm:h-20 flex-shrink-0 hidden sm:flex items-center justify-center text-slate-300">
                    <span class="material-symbols-outlined text-[48px]">verified_user</span>
                </div>
            </div>

            <!-- JUDUL LAPORAN -->
            <div class="text-center space-y-1 pt-2">
                <h2 class="text-base sm:text-lg font-black uppercase tracking-wide text-slate-900 underline underline-offset-4">
                    LAPORAN KINERJA HARIAN (LKH)
                </h2>
                <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-700">
                    BULAN: <?= strtoupper($bulanTeks) ?> <?= $tahunDipilih ?>
                </p>
            </div>

            <!-- ========================================================== -->
            <!-- TABEL DUA KOLOM: PEJABAT PENILAI & PEGAWAI YANG DINILAI -->
            <!-- ========================================================== -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- I. Pejabat Penilai -->
                <div class="border border-slate-300 rounded-xl overflow-hidden text-xs">
                    <div class="bg-slate-100 font-bold px-3.5 py-2 text-slate-800 border-b border-slate-300 uppercase tracking-wider flex items-center justify-between">
                        <span>I. Pejabat Penilai</span>
                        <button type="button" onclick="openModalPejabat()" class="no-print text-[11px] text-emerald-600 hover:underline font-normal flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">edit</span> Edit
                        </button>
                    </div>
                    <table class="w-full">
                        <tbody class="divide-y divide-slate-200">
                            <tr>
                                <td class="w-8 px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">1</td>
                                <td class="w-32 px-3 py-1.5 font-semibold text-slate-600">NAMA</td>
                                <td class="px-3 py-1.5 font-bold text-slate-800">: <?= h($pejabatPenilai['nama']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">2</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-600">NIP</td>
                                <td class="px-3 py-1.5 font-mono text-slate-800">: <?= h($pejabatPenilai['nip']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">3</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-600">Pangkat / Gol.</td>
                                <td class="px-3 py-1.5 text-slate-800">: <?= h($pejabatPenilai['pangkat']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">4</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-600">Jabatan</td>
                                <td class="px-3 py-1.5 text-slate-800">: <?= h($pejabatPenilai['jabatan']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">5</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-600">Unit Kerja</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-800">: <?= h($pejabatPenilai['unit']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- II. Pegawai Negeri Sipil yang Dinilai -->
                <div class="border border-slate-300 rounded-xl overflow-hidden text-xs">
                    <div class="bg-slate-100 font-bold px-3.5 py-2 text-slate-800 border-b border-slate-300 uppercase tracking-wider">
                        II. Pegawai yang Dinilai
                    </div>
                    <table class="w-full">
                        <tbody class="divide-y divide-slate-200">
                            <tr>
                                <td class="w-8 px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">1</td>
                                <td class="w-32 px-3 py-1.5 font-semibold text-slate-600">NAMA</td>
                                <td class="px-3 py-1.5 font-bold text-slate-800">: <?= h($pegawaiDinilai['nama']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">2</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-600">NIP</td>
                                <td class="px-3 py-1.5 font-mono text-slate-800">: <?= h($pegawaiDinilai['nip']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">3</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-600">Pangkat / Gol.</td>
                                <td class="px-3 py-1.5 text-slate-800">: <?= h($pegawaiDinilai['pangkat']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">4</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-600">Jabatan</td>
                                <td class="px-3 py-1.5 text-slate-800">: <?= h($pegawaiDinilai['jabatan']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-1.5 font-semibold text-center text-slate-400 bg-slate-50/50">5</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-600">Unit Kerja</td>
                                <td class="px-3 py-1.5 font-semibold text-slate-800">: <?= h($pegawaiDinilai['unit']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- ========================================================== -->
            <!-- TABEL DAFTAR KEGIATAN HARIAN -->
            <!-- ========================================================== -->
            <div class="overflow-x-auto rounded-xl border border-slate-300">
                <table class="w-full text-left text-xs print-table">
                    <thead class="bg-slate-100/90 text-slate-700 font-bold border-b border-slate-300 uppercase tracking-wider">
                        <tr>
                            <th class="w-10 px-3 py-2.5 text-center">No</th>
                            <th class="w-28 px-3 py-2.5 text-center">Tanggal</th>
                            <th class="px-4 py-2.5">Uraian Kegiatan Tugas Pokok &amp; Tambahan</th>
                            <th class="w-36 px-3 py-2.5 text-center">Volume / Beban</th>
                            <th class="w-44 px-3 py-2.5">Keterangan / Output</th>
                            <th class="w-16 px-2 py-2.5 text-center no-print">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (empty($daftarKegiatan)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    Belum ada kegiatan yang tercatat pada bulan <?= $bulanTeks ?> <?= $tahunDipilih ?>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($daftarKegiatan as $keg): ?>
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-3 py-2.5 text-center font-semibold text-slate-500">
                                        <?= $no++ ?>
                                    </td>
                                    <td class="px-3 py-2.5 text-center whitespace-nowrap font-medium text-slate-800">
                                        <div class="font-bold"><?= $keg['tgl_format'] ?></div>
                                        <div class="text-[10px] text-slate-400"><?= $keg['hari'] ?></div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-800 font-medium leading-relaxed">
                                        <?= h($keg['kegiatan']) ?>
                                        <?php if ($keg['is_manual']): ?>
                                            <span class="no-print ml-1.5 px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-semibold rounded-md">
                                                Tugas Tambahan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                        <span class="px-2.5 py-1 bg-slate-100 font-bold text-slate-800 rounded-lg text-xs">
                                            <?= h($keg['volume']) ?>
                                        </span>
                                        <?php if (!empty($keg['detail_vol'])): ?>
                                            <div class="text-[10px] text-slate-400 mt-0.5"><?= $keg['detail_vol'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-600">
                                        <?= h($keg['keterangan']) ?>
                                    </td>
                                    <td class="px-2 py-2.5 text-center no-print whitespace-nowrap">
                                        <?php if ($keg['is_manual']): ?>
                                            <form action="<?= APP_URL ?>/actions/kinerja/kegiatan_hapus.php" method="POST" class="inline" onsubmit="return confirm('Hapus kegiatan ini dari laporan?')">
                                                <input type="hidden" name="id" value="<?= $keg['id'] ?>">
                                                <input type="hidden" name="bulan" value="<?= $bulanDipilih ?>">
                                                <input type="hidden" name="tahun" value="<?= $tahunDipilih ?>">
                                                <input type="hidden" name="guru_id" value="<?= $guruId ?>">
                                                <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 rounded transition" title="Hapus Kegiatan">
                                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-slate-300 material-symbols-outlined text-[16px]" title="Jadwal Sistem Otomatis">lock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ========================================================== -->
            <!-- TITIMANGSA & TANDA TANGAN -->
            <!-- ========================================================== -->
            <div class="pt-6 space-y-6">
                <!-- Lokasi dan Tanggal Pengesahan -->
                <div class="text-right text-xs font-semibold text-slate-700">
                    Brebes, <?= $jumlahHariDalamBulan ?> <?= $bulanTeks ?> <?= $tahunDipilih ?>
                </div>

                <div class="grid grid-cols-2 gap-8 text-xs text-center">
                    
                    <!-- Pejabat Penilai (Kiri) -->
                    <div class="space-y-16">
                        <div>
                            <p class="font-bold text-slate-700 uppercase">Pejabat Penilai,</p>
                            <p class="text-slate-500 font-medium"><?= h($pejabatPenilai['jabatan']) ?></p>
                        </div>
                        <div class="space-y-0.5">
                            <p class="font-bold text-slate-900 uppercase underline underline-offset-4">
                                <?= h($pejabatPenilai['nama']) ?>
                            </p>
                            <p class="text-slate-600 font-mono text-[11px]">NIP. <?= h($pejabatPenilai['nip']) ?></p>
                        </div>
                    </div>

                    <!-- Pegawai yang Dinilai (Kanan) -->
                    <div class="space-y-16">
                        <div>
                            <p class="font-bold text-slate-700 uppercase">Pegawai yang Dinilai,</p>
                            <p class="text-slate-500 font-medium">Guru Mata Pelajaran</p>
                        </div>
                        <div class="space-y-0.5">
                            <p class="font-bold text-slate-900 uppercase underline underline-offset-4">
                                <?= h($pegawaiDinilai['nama']) ?>
                            </p>
                            <p class="text-slate-600 font-mono text-[11px]">NIP. <?= h($pegawaiDinilai['nip']) ?></p>
                        </div>
                    </div>

                </div>
            </div>

        </div>

    </div>
</main>

<!-- ===================================================================== -->
<!-- MODAL: TAMBAH KEGIATAN TAMBAHAN (NO PRINT) -->
<!-- ===================================================================== -->
<div id="modal-tambah-kegiatan" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-2xs flex items-center justify-center p-4 hidden no-print">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200 animate-scaleUp">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600 text-[20px]">add_circle</span>
                Tambah Kegiatan Kinerja
            </h3>
            <button type="button" onclick="closeModalTambah()" class="text-slate-400 hover:text-slate-600">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form action="<?= APP_URL ?>/actions/kinerja/kegiatan_simpan.php" method="POST" class="space-y-3 text-xs">
            <input type="hidden" name="guru_id" value="<?= $guruId ?>">

            <div class="space-y-1">
                <label class="block font-bold text-slate-700">Tanggal Kegiatan <span class="text-red-500">*</span></label>
                <input type="date" name="tanggal" required 
                       value="<?= sprintf('%04d-%02d-%02d', $tahunDipilih, $bulanDipilih, min(date('d'), $jumlahHariDalamBulan)) ?>"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="space-y-1">
                <label class="block font-bold text-slate-700">Uraian Kegiatan <span class="text-red-500">*</span></label>
                <textarea name="kegiatan" rows="3" required placeholder="Contoh: Mengikuti Rapat Pleno Kelulusan / Melaksanakan Tugas Piket KBM"
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="block font-bold text-slate-700">Volume / Beban</label>
                    <input type="text" name="volume" value="1 Kegiatan" placeholder="1 Kegiatan"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="space-y-1">
                    <label class="block font-bold text-slate-700">Keterangan / Bukti</label>
                    <input type="text" name="keterangan" placeholder="Notula Rapat / SK"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeModalTambah()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition shadow-xs">
                    Simpan Kegiatan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===================================================================== -->
<!-- MODAL: SESUAIKAN DATA PEJABAT PENILAI (NO PRINT) -->
<!-- ===================================================================== -->
<div id="modal-pejabat" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-2xs flex items-center justify-center p-4 hidden no-print">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200 animate-scaleUp">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600 text-[20px]">badge</span>
                Sesuaikan Pejabat Penilai
            </h3>
            <button type="button" onclick="closeModalPejabat()" class="text-slate-400 hover:text-slate-600">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form method="GET" action="kinerja_harian.php" class="space-y-3 text-xs">
            <input type="hidden" name="bulan" value="<?= $bulanDipilih ?>">
            <input type="hidden" name="tahun" value="<?= $tahunDipilih ?>">
            <input type="hidden" name="guru_id" value="<?= $guruId ?>">

            <div class="space-y-1">
                <label class="block font-bold text-slate-700">Nama Pejabat Penilai</label>
                <input type="text" name="pejabat_nama" value="<?= h($pejabatPenilai['nama']) ?>" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="space-y-1">
                <label class="block font-bold text-slate-700">NIP Pejabat Penilai</label>
                <input type="text" name="pejabat_nip" value="<?= h($pejabatPenilai['nip']) ?>" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="space-y-1">
                <label class="block font-bold text-slate-700">Pangkat / Golongan</label>
                <input type="text" name="pejabat_pangkat" value="<?= h($pejabatPenilai['pangkat']) ?>"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="space-y-1">
                <label class="block font-bold text-slate-700">Jabatan</label>
                <input type="text" name="pejabat_jabatan" value="<?= h($pejabatPenilai['jabatan']) ?>"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeModalPejabat()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition shadow-xs">
                    Terapkan di Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Jam Digital Dinamis
function updateClock() {
    const el = document.getElementById('digital-clock');
    if (!el) return;
    const d = new Date();
    el.textContent = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')} WIB`;
}
setInterval(updateClock, 1000);

// Modal Tambah
function openModalTambah() {
    document.getElementById('modal-tambah-kegiatan').classList.remove('hidden');
}
function closeModalTambah() {
    document.getElementById('modal-tambah-kegiatan').classList.add('hidden');
}

// Modal Pejabat
function openModalPejabat() {
    document.getElementById('modal-pejabat').classList.remove('hidden');
}
function closeModalPejabat() {
    document.getElementById('modal-pejabat').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
