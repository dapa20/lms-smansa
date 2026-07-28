<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'Kelas & Jadwal';
$currentPage = 'jadwal';
$user        = currentUser();
$isAdmin     = isAdmin();

$daftarGuru  = $pdo->query("SELECT id, nama_lengkap FROM users WHERE role='guru' ORDER BY nama_lengkap")->fetchAll();
$daftarMapel = $pdo->query('SELECT * FROM mata_pelajaran ORDER BY nama_mapel')->fetchAll();
$daftarKelas = $pdo->query('SELECT * FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();

// Guru hanya bisa melihat jadwalnya sendiri; admin bisa memilih guru mana saja.
$guruId = $isAdmin ? ($_GET['guru_id'] ?? ($daftarGuru[0]['id'] ?? 0)) : $user['id'];

// ---------------------------------------------------------------------
// FITUR CARI GURU (Admin)
// ---------------------------------------------------------------------
$cariGuru = trim($_GET['cari_guru'] ?? '');
if ($cariGuru !== '') {
    $daftarGuruFiltered = array_filter($daftarGuru, function($g) use ($cariGuru) {
        return stripos($g['nama_lengkap'], $cariGuru) !== false;
    });
} else {
    $daftarGuruFiltered = $daftarGuru;
}

// ---------------------------------------------------------------------
// JADWAL MENGAJAR MINGGUAN (reguler & rapat)
// ---------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT j.*, m.nama_mapel, k.nama_kelas, k.id AS kelas_id_val
                        FROM jadwal_mengajar j
                        JOIN mata_pelajaran m ON m.id = j.mapel_id
                        JOIN kelas k ON k.id = j.kelas_id
                        WHERE j.guru_id = ? AND j.jenis IN ('reguler','rapat')
                        ORDER BY FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai");
$stmt->execute([$guruId]);
$jadwalMingguan = $stmt->fetchAll();

// Kelompokkan berdasarkan hari
$hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$jadwalPerHari = array_fill_keys($hariList, []);
foreach ($jadwalMingguan as $j) {
    $jadwalPerHari[$j['hari']][] = $j;
}

// ---------------------------------------------------------------------
// JADWAL UJIAN & PENGAWASAN MENDATANG
// ---------------------------------------------------------------------
$sqlUjian = "SELECT j.*, m.nama_mapel, k.nama_kelas
             FROM jadwal_mengajar j
             JOIN mata_pelajaran m ON m.id = j.mapel_id
             JOIN kelas k ON k.id = j.kelas_id
             WHERE j.jenis = 'ujian' AND j.tanggal >= CURDATE()" . ($isAdmin ? '' : ' AND j.guru_id = ?') . "
             ORDER BY j.tanggal ASC, j.jam_mulai ASC LIMIT 10";
$stmt = $pdo->prepare($sqlUjian);
$stmt->execute($isAdmin ? [] : [$guruId]);
$jadwalUjian = $stmt->fetchAll();

// Total jam mengajar per minggu
$totalJamMinggu = 0;
foreach ($jadwalMingguan as $j) {
    if ($j['jenis'] === 'reguler') {
        $totalJamMinggu += (strtotime($j['jam_selesai']) - strtotime($j['jam_mulai'])) / 3600;
    }
}
$kelasDiajar = count(array_unique(array_column($jadwalMingguan, 'kelas_id')));

// ---------------------------------------------------------------------
// MODE DETAIL JADWAL (klik jadwal → lihat peserta)
// ---------------------------------------------------------------------
$detailJadwalId = (int)($_GET['detail_jadwal'] ?? 0);
$tanggalAbsen   = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAbsen)) {
    $tanggalAbsen = date('Y-m-d');
}

$detailJadwal = null;
$daftarSiswa  = [];
$rekapMap     = [];

if ($detailJadwalId > 0) {
    $stmtDJ = $pdo->prepare("SELECT j.*, m.nama_mapel, k.nama_kelas, k.id AS kelas_id_val,
                                     u.nama_lengkap AS nama_guru
                              FROM jadwal_mengajar j
                              JOIN mata_pelajaran m ON m.id = j.mapel_id
                              JOIN kelas k ON k.id = j.kelas_id
                              JOIN users u ON u.id = j.guru_id
                              WHERE j.id = ? LIMIT 1");
    $stmtDJ->execute([$detailJadwalId]);
    $detailJadwal = $stmtDJ->fetch() ?: null;

    if ($detailJadwal && ($isAdmin || (int)$detailJadwal['guru_id'] === (int)$user['id'])) {
        $kelasIdDetail = (int)$detailJadwal['kelas_id_val'];

        $stmtS = $pdo->prepare("SELECT s.id, s.nis, s.nisn, s.nama_lengkap, s.jenis_kelamin,
                                        kh.status AS status_hari_ini
                                 FROM siswa s
                                 LEFT JOIN kehadiran kh ON kh.siswa_id = s.id
                                     AND kh.kelas_id = ? AND kh.tanggal = ?
                                 WHERE s.kelas_id = ? AND s.status = 'aktif'
                                 ORDER BY s.nama_lengkap ASC");
        $stmtS->execute([$kelasIdDetail, $tanggalAbsen, $kelasIdDetail]);
        $daftarSiswa = $stmtS->fetchAll();

        $stmtRekap = $pdo->prepare("SELECT siswa_id,
                                            SUM(status = 'hadir') AS total_hadir,
                                            SUM(status = 'izin')  AS total_izin,
                                            SUM(status = 'sakit') AS total_sakit,
                                            SUM(status = 'alpa')  AS total_alpa,
                                            COUNT(*) AS total_pertemuan
                                     FROM kehadiran WHERE kelas_id = ?
                                     GROUP BY siswa_id");
        $stmtRekap->execute([$kelasIdDetail]);
        foreach ($stmtRekap->fetchAll() as $r) {
            $rekapMap[(int)$r['siswa_id']] = $r;
        }
    }
}

// ---------------------------------------------------------------------
// MODE EDIT JADWAL (Admin)
// ---------------------------------------------------------------------
$editJadwal = null;
if ($isAdmin && !empty($_GET['edit_jadwal'])) {
    $stmtEdit = $pdo->prepare('SELECT * FROM jadwal_mengajar WHERE id = ?');
    $stmtEdit->execute([(int)$_GET['edit_jadwal']]);
    $editJadwal = $stmtEdit->fetch() ?: null;
}

$modalTambah = !empty($_GET['tambah']);
$modalEdit   = $editJadwal !== null;

// Warna per mapel (warna diambil berdasarkan index)
$paletteColors = [
    ['bg' => '#e3f2fd', 'border' => '#2196F3', 'text' => '#0d47a1'],  // Biru
    ['bg' => '#e8f5e9', 'border' => '#4CAF50', 'text' => '#1b5e20'],  // Hijau
    ['bg' => '#fff3e0', 'border' => '#FF9800', 'text' => '#e65100'],  // Oranye
    ['bg' => '#fce4ec', 'border' => '#e91e63', 'text' => '#880e4f'],  // Pink
    ['bg' => '#f3e5f5', 'border' => '#9c27b0', 'text' => '#4a148c'],  // Ungu
    ['bg' => '#e0f7fa', 'border' => '#00bcd4', 'text' => '#006064'],  // Cyan
    ['bg' => '#fff8e1', 'border' => '#ffc107', 'text' => '#ff6f00'],  // Kuning
    ['bg' => '#fbe9e7', 'border' => '#ff5722', 'text' => '#bf360c'],  // Deep Orange
];
// Map mapel_id ke index warna
$mapelColorIndex = [];
$colorCounter = 0;
foreach ($jadwalMingguan as $j) {
    $mid = (int)$j['mapel_id'];
    if (!isset($mapelColorIndex[$mid])) {
        $mapelColorIndex[$mid] = $colorCounter % count($paletteColors);
        $colorCounter++;
    }
}

// Jam awal & akhir kalender
$jamAwal   = 7;   // 07:00
$jamAkhir  = 19;  // 19:00
$totalJam  = $jamAkhir - $jamAwal;
$slotHeight = 60; // px per jam

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>

<style>
/* ==============================
   KALENDER JADWAL
   ============================== */
.calendar-grid {
    display: grid;
    grid-template-columns: 60px repeat(6, 1fr);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    min-width: 700px;
}
.cal-header {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    padding: 12px 8px;
    text-align: center;
    font-weight: 700;
    font-size: 13px;
    color: #334155;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.cal-time-col {
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
}
.cal-day-col {
    position: relative;
    border-right: 1px solid #e2e8f0;
    min-height: <?= $totalJam * $slotHeight ?>px;
}
.cal-day-col:last-child { border-right: none; }
.cal-hour-line {
    position: absolute;
    left: 0; right: 0;
    border-top: 1px solid #f1f5f9;
    pointer-events: none;
}
.cal-hour-line.major { border-color: #e2e8f0; }
.cal-time-label {
    position: absolute;
    right: 6px;
    font-size: 10px;
    color: #94a3b8;
    font-weight: 500;
    transform: translateY(-50%);
    white-space: nowrap;
}
.cal-event {
    position: absolute;
    left: 3px; right: 3px;
    border-radius: 6px;
    padding: 5px 7px;
    cursor: pointer;
    border-left: 4px solid;
    transition: all 0.15s ease;
    overflow: hidden;
    z-index: 1;
}
.cal-event:hover {
    filter: brightness(0.93);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10;
}
.cal-event.active-event {
    box-shadow: 0 0 0 3px rgba(37,99,235,0.4), 0 4px 12px rgba(0,0,0,0.2);
    transform: translateY(-1px);
    z-index: 10;
}
.cal-event-title {
    font-size: 11px;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cal-event-sub {
    font-size: 10px;
    opacity: 0.8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}

/* ==============================
   PANEL DETAIL JADWAL
   ============================== */
#panel-detail {
    transition: max-height 0.4s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease;
    max-height: 0;
    opacity: 0;
    overflow: hidden;
}
#panel-detail.open {
    max-height: 3000px;
    opacity: 1;
}

/* Status badge absensi */
.status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 600;
}
.badge-hadir  { background: #dcfce7; color: #15803d; }
.badge-izin   { background: #fef9c3; color: #854d0e; }
.badge-sakit  { background: #dbeafe; color: #1e40af; }
.badge-alpa   { background: #fee2e2; color: #b91c1c; }

/* Absensi radio button styling */
.absen-radio { display: none; }
.absen-label {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 4px 10px; border-radius: 6px; cursor: pointer;
    font-size: 11px; font-weight: 600; border: 1.5px solid #e2e8f0;
    color: #64748b; transition: all 0.15s;
    user-select: none;
}
.absen-radio[value="hadir"]:checked + .absen-label { background: #dcfce7; border-color: #16a34a; color: #15803d; }
.absen-radio[value="izin"]:checked  + .absen-label { background: #fef9c3; border-color: #ca8a04; color: #854d0e; }
.absen-radio[value="sakit"]:checked + .absen-label { background: #dbeafe; border-color: #3b82f6; color: #1e40af; }
.absen-radio[value="alpa"]:checked  + .absen-label { background: #fee2e2; border-color: #dc2626; color: #b91c1c; }
.absen-label:hover { background: #f8fafc; border-color: #94a3b8; }
</style>

<main class="pt-16 md:ml-[280px] min-h-screen bg-background">
    <div class="p-md md:p-lg max-w-container-max mx-auto w-full">
        <?php renderFlash(); ?>

        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-lg gap-4">
            <div>
                <h2 class="text-headline-lg font-headline-lg font-semibold text-text-main mb-1">Kelas &amp; Jadwal</h2>
                <p class="text-body-md font-body-md text-text-muted">
                    <?= $isAdmin ? 'Kelola jadwal mengajar mingguan dan jadwal pengawasan ujian.' : 'Jadwal mengajar mingguan Anda. Klik jadwal untuk melihat peserta &amp; absensi.' ?>
                </p>
            </div>
            <?php if ($isAdmin): ?>
            <a href="?tambah=1" class="bg-primary hover:bg-primary-container text-white px-6 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
                <span class="material-symbols-outlined">add</span> Tambah Jadwal
            </a>
            <?php else: ?>
            <span class="text-label-md font-label-md text-text-muted bg-surface-container-low px-4 py-2 rounded-lg flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">visibility</span> Mode Lihat Saja — perubahan dikelola Admin
            </span>
            <?php endif; ?>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Filter Guru (Admin) -->
        <form method="get" class="bg-surface-white rounded-xl p-md mb-lg flex flex-col sm:flex-row items-start sm:items-center gap-3 shadow-sm">
            <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap">Tampilkan jadwal milik:</label>
            <div class="flex-1 flex flex-col sm:flex-row gap-2 w-full">
                <div class="relative flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                    <input type="text" name="cari_guru" value="<?= h($cariGuru) ?>" placeholder="Cari nama guru..."
                           class="w-full pl-9 pr-3 py-2 border border-outline-variant rounded-lg text-body-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <select name="guru_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[200px]">
                    <?php foreach ($daftarGuruFiltered as $g): ?>
                        <option value="<?= (int)$g['id'] ?>" <?= (string)$guruId === (string)$g['id'] ? 'selected' : '' ?>><?= h($g['nama_lengkap']) ?></option>
                    <?php endforeach; ?>
                    <?php if (empty($daftarGuruFiltered)): ?>
                        <option value="" disabled>Tidak ditemukan</option>
                    <?php endif; ?>
                </select>
                <?php if ($cariGuru !== ''): ?>
                <a href="kelas_jadwal.php" class="text-label-md font-label-md text-primary hover:underline flex items-center gap-1 self-center">
                    <span class="material-symbols-outlined text-[16px]">close</span> Reset
                </a>
                <?php endif; ?>
            </div>
            <noscript><button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Tampilkan</button></noscript>
        </form>
        <?php endif; ?>

        <!-- Statistik Ringkas -->
        <div class="grid grid-cols-3 gap-md mb-lg">
            <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-[20px]">schedule</span>
                </div>
                <div>
                    <p class="text-label-sm text-text-muted">Jam Mengajar/Minggu</p>
                    <p class="text-headline-sm font-bold text-primary"><?= $totalJamMinggu ?> jam</p>
                </div>
            </div>
            <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-secondary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-secondary text-[20px]">class</span>
                </div>
                <div>
                    <p class="text-label-sm text-text-muted">Kelas Diajar</p>
                    <p class="text-headline-sm font-bold text-secondary"><?= $kelasDiajar ?> kelas</p>
                </div>
            </div>
            <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-tertiary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-tertiary text-[20px]">event</span>
                </div>
                <div>
                    <p class="text-label-sm text-text-muted">Jadwal Ujian</p>
                    <p class="text-headline-sm font-bold text-tertiary"><?= count($jadwalUjian) ?> jadwal</p>
                </div>
            </div>
        </div>

        <!-- ============================================================
             KALENDER MINGGUAN
             ============================================================ -->
        <div class="bg-surface-white rounded-xl shadow-sm mb-lg overflow-hidden border border-outline-variant/40">
            <div class="flex items-center justify-between px-lg py-md border-b border-outline-variant/40">
                <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">calendar_month</span>
                    Jadwal Mengajar Mingguan
                </h3>
                <span class="text-label-sm text-text-muted bg-primary/5 px-3 py-1 rounded-full">Klik jadwal untuk melihat peserta & absensi</span>
            </div>
            <div class="overflow-x-auto p-md">
                <div class="calendar-grid" style="min-height: <?= $totalJam * $slotHeight + 48 ?>px;">
                    <!-- Header row -->
                    <div class="cal-header cal-time-col" style="grid-row:1; grid-column:1;"></div>
                    <?php foreach ($hariList as $idx => $hari): ?>
                    <div class="cal-header" style="grid-row:1; grid-column:<?= $idx+2 ?>;"><?= $hari ?></div>
                    <?php endforeach; ?>

                    <!-- Time column body -->
                    <div class="cal-time-col" style="grid-row:2; grid-column:1; position:relative; min-height:<?= $totalJam * $slotHeight ?>px;">
                        <?php for ($h = $jamAwal; $h <= $jamAkhir; $h++): ?>
                        <div class="cal-time-label" style="top:<?= ($h - $jamAwal) * $slotHeight ?>px;"><?= sprintf('%02d:00', $h) ?></div>
                        <?php endfor; ?>
                    </div>

                    <!-- Day columns -->
                    <?php foreach ($hariList as $colIdx => $hari): ?>
                    <div class="cal-day-col" style="grid-row:2; grid-column:<?= $colIdx+2 ?>; min-height:<?= $totalJam * $slotHeight ?>px;">
                        <!-- Hour guide lines -->
                        <?php for ($h = 0; $h <= $totalJam; $h++): ?>
                        <div class="cal-hour-line <?= $h % 1 === 0 ? 'major' : '' ?>" style="top:<?= $h * $slotHeight ?>px;"></div>
                        <?php endfor; ?>

                        <!-- Events for this day -->
                        <?php foreach ($jadwalPerHari[$hari] as $j):
                            $mid     = (int)$j['mapel_id'];
                            $ci      = $mapelColorIndex[$mid] ?? 0;
                            $palette = $paletteColors[$ci];

                            $mulaiMin  = (int)substr($j['jam_mulai'], 0, 2) * 60 + (int)substr($j['jam_mulai'], 3, 2);
                            $selesaiMin = (int)substr($j['jam_selesai'], 0, 2) * 60 + (int)substr($j['jam_selesai'], 3, 2);
                            $topPx   = ($mulaiMin - $jamAwal * 60) / 60 * $slotHeight;
                            $heightPx = max(($selesaiMin - $mulaiMin) / 60 * $slotHeight, 28);
                            $isActive = ($detailJadwalId === (int)$j['id']);

                            $guruIdParam = $isAdmin ? '&guru_id=' . (int)$guruId : '';
                        ?>
                        <div class="cal-event <?= $isActive ? 'active-event' : '' ?>"
                             style="top:<?= $topPx ?>px; height:<?= $heightPx ?>px; background:<?= $palette['bg'] ?>; border-left-color:<?= $palette['border'] ?>; color:<?= $palette['text'] ?>;"
                             onclick="bukaDetail(<?= (int)$j['id'] ?>, '<?= date('Y-m-d') ?>')"
                             title="<?= h($j['nama_mapel']) ?> — <?= h($j['nama_kelas']) ?> | <?= substr($j['jam_mulai'],0,5) ?>-<?= substr($j['jam_selesai'],0,5) ?>">
                            <div class="cal-event-title"><?= h($j['nama_mapel']) ?></div>
                            <div class="cal-event-sub"><?= h($j['nama_kelas']) ?><?= $j['ruang'] ? ' · ' . h($j['ruang']) : '' ?></div>
                            <?php if ($heightPx > 50): ?>
                            <div class="cal-event-sub" style="opacity:0.65; font-size:9px;"><?= substr($j['jam_mulai'],0,5) ?>–<?= substr($j['jam_selesai'],0,5) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($jadwalPerHari[$hari])): ?>
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;">
                            <span style="font-size:11px; color:#cbd5e1;">—</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ============================================================
             PANEL DETAIL JADWAL + DAFTAR PESERTA + ABSENSI
             ============================================================ -->
        <div id="panel-detail" class="<?= $detailJadwal ? 'open' : '' ?>">
        <?php if ($detailJadwal): ?>
        <div class="bg-surface-white rounded-xl shadow-sm mb-lg border border-primary/20" id="detail-card">
            <!-- Header Panel -->
            <div class="flex items-center justify-between px-lg py-md border-b border-outline-variant/40"
                 style="background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-primary flex items-center justify-center shadow-sm">
                        <span class="material-symbols-outlined text-white">menu_book</span>
                    </div>
                    <div>
                        <h3 class="text-headline-sm font-bold text-text-main"><?= h($detailJadwal['nama_mapel']) ?></h3>
                        <p class="text-body-sm text-text-muted">
                            <?= h($detailJadwal['nama_kelas']) ?>
                            &bull; <?= h($detailJadwal['hari']) ?>
                            &bull; <?= substr($detailJadwal['jam_mulai'],0,5) ?>–<?= substr($detailJadwal['jam_selesai'],0,5) ?>
                            <?= $detailJadwal['ruang'] ? '&bull; ' . h($detailJadwal['ruang']) : '' ?>
                        </p>
                    </div>
                </div>
                <a href="kelas_jadwal.php<?= $isAdmin ? '?guru_id='.(int)$guruId : '' ?>" class="text-text-muted hover:text-error p-2 rounded-lg hover:bg-error/5 transition-colors" title="Tutup">
                    <span class="material-symbols-outlined">close</span>
                </a>
            </div>

            <!-- Tab Navigation -->
            <div class="flex border-b border-outline-variant/40 px-lg" id="tab-bar">
                <button class="tab-btn active px-4 py-3 text-label-lg font-semibold border-b-2 border-primary text-primary flex items-center gap-2 transition-colors" onclick="switchTab('peserta', this)">
                    <span class="material-symbols-outlined text-[18px]">group</span> Peserta &amp; Absensi
                    <span class="bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= count($daftarSiswa) ?></span>
                </button>
                <button class="tab-btn px-4 py-3 text-label-lg font-semibold border-b-2 border-transparent text-text-muted flex items-center gap-2 hover:text-primary transition-colors" onclick="switchTab('info', this)">
                    <span class="material-symbols-outlined text-[18px]">info</span> Info Jadwal
                </button>
            </div>

            <!-- TAB: Peserta & Absensi -->
            <div id="tab-peserta" class="p-lg">
                <?php if (empty($daftarSiswa)): ?>
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <span class="material-symbols-outlined text-[48px] text-outline-variant mb-3">person_off</span>
                    <p class="text-body-md text-text-muted">Belum ada siswa aktif di kelas <strong><?= h($detailJadwal['nama_kelas']) ?></strong>.</p>
                </div>
                <?php else: ?>

                <!-- Form Absensi -->
                <form action="../actions/profil/kehadiran_simpan.php" method="post" id="form-absensi">
                    <input type="hidden" name="kelas_id" value="<?= (int)$detailJadwal['kelas_id_val'] ?>">
                    <input type="hidden" name="jadwal_id" value="<?= (int)$detailJadwalId ?>">
                    <input type="hidden" name="redirect_to" value="kelas_jadwal">
                    <?php if ($isAdmin): ?>
                    <input type="hidden" name="guru_id" value="<?= (int)$guruId ?>">
                    <?php endif; ?>

                    <!-- Toolbar absensi -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-text-muted text-[18px]">calendar_today</span>
                            <span class="text-label-md text-text-muted">Tanggal Absensi:</span>
                            <input type="date" name="tanggal" id="tanggal-absen" value="<?= h($tanggalAbsen) ?>"
                                   max="<?= date('Y-m-d') ?>"
                                   onchange="gotoTanggal(this.value)"
                                   class="border border-outline-variant rounded-lg px-3 py-1 text-body-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none cursor-pointer">
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Tandai Semua Hadir -->
                            <button type="button" onclick="setAllStatus('hadir')"
                                    class="px-3 py-1.5 rounded-lg text-label-sm font-semibold bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">done_all</span> Semua Hadir
                            </button>
                            <button type="submit"
                                    class="px-5 py-1.5 rounded-lg text-label-lg font-bold bg-primary text-white hover:bg-primary/90 transition-colors flex items-center gap-2 shadow-sm">
                                <span class="material-symbols-outlined text-[18px]">save</span> Simpan Absensi
                            </button>
                        </div>
                    </div>

                    <!-- Rekap singkat -->
                    <div class="flex gap-3 mb-4 flex-wrap" id="rekap-bar">
                        <span class="status-badge badge-hadir" id="count-hadir">Hadir: 0</span>
                        <span class="status-badge badge-izin"  id="count-izin">Izin: 0</span>
                        <span class="status-badge badge-sakit" id="count-sakit">Sakit: 0</span>
                        <span class="status-badge badge-alpa"  id="count-alpa">Alpa: 0</span>
                    </div>

                    <!-- Tabel Siswa -->
                    <div class="overflow-x-auto rounded-xl border border-outline-variant/60">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface-container-low">
                                    <th class="px-4 py-3 text-label-sm font-bold text-text-muted uppercase tracking-wide w-10">No</th>
                                    <th class="px-4 py-3 text-label-sm font-bold text-text-muted uppercase tracking-wide">NIS</th>
                                    <th class="px-4 py-3 text-label-sm font-bold text-text-muted uppercase tracking-wide">Nama Siswa</th>
                                    <th class="px-4 py-3 text-label-sm font-bold text-text-muted uppercase tracking-wide text-center">Status Hari Ini</th>
                                    <th class="px-4 py-3 text-label-sm font-bold text-text-muted uppercase tracking-wide text-center">Hadir</th>
                                    <th class="px-4 py-3 text-label-sm font-bold text-text-muted uppercase tracking-wide text-center">Izin</th>
                                    <th class="px-4 py-3 text-label-sm font-bold text-text-muted uppercase tracking-wide text-center">Sakit</th>
                                    <th class="px-4 py-3 text-label-sm font-bold text-text-muted uppercase tracking-wide text-center">Alpa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daftarSiswa as $no => $s):
                                    $sid    = (int)$s['id'];
                                    $rekap  = $rekapMap[$sid] ?? [];
                                    $statusHariIni = $s['status_hari_ini'] ?: 'hadir';
                                ?>
                                <tr class="border-t border-outline-variant/40 hover:bg-surface-container-low transition-colors <?= $no % 2 === 0 ? '' : 'bg-surface-container-lowest' ?>">
                                    <td class="px-4 py-3 text-body-sm text-text-muted font-medium"><?= $no + 1 ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-block bg-primary/10 text-primary text-[11px] font-mono font-semibold px-2 py-0.5 rounded">
                                            <?= h($s['nis']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                <span class="text-primary text-[11px] font-bold"><?= mb_strtoupper(mb_substr($s['nama_lengkap'], 0, 1)) ?></span>
                                            </div>
                                            <span class="text-body-sm font-medium text-text-main"><?= h($s['nama_lengkap']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <!-- Hidden input siswa_id dengan index unik per baris -->
                                        <input type="hidden" name="siswa_id[<?= $no ?>]" value="<?= $sid ?>">
                                        <!-- Radio group absensi: name pakai index $no agar tiap siswa punya grup sendiri -->
                                        <div class="flex gap-1 flex-wrap justify-center" data-siswa="<?= $sid ?>">
                                            <?php foreach (['hadir' => '✓ Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'] as $statusVal => $statusLabel): ?>
                                            <input type="radio" class="absen-radio" name="status[<?= $no ?>]"
                                                   id="status-<?= $sid ?>-<?= $statusVal ?>"
                                                   value="<?= $statusVal ?>"
                                                   <?= $statusHariIni === $statusVal ? 'checked' : '' ?>
                                                   onchange="updateRekapBar()">
                                            <label class="absen-label" for="status-<?= $sid ?>-<?= $statusVal ?>"><?= $statusLabel ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-body-sm font-semibold text-green-700"><?= (int)($rekap['total_hadir'] ?? 0) ?></td>
                                    <td class="px-4 py-3 text-center text-body-sm font-semibold text-yellow-700"><?= (int)($rekap['total_izin']  ?? 0) ?></td>
                                    <td class="px-4 py-3 text-center text-body-sm font-semibold text-blue-700"><?= (int)($rekap['total_sakit'] ?? 0) ?></td>
                                    <td class="px-4 py-3 text-center text-body-sm font-semibold text-red-700"><?= (int)($rekap['total_alpa']  ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Bottom Save Button -->
                    <div class="flex justify-end mt-4">
                        <button type="submit"
                                class="px-6 py-2 rounded-lg text-label-lg font-bold bg-primary text-white hover:bg-primary/90 transition-colors flex items-center gap-2 shadow-sm">
                            <span class="material-symbols-outlined text-[18px]">save</span> Simpan Absensi
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <!-- TAB: Info Jadwal -->
            <div id="tab-info" class="p-lg hidden">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php
                    $infoItems = [
                        ['label' => 'Mata Pelajaran', 'value' => $detailJadwal['nama_mapel'], 'icon' => 'menu_book'],
                        ['label' => 'Kelas',          'value' => $detailJadwal['nama_kelas'],  'icon' => 'class'],
                        ['label' => 'Hari',           'value' => $detailJadwal['hari'],         'icon' => 'calendar_today'],
                        ['label' => 'Jam',            'value' => substr($detailJadwal['jam_mulai'],0,5) . ' – ' . substr($detailJadwal['jam_selesai'],0,5), 'icon' => 'schedule'],
                        ['label' => 'Ruang',          'value' => $detailJadwal['ruang'] ?: '-',  'icon' => 'meeting_room'],
                        ['label' => 'Guru Pengajar',  'value' => $detailJadwal['nama_guru'],   'icon' => 'person'],
                        ['label' => 'Semester',       'value' => $detailJadwal['semester'],     'icon' => 'school'],
                        ['label' => 'Tahun Ajaran',   'value' => $detailJadwal['tahun_ajaran'], 'icon' => 'date_range'],
                        ['label' => 'Keterangan',     'value' => $detailJadwal['keterangan'] ?: '-', 'icon' => 'notes'],
                    ];
                    ?>
                    <?php foreach ($infoItems as $item): ?>
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-surface-container-low">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="material-symbols-outlined text-primary text-[16px]"><?= $item['icon'] ?></span>
                        </div>
                        <div>
                            <p class="text-label-sm text-text-muted mb-0.5"><?= $item['label'] ?></p>
                            <p class="text-body-sm font-semibold text-text-main"><?= h($item['value']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($isAdmin): ?>
                <div class="flex gap-3 mt-6 pt-4 border-t border-outline-variant/40">
                    <a href="?edit_jadwal=<?= (int)$detailJadwalId ?>&guru_id=<?= (int)$guruId ?>"
                       class="px-4 py-2 rounded-lg bg-primary text-white text-label-lg font-semibold hover:bg-primary/90 flex items-center gap-2 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">edit</span> Edit Jadwal
                    </a>
                    <form action="../actions/jadwal/jadwal_hapus.php" method="post" onsubmit="return confirm('Hapus jadwal ini? Data kehadiran terkait tidak akan terhapus.');">
                        <input type="hidden" name="id" value="<?= (int)$detailJadwalId ?>">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-error/10 text-error text-label-lg font-semibold hover:bg-error/20 flex items-center gap-2 transition-colors border border-error/30">
                            <span class="material-symbols-outlined text-[18px]">delete</span> Hapus Jadwal
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        </div>

        <!-- Jadwal Ujian -->
        <?php if (!empty($jadwalUjian)): ?>
        <div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 mb-lg">
            <div class="px-lg py-md border-b border-outline-variant/40">
                <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
                    <span class="material-symbols-outlined text-tertiary">event_note</span>
                    Jadwal Ujian &amp; Pengawasan Mendatang
                </h3>
            </div>
            <div class="p-lg">
                <div class="space-y-3">
                <?php foreach ($jadwalUjian as $j): ?>
                    <div class="border border-outline-variant/60 rounded-xl p-4 relative group hover:border-primary/30 hover:shadow-sm transition-all">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-lg bg-tertiary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <span class="material-symbols-outlined text-tertiary text-[20px]">event</span>
                                </div>
                                <div>
                                    <p class="text-label-sm font-bold text-tertiary"><?= formatTanggalIndo($j['tanggal']) ?></p>
                                    <p class="text-body-sm font-semibold text-text-main"><?= h($j['nama_mapel']) ?> — <?= h($j['nama_kelas']) ?></p>
                                    <p class="text-label-sm text-text-muted"><?= substr($j['jam_mulai'],0,5) ?>–<?= substr($j['jam_selesai'],0,5) ?><?= $j['ruang'] ? ' &bull; ' . h($j['ruang']) : '' ?></p>
                                    <?php if ($j['keterangan']): ?>
                                    <p class="text-label-sm text-text-muted italic mt-1"><?= h($j['keterangan']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($isAdmin): ?>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="?edit_jadwal=<?= (int)$j['id'] ?>&guru_id=<?= (int)$guruId ?>"
                                   class="p-1.5 rounded-lg text-text-muted hover:text-primary hover:bg-primary/10 transition-colors" title="Edit">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                </a>
                                <form action="../actions/jadwal/jadwal_hapus.php" method="post" onsubmit="return confirm('Hapus jadwal ujian ini?');">
                                    <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                                    <button type="submit" class="p-1.5 rounded-lg text-text-muted hover:text-error hover:bg-error/10 transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</main>

<?php if ($isAdmin): ?>
<!-- ============================================================= -->
<!-- MODAL: TAMBAH JADWAL                                          -->
<!-- ============================================================= -->
<div class="fixed inset-0 z-[60] <?= $modalTambah ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='kelas_jadwal.php'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <form action="../actions/jadwal/jadwal_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-headline-sm text-text-main">Tambah Jadwal</h3>
                <a href="kelas_jadwal.php" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Guru Pengajar</label>
                    <select name="guru_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <?php foreach ($daftarGuru as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" <?= (string)$guruId === (string)$g['id'] ? 'selected' : '' ?>><?= h($g['nama_lengkap']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Mata Pelajaran</label>
                        <select required name="mapel_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach ($daftarMapel as $mp): ?>
                                <option value="<?= (int)$mp['id'] ?>"><?= h($mp['nama_mapel']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Kelas</label>
                        <select required name="kelas_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach ($daftarKelas as $k): ?>
                                <option value="<?= (int)$k['id'] ?>"><?= h($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Jenis Jadwal</label>
                    <select required name="jenis" id="jenis-jadwal" onchange="document.getElementById('grup-hari').classList.toggle('hidden', this.value!=='reguler' && this.value!=='rapat'); document.getElementById('grup-tanggal').classList.toggle('hidden', this.value==='reguler' || this.value==='rapat');" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="reguler">Reguler (jadwal rutin mingguan)</option>
                        <option value="ujian">Ujian (tanggal tertentu)</option>
                        <option value="rapat">Rapat (rutin mingguan)</option>
                        <option value="lainnya">Lainnya (tanggal tertentu)</option>
                    </select>
                </div>
                <div id="grup-hari">
                    <label class="text-label-md font-label-md text-text-main block mb-1">Hari</label>
                    <select name="hari" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <?php foreach ($hariList as $h): ?><option value="<?= $h ?>"><?= $h ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div id="grup-tanggal" class="hidden">
                    <label class="text-label-md font-label-md text-text-main block mb-1">Tanggal Spesifik</label>
                    <input type="date" name="tanggal" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jam Mulai</label>
                        <input required type="time" name="jam_mulai" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jam Selesai</label>
                        <input required type="time" name="jam_selesai" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Ruang</label>
                    <input name="ruang" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: Ruang 204">
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Keterangan (opsional)</label>
                    <input name="keterangan" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: PTS Ganjil - Bab 1-3">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="kelas_jadwal.php" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL: EDIT JADWAL                                            -->
<!-- ============================================================= -->
<div class="fixed inset-0 z-[60] <?= $modalEdit ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='kelas_jadwal.php?guru_id=<?= (int)$guruId ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <form action="../actions/jadwal/jadwal_simpan.php" method="post" class="p-lg">
            <input type="hidden" name="id" value="<?= (int)($editJadwal['id'] ?? 0) ?>">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-headline-sm text-text-main">Edit Jadwal</h3>
                <a href="kelas_jadwal.php?guru_id=<?= (int)$guruId ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Guru Pengajar</label>
                    <select name="guru_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <?php foreach ($daftarGuru as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" <?= (int)($editJadwal['guru_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= h($g['nama_lengkap']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Mata Pelajaran</label>
                        <select required name="mapel_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach ($daftarMapel as $mp): ?>
                                <option value="<?= (int)$mp['id'] ?>" <?= (int)($editJadwal['mapel_id'] ?? 0) === (int)$mp['id'] ? 'selected' : '' ?>><?= h($mp['nama_mapel']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Kelas</label>
                        <select required name="kelas_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach ($daftarKelas as $k): ?>
                                <option value="<?= (int)$k['id'] ?>" <?= (int)($editJadwal['kelas_id'] ?? 0) === (int)$k['id'] ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Jenis Jadwal</label>
                    <select required name="jenis" id="jenis-jadwal-edit" onchange="toggleJenisEdit(this.value);" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="reguler" <?= ($editJadwal['jenis'] ?? '') === 'reguler' ? 'selected' : '' ?>>Reguler (jadwal rutin mingguan)</option>
                        <option value="ujian"   <?= ($editJadwal['jenis'] ?? '') === 'ujian'   ? 'selected' : '' ?>>Ujian (tanggal tertentu)</option>
                        <option value="rapat"   <?= ($editJadwal['jenis'] ?? '') === 'rapat'   ? 'selected' : '' ?>>Rapat (rutin mingguan)</option>
                        <option value="lainnya" <?= ($editJadwal['jenis'] ?? '') === 'lainnya' ? 'selected' : '' ?>>Lainnya (tanggal tertentu)</option>
                    </select>
                </div>
                <?php
                    $jenisEdit = $editJadwal['jenis'] ?? 'reguler';
                    $grupHariHidden = !in_array($jenisEdit, ['reguler', 'rapat']);
                    $grupTanggalHidden = in_array($jenisEdit, ['reguler', 'rapat']);
                ?>
                <div id="grup-hari-edit" class="<?= $grupHariHidden ? 'hidden' : '' ?>">
                    <label class="text-label-md font-label-md text-text-main block mb-1">Hari</label>
                    <select name="hari" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <?php foreach ($hariList as $h): ?>
                            <option value="<?= $h ?>" <?= ($editJadwal['hari'] ?? '') === $h ? 'selected' : '' ?>><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="grup-tanggal-edit" class="<?= $grupTanggalHidden ? 'hidden' : '' ?>">
                    <label class="text-label-md font-label-md text-text-main block mb-1">Tanggal Spesifik</label>
                    <input type="date" name="tanggal" value="<?= h($editJadwal['tanggal'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jam Mulai</label>
                        <input required type="time" name="jam_mulai" value="<?= h(substr($editJadwal['jam_mulai'] ?? '', 0, 5)) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jam Selesai</label>
                        <input required type="time" name="jam_selesai" value="<?= h(substr($editJadwal['jam_selesai'] ?? '', 0, 5)) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Ruang</label>
                    <input name="ruang" value="<?= h($editJadwal['ruang'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: Ruang 204">
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Keterangan (opsional)</label>
                    <input name="keterangan" value="<?= h($editJadwal['keterangan'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: PTS Ganjil - Bab 1-3">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="kelas_jadwal.php?guru_id=<?= (int)$guruId ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleJenisEdit(value) {
    const grupHari    = document.getElementById('grup-hari-edit');
    const grupTanggal = document.getElementById('grup-tanggal-edit');
    if (value === 'reguler' || value === 'rapat') {
        grupHari.classList.remove('hidden');
        grupTanggal.classList.add('hidden');
    } else {
        grupHari.classList.add('hidden');
        grupTanggal.classList.remove('hidden');
    }
}
</script>
<?php endif; ?>

<script>
// -------------------------------------------------------
// Buka detail jadwal (navigate ke URL dengan detail_jadwal=ID)
// -------------------------------------------------------
function bukaDetail(jadwalId, tanggal) {
    const base = 'kelas_jadwal.php';
    const params = new URLSearchParams(window.location.search);
    params.set('detail_jadwal', jadwalId);
    params.set('tanggal', tanggal || '<?= date('Y-m-d') ?>');
    // Scroll ke panel setelah load
    window.location.href = base + '?' + params.toString() + '#detail-card';
}

// -------------------------------------------------------
// Ganti tanggal absensi → reload dengan tanggal baru
// -------------------------------------------------------
function gotoTanggal(tanggal) {
    const params = new URLSearchParams(window.location.search);
    params.set('tanggal', tanggal);
    window.location.href = 'kelas_jadwal.php?' + params.toString() + '#detail-card';
}

// -------------------------------------------------------
// Switch tab di panel detail
// -------------------------------------------------------
function switchTab(tab, btn) {
    document.getElementById('tab-peserta').classList.add('hidden');
    document.getElementById('tab-info').classList.add('hidden');
    document.getElementById('tab-' + tab).classList.remove('hidden');
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-primary', 'text-primary');
        b.classList.add('border-transparent', 'text-text-muted');
    });
    btn.classList.add('border-primary', 'text-primary');
    btn.classList.remove('border-transparent', 'text-text-muted');
}

// -------------------------------------------------------
// Set semua status absensi ke satu nilai
// -------------------------------------------------------
function setAllStatus(status) {
    document.querySelectorAll('input[type="radio"][value="' + status + '"]').forEach(r => {
        r.checked = true;
    });
    updateRekapBar();
}

// -------------------------------------------------------
// Update rekap bar (Hadir/Izin/Sakit/Alpa count)
// -------------------------------------------------------
function updateRekapBar() {
    const counts = { hadir: 0, izin: 0, sakit: 0, alpa: 0 };
    document.querySelectorAll('input[type="radio"].absen-radio:checked').forEach(r => {
        if (counts[r.value] !== undefined) counts[r.value]++;
    });
    document.getElementById('count-hadir').textContent = 'Hadir: ' + counts.hadir;
    document.getElementById('count-izin').textContent  = 'Izin: '  + counts.izin;
    document.getElementById('count-sakit').textContent = 'Sakit: ' + counts.sakit;
    document.getElementById('count-alpa').textContent  = 'Alpa: '  + counts.alpa;
}

// Init rekap on page load
document.addEventListener('DOMContentLoaded', function() {
    updateRekapBar();

    // Auto-scroll ke panel detail jika ada
    const card = document.getElementById('detail-card');
    if (card && window.location.hash === '#detail-card') {
        setTimeout(() => card.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200);
    }
});
</script>

</body>
</html>
