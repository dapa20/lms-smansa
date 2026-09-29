<?php
/**
 * =====================================================================
 * HALAMAN ABSEN QR CODE (GURU & ADMIN)
 * =====================================================================
 * Guru dapat mengenerate QR Code untuk absensi kelas secara langsung,
 * memantau siswa yang scan QR secara real-time, dan menampilkan QR
 * dalam Mode Proyektor (Layar Penuh).
 * =====================================================================
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'Absen QR Code';
$currentPage = 'absen_qr';
$user        = currentUser();
$isAdmin     = isAdmin();

// 1. Ambil daftar kelas yang diajar guru (atau semua kelas jika Admin)
$daftarKelas = [];
if ($isAdmin) {
    $daftarKelas = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
} else {
    // Kelas dari jadwal mengajar + kelas yang diwalikan
    $stmtKls = $pdo->prepare("SELECT DISTINCT k.id, k.nama_kelas, k.tingkat, k.program 
                              FROM kelas k
                              LEFT JOIN jadwal_mengajar j ON j.kelas_id = k.id AND j.guru_id = ?
                              WHERE j.guru_id = ? OR k.wali_kelas_id = ?
                              ORDER BY k.tingkat, k.nama_kelas");
    $stmtKls->execute([$user['id'], $user['id'], $user['id']]);
    $daftarKelas = $stmtKls->fetchAll();
}

// 2. Ambil daftar mata pelajaran guru
$daftarMapel = [];
if ($isAdmin) {
    $daftarMapel = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();
} else {
    $stmtMpl = $pdo->prepare("SELECT DISTINCT m.id, m.nama_mapel, m.kode_mapel
                              FROM mata_pelajaran m
                              JOIN jadwal_mengajar j ON j.mapel_id = m.id
                              WHERE j.guru_id = ?
                              ORDER BY m.nama_mapel");
    $stmtMpl->execute([$user['id']]);
    $daftarMapel = $stmtMpl->fetchAll();
}

// 3. Sesi terpilih / Sesi aktif saat ini
$sessionIdParam = (int)($_GET['session_id'] ?? 0);
$currentSession = null;

if ($sessionIdParam > 0) {
    $stmtSess = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat, k.program, u.nama_lengkap AS nama_guru, m.nama_mapel
                               FROM absen_qr_sessions s
                               JOIN kelas k ON k.id = s.kelas_id
                               JOIN users u ON u.id = s.guru_id
                               LEFT JOIN mata_pelajaran m ON m.id = s.mapel_id
                               WHERE s.id = ? " . ($isAdmin ? "" : "AND s.guru_id = {$user['id']}") . "
                               LIMIT 1");
    $stmtSess->execute([$sessionIdParam]);
    $currentSession = $stmtSess->fetch();
}

// Jika tidak ada parameter session_id, cari sesi aktif terbaru milik guru hari ini
if (!$currentSession) {
    $stmtActive = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat, k.program, u.nama_lengkap AS nama_guru, m.nama_mapel
                                 FROM absen_qr_sessions s
                                 JOIN kelas k ON k.id = s.kelas_id
                                 JOIN users u ON u.id = s.guru_id
                                 LEFT JOIN mata_pelajaran m ON m.id = s.mapel_id
                                 WHERE " . ($isAdmin ? "1=1" : "s.guru_id = {$user['id']}") . "
                                   AND s.tanggal = CURDATE()
                                   AND s.is_active = 1
                                   AND s.berlaku_sampai >= NOW()
                                 ORDER BY s.id DESC
                                 LIMIT 1");
    $stmtActive->execute();
    $currentSession = $stmtActive->fetch();
}

// 4. Statistik Hari Ini
$today = date('Y-m-d');
$statQuery = $pdo->prepare("SELECT 
    COUNT(DISTINCT s.id) AS total_sesi_hari_ini,
    COUNT(DISTINCT CASE WHEN s.is_active = 1 AND s.berlaku_sampai >= NOW() THEN s.id END) AS sesi_aktif,
    (SELECT COUNT(*) FROM kehadiran k WHERE k.tanggal = ? AND k.metode_absen = 'qr' " . ($isAdmin ? "" : "AND k.dicatat_oleh = {$user['id']}") . ") AS total_absen_qr
    FROM absen_qr_sessions s
    WHERE s.tanggal = ? " . ($isAdmin ? "" : "AND s.guru_id = {$user['id']}"));
$statQuery->execute([$today, $today]);
$stats = $statQuery->fetch();

// 5. Riwayat Sesi Guru (5 terakhir)
$stmtRiwayat = $pdo->prepare("SELECT s.*, k.nama_kelas, m.nama_mapel,
                              (SELECT COUNT(*) FROM kehadiran kh WHERE kh.kelas_id = s.kelas_id AND kh.tanggal = s.tanggal AND kh.status = 'hadir') AS total_hadir,
                              (SELECT COUNT(*) FROM siswa sw WHERE sw.kelas_id = s.kelas_id AND sw.status = 'aktif') AS total_siswa
                              FROM absen_qr_sessions s
                              JOIN kelas k ON k.id = s.kelas_id
                              LEFT JOIN mata_pelajaran m ON m.id = s.mapel_id
                              WHERE " . ($isAdmin ? "1=1" : "s.guru_id = {$user['id']}") . "
                              ORDER BY s.id DESC
                              LIMIT 10");
$stmtRiwayat->execute();
$riwayatSesi = $stmtRiwayat->fetchAll();

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>

<main class="pt-16 md:ml-[280px] min-h-screen bg-slate-50/60 pb-16">
    <div class="p-4 sm:p-6 lg:p-8 max-w-[1500px] mx-auto space-y-6">

        <?php renderFlash(); ?>

        <!-- HEADER SECTION -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-[26px]">qr_code_2</span>
                    </span>
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Presensi QR Code</h1>
                </div>
                <p class="text-sm text-slate-500 max-w-2xl">
                    Generate QR Code untuk absensi pertemuan kelas. Tampilkan di layar proyektor agar siswa dapat melakukan scan kehadiran secara mandiri melalui aplikasi siswa.
                </p>
            </div>

            <!-- Header Quick Action -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="<?= APP_URL ?>/pages/siswa_scan_simulator.php" target="_blank" 
                   class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-xl transition shadow-2xs"
                   title="Buka simulator scan siswa untuk uji coba scan QR">
                    <span class="material-symbols-outlined text-[18px]">phone_android</span>
                    Simulator Scan Siswa
                </a>
                <a href="#riwayat-sesi" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                    <span class="material-symbols-outlined text-[18px]">history</span>
                    Riwayat Sesi
                </a>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Sesi Aktif Saat Ini</span>
                    <p class="text-2xl font-bold text-slate-800"><?= (int)($stats['sesi_aktif'] ?? 0) ?> Sesi</p>
                    <span class="text-xs text-emerald-600 font-medium flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full <?= ($stats['sesi_aktif'] ?? 0) > 0 ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300' ?>"></span>
                        <?= ($stats['sesi_aktif'] ?? 0) > 0 ? 'Siap discan oleh siswa' : 'Belum ada sesi aktif' ?>
                    </span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">sensors</span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Hadir via QR Hari Ini</span>
                    <p class="text-2xl font-bold text-slate-800"><?= (int)($stats['total_absen_qr'] ?? 0) ?> Siswa</p>
                    <span class="text-xs text-slate-500 font-medium">Tercatat di sistem kehadiran</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">check_circle</span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Sesi Dibuat Hari Ini</span>
                    <p class="text-2xl font-bold text-slate-800"><?= (int)($stats['total_sesi_hari_ini'] ?? 0) ?> Sesi</p>
                    <span class="text-xs text-slate-500 font-medium"><?= date('d F Y') ?></span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">today</span>
                </div>
            </div>
        </div>

        <!-- MAIN WORKSPACE: GENERATOR & QR LIVE SCREEN -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- LEFT PANEL: FORM BUAT SESI (Col 4) -->
            <div class="lg:col-span-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-600 text-[20px]">add_circle</span>
                        Buka Sesi Absensi Baru
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Tentukan kelas & durasi aktif QR Code</p>
                </div>

                <?php if (empty($daftarKelas)): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs">
                        <div class="font-bold mb-1 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">warning</span> Perhatian
                        </div>
                        Anda belum memiliki jadwal mengajar di kelas manapun. Hubungi Administrator untuk mengatur jadwal mengajar Anda.
                    </div>
                <?php else: ?>
                    <form action="<?= APP_URL ?>/actions/absen_qr/session_create.php" method="POST" class="space-y-4" id="form-buat-sesi">
                        <!-- Kelas -->
                        <div class="space-y-1.5">
                            <label for="kelas_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Pilih Kelas <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <select name="kelas_id" id="kelas_id" required 
                                        class="w-full pl-3.5 pr-9 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($daftarKelas as $k): ?>
                                        <option value="<?= $k['id'] ?>" <?= ($currentSession && (int)$currentSession['kelas_id'] === (int)$k['id']) ? 'selected' : '' ?>>
                                            <?= h($k['nama_kelas']) ?> (<?= h($k['tingkat'] ?? '') ?> <?= h($k['program'] ?? '') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[20px]">expand_more</span>
                            </div>
                        </div>

                        <!-- Mata Pelajaran -->
                        <div class="space-y-1.5">
                            <label for="mapel_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Mata Pelajaran</label>
                            <div class="relative">
                                <select name="mapel_id" id="mapel_id"
                                        class="w-full pl-3.5 pr-9 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                                    <option value="">-- Wali Kelas / Umum --</option>
                                    <?php foreach ($daftarMapel as $m): ?>
                                        <option value="<?= $m['id'] ?>" <?= ($currentSession && (int)($currentSession['mapel_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>>
                                            <?= h($m['nama_mapel']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[20px]">expand_more</span>
                            </div>
                        </div>

                        <!-- Judul Pertemuan -->
                        <div class="space-y-1.5">
                            <label for="judul" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Topik / Pertemuan</label>
                            <input type="text" name="judul" id="judul" placeholder="Contoh: Pertemuan 1 - Aljabar Linier"
                                   value="Presensi Kelas - <?= date('d M Y') ?>"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                        </div>

                        <!-- Tanggal -->
                        <div class="space-y-1.5">
                            <label for="tanggal" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Tanggal Sesi</label>
                            <input type="date" name="tanggal" id="tanggal" value="<?= date('Y-m-d') ?>" required
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                        </div>

                        <!-- Durasi QR Berlaku -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">Masa Berlaku QR Code</label>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="durasi_menit" value="15" class="peer sr-only">
                                    <div class="p-2.5 text-center text-xs font-bold border rounded-xl peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 border-slate-200 bg-slate-50 hover:bg-slate-100 transition">
                                        15 Menit
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="durasi_menit" value="30" checked class="peer sr-only">
                                    <div class="p-2.5 text-center text-xs font-bold border rounded-xl peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 border-slate-200 bg-slate-50 hover:bg-slate-100 transition">
                                        30 Menit
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="durasi_menit" value="45" class="peer sr-only">
                                    <div class="p-2.5 text-center text-xs font-bold border rounded-xl peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 border-slate-200 bg-slate-50 hover:bg-slate-100 transition">
                                        45 Menit
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="durasi_menit" value="60" class="peer sr-only">
                                    <div class="p-2.5 text-center text-xs font-bold border rounded-xl peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 border-slate-200 bg-slate-50 hover:bg-slate-100 transition">
                                        60 Menit
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="durasi_menit" value="90" class="peer sr-only">
                                    <div class="p-2.5 text-center text-xs font-bold border rounded-xl peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 border-slate-200 bg-slate-50 hover:bg-slate-100 transition">
                                        90 Menit
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="durasi_menit" value="1440" class="peer sr-only">
                                    <div class="p-2.5 text-center text-xs font-bold border rounded-xl peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 border-slate-200 bg-slate-50 hover:bg-slate-100 transition">
                                        Seharian
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl transition shadow-md shadow-emerald-600/20 active:scale-[0.99]">
                            <span class="material-symbols-outlined text-[20px]">qr_code_2</span>
                            Generate QR Code Sekarang
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- RIGHT PANEL: DISPLAY QR CODE & LIVE ROSTER (Col 8) -->
            <div class="lg:col-span-8 space-y-6">
                
                <?php if ($currentSession): ?>
                    <!-- ACTIVE SESSION CONTAINER -->
                    <div id="active-session-card" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                        
                        <!-- Session Header Bar -->
                        <div class="p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50">
                            <div class="flex items-center gap-3">
                                <span id="session-badge-status" class="px-3 py-1 text-xs font-bold rounded-full <?= (int)$currentSession['is_active'] === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' ?> flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full <?= (int)$currentSession['is_active'] === 1 ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' ?>"></span>
                                    <span id="session-status-text"><?= (int)$currentSession['is_active'] === 1 ? 'Sesi Aktif' : 'Sesi Ditutup' ?></span>
                                </span>
                                <h3 class="text-base font-bold text-slate-800">
                                    Kelas <?= h($currentSession['nama_kelas']) ?> 
                                    <span class="text-slate-400 font-normal">|</span> 
                                    <span class="text-emerald-700 font-semibold"><?= h($currentSession['nama_mapel'] ?? 'Umum') ?></span>
                                </h3>
                            </div>

                            <!-- Action Toolbar -->
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="openFullscreenModal()" 
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition shadow-xs">
                                    <span class="material-symbols-outlined text-[16px]">fullscreen</span>
                                    Layar Penuh (Proyektor)
                                </button>
                                
                                <form action="<?= APP_URL ?>/actions/absen_qr/session_toggle.php" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin memperbarui kode QR ini? Token lama tidak akan bisa dipakai lagi.')">
                                    <input type="hidden" name="action" value="regenerate">
                                    <input type="hidden" name="session_id" value="<?= $currentSession['id'] ?>">
                                    <button type="submit" class="p-2 text-slate-600 hover:text-emerald-600 hover:bg-slate-100 rounded-xl transition" title="Perbarui Kode QR (Cegah Kecurangan)">
                                        <span class="material-symbols-outlined text-[20px]">refresh</span>
                                    </button>
                                </form>

                                <?php if ((int)$currentSession['is_active'] === 1): ?>
                                    <form action="<?= APP_URL ?>/actions/absen_qr/session_toggle.php" method="POST" class="inline" onsubmit="return confirm('Tutup sesi absensi sekarang?')">
                                        <input type="hidden" name="action" value="close">
                                        <input type="hidden" name="session_id" value="<?= $currentSession['id'] ?>">
                                        <button type="submit" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl border border-rose-200 transition">
                                            Tutup Sesi
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form action="<?= APP_URL ?>/actions/absen_qr/session_toggle.php" method="POST" class="inline">
                                        <input type="hidden" name="action" value="reopen">
                                        <input type="hidden" name="session_id" value="<?= $currentSession['id'] ?>">
                                        <input type="hidden" name="tambah_menit" value="30">
                                        <button type="submit" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-200 transition">
                                            Buka Kembali (30 Menit)
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- QR Code & Quick Info Panel -->
                        <div class="p-6 grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                            
                            <!-- QR Code Visual Display (5 Cols) -->
                            <div class="md:col-span-5 flex flex-col items-center justify-center p-5 bg-gradient-to-b from-slate-50 to-white rounded-2xl border border-slate-200 text-center">
                                <div class="relative p-3 bg-white rounded-2xl shadow-sm border border-slate-100">
                                    <div id="qrcode-box" class="w-[200px] h-[200px] flex items-center justify-center"></div>
                                    <div id="qr-expired-overlay" class="hidden absolute inset-0 bg-slate-900/80 backdrop-blur-2xs rounded-2xl flex flex-col items-center justify-center text-white p-4">
                                        <span class="material-symbols-outlined text-[36px] text-amber-400">timer_off</span>
                                        <p class="font-bold text-xs mt-1">Sesi Telah Berakhir</p>
                                    </div>
                                </div>

                                <!-- Token display & Copy -->
                                <div class="mt-4 flex items-center gap-2">
                                    <code id="kode-qr-text" class="px-3 py-1 bg-slate-100 border border-slate-200 text-xs font-mono font-bold text-slate-800 rounded-lg select-all">
                                        <?= h($currentSession['kode_qr']) ?>
                                    </code>
                                    <button type="button" onclick="copyQrCode()" class="p-1.5 text-slate-500 hover:text-emerald-600 hover:bg-slate-100 rounded-lg transition" title="Salin Kode">
                                        <span class="material-symbols-outlined text-[18px]">content_copy</span>
                                    </button>
                                </div>

                                <div class="mt-2 flex gap-2">
                                    <button type="button" onclick="downloadQrCode()" class="text-[11px] text-slate-500 hover:text-slate-800 font-semibold underline flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">download</span> Unduh QR Image
                                    </button>
                                </div>
                            </div>

                            <!-- Session Meta & Countdown (7 Cols) -->
                            <div class="md:col-span-7 space-y-4">
                                <div>
                                    <h4 class="text-lg font-bold text-slate-800"><?= h($currentSession['judul']) ?></h4>
                                    <p class="text-xs text-slate-500">Guru Pengajar: <span class="font-semibold text-slate-700"><?= h($currentSession['nama_guru']) ?></span></p>
                                </div>

                                <!-- COUNTDOWN TIMER -->
                                <div class="p-4 bg-emerald-50/70 border border-emerald-100 rounded-2xl space-y-2">
                                    <div class="flex items-center justify-between text-xs font-semibold text-emerald-900">
                                        <span class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-emerald-600">schedule</span>
                                            Sisa Waktu Berlaku
                                        </span>
                                        <span id="countdown-text" class="font-mono text-base font-bold text-emerald-700">00:00:00</span>
                                    </div>
                                    <!-- Progress Bar -->
                                    <div class="w-full bg-emerald-200/60 rounded-full h-2 overflow-hidden">
                                        <div id="countdown-progress" class="bg-emerald-600 h-2 rounded-full transition-all duration-1000" style="width: 100%;"></div>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-emerald-700/80">
                                        <span>Dimulai: <?= substr($currentSession['jam_mulai'], 0, 5) ?> WIB</span>
                                        <span>Berlaku hingga: <?= date('H:i', strtotime($currentSession['berlaku_sampai'])) ?> WIB</span>
                                    </div>
                                </div>

                                <!-- Attendance Progress Bar -->
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-bold text-slate-700">Tingkat Kehadiran Siswa</span>
                                        <span class="font-bold text-emerald-600" id="stat-persen">0%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                        <div id="stat-progress-bar" class="bg-gradient-to-r from-emerald-500 to-teal-600 h-2.5 rounded-full transition-all duration-500" style="width: 0%;"></div>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-500 font-medium">
                                        <span><strong id="stat-hadir" class="text-slate-800">0</strong> Hadir</span>
                                        <span><strong id="stat-belum" class="text-slate-800">0</strong> Belum Scan</span>
                                        <span>Total: <strong id="stat-total" class="text-slate-800">0</strong> Siswa</span>
                                    </div>
                                </div>

                                <div class="text-[11px] text-slate-500 flex items-center gap-1.5 pt-1">
                                    <span class="material-symbols-outlined text-[16px] text-slate-400">info</span>
                                    Daftar kehadiran diperbarui otomatis setiap 3 detik tanpa refresh.
                                </div>
                            </div>
                        </div>

                        <!-- LIVE ATTENDANCE ROSTER TABS -->
                        <div class="border-t border-slate-100">
                            <div class="px-6 pt-3 flex items-center justify-between border-b border-slate-100">
                                <div class="flex gap-4">
                                    <button type="button" onclick="switchRosterTab('hadir')" id="tab-btn-hadir" 
                                            class="pb-3 border-b-2 border-emerald-600 text-emerald-600 font-bold text-xs flex items-center gap-1.5 transition">
                                        <span>Sudah Hadir</span>
                                        <span id="badge-count-hadir" class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] rounded-full font-bold">0</span>
                                    </button>
                                    <button type="button" onclick="switchRosterTab('belum')" id="tab-btn-belum" 
                                            class="pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 font-bold text-xs flex items-center gap-1.5 transition">
                                        <span>Belum Scan</span>
                                        <span id="badge-count-belum" class="px-2 py-0.5 bg-slate-100 text-slate-700 text-[10px] rounded-full font-bold">0</span>
                                    </button>
                                </div>
                                <div class="pb-3 flex items-center gap-2">
                                    <label class="flex items-center gap-1.5 text-xs text-slate-500 cursor-pointer select-none">
                                        <input type="checkbox" id="toggle-sound" class="rounded text-emerald-600 focus:ring-emerald-500">
                                        <span>Bunyi Notif</span>
                                    </label>
                                </div>
                            </div>

                            <!-- List Siswa Hadir -->
                            <div id="tab-content-hadir" class="p-6">
                                <div id="container-siswa-hadir" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[360px] overflow-y-auto pr-1">
                                    <!-- Diisi via AJAX polling -->
                                    <div class="col-span-full py-8 text-center text-slate-400 text-xs">
                                        Menunggu siswa melakukan scan QR...
                                    </div>
                                </div>
                            </div>

                            <!-- List Siswa Belum Hadir -->
                            <div id="tab-content-belum" class="p-6 hidden">
                                <div id="container-siswa-belum" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[360px] overflow-y-auto pr-1">
                                    <!-- Diisi via AJAX polling -->
                                </div>
                            </div>
                        </div>

                    </div>
                <?php else: ?>
                    <!-- EMPTY STATE -->
                    <div class="bg-white p-12 rounded-2xl border border-slate-200/80 shadow-xs text-center space-y-4">
                        <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-3xl mx-auto flex items-center justify-center">
                            <span class="material-symbols-outlined text-[48px]">qr_code_scanner</span>
                        </div>
                        <div class="max-w-md mx-auto space-y-1">
                            <h3 class="text-lg font-bold text-slate-800">Belum Ada Sesi Absen yang Dibuka</h3>
                            <p class="text-xs text-slate-500 leading-relaxed">
                                Pilih kelas Anda pada panel sebelah kiri lalu klik <strong>Generate QR Code Sekarang</strong> untuk memulai sesi presensi digital.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>

        <!-- SECTION: RIWAYAT SESI ABSEN TERAKHIR -->
        <div id="riwayat-sesi" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Riwayat Sesi Absen QR</h3>
                    <p class="text-xs text-slate-500">Daftar sesi absensi yang pernah dibuat sebelumnya</p>
                </div>
            </div>

            <?php if (empty($riwayatSesi)): ?>
                <p class="text-xs text-slate-400 py-4 text-center">Belum ada riwayat sesi absensi.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Tanggal &amp; Waktu</th>
                                <th class="px-4 py-3">Kelas &amp; Mapel</th>
                                <th class="px-4 py-3">Topik Pertemuan</th>
                                <th class="px-4 py-3">Kode QR</th>
                                <th class="px-4 py-3 text-center">Kehadiran</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($riwayatSesi as $rw): 
                                $isSessionExpired = strtotime($rw['berlaku_sampai']) < time();
                                $isActive = (int)$rw['is_active'] === 1 && !$isSessionExpired;
                            ?>
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-4 py-3 font-medium text-slate-800 whitespace-nowrap">
                                        <div><?= date('d/m/Y', strtotime($rw['tanggal'])) ?></div>
                                        <div class="text-[11px] text-slate-400"><?= substr($rw['jam_mulai'], 0, 5) ?> WIB (<?= $rw['durasi_menit'] ?> mnt)</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-bold text-slate-800"><?= h($rw['nama_kelas']) ?></span>
                                        <div class="text-[11px] text-emerald-600"><?= h($rw['nama_mapel'] ?? 'Umum') ?></div>
                                    </td>
                                    <td class="px-4 py-3 max-w-[200px] truncate" title="<?= h($rw['judul']) ?>">
                                        <?= h($rw['judul']) ?>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[11px] text-slate-700 font-semibold">
                                        <?= h($rw['kode_qr']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-bold text-slate-800"><?= (int)$rw['total_hadir'] ?></span>
                                        <span class="text-slate-400">/ <?= (int)$rw['total_siswa'] ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($isActive): ?>
                                            <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 font-bold rounded-full text-[10px]">Aktif</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 bg-slate-100 text-slate-600 font-medium rounded-full text-[10px]">Selesai</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="<?= APP_URL ?>/pages/absen_qr.php?session_id=<?= $rw['id'] ?>" 
                                           class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition">
                                            <span class="material-symbols-outlined text-[16px]">visibility</span> Tampilkan
                                        </a>
                                        <form action="<?= APP_URL ?>/actions/absen_qr/session_toggle.php" method="POST" class="inline" onsubmit="return confirm('Hapus riwayat sesi ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="session_id" value="<?= $rw['id'] ?>">
                                            <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Hapus Sesi">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- ===================================================================== -->
<!-- MODAL FULLSCREEN / PROYEKTOR MODE (UNTUK DITAMPILKAN DI KELAS) -->
<!-- ===================================================================== -->
<?php if ($currentSession): ?>
<div id="modal-projector" class="fixed inset-0 z-50 bg-slate-900 text-white flex flex-col justify-between hidden backdrop-blur-md">
    
    <!-- Top Header -->
    <div class="px-8 py-5 flex items-center justify-between border-b border-slate-800 bg-slate-950/80">
        <div class="flex items-center gap-4">
            <span class="p-2.5 bg-emerald-500/20 text-emerald-400 rounded-2xl flex items-center justify-center">
                <span class="material-symbols-outlined text-[32px]">qr_code_2</span>
            </span>
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-white">Presensi Siswa: Kelas <?= h($currentSession['nama_kelas']) ?></h2>
                <p class="text-sm text-slate-400">
                    Mata Pelajaran: <span class="text-emerald-400 font-semibold"><?= h($currentSession['nama_mapel'] ?? 'Wali Kelas / Pelajaran Umum') ?></span> | Guru: <?= h($currentSession['nama_guru']) ?>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <!-- Projector Countdown -->
            <div class="px-4 py-2 bg-slate-800/90 rounded-2xl border border-slate-700 flex items-center gap-3">
                <span class="material-symbols-outlined text-emerald-400 text-[24px]">timer</span>
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Sisa Waktu</span>
                    <span id="proj-countdown-text" class="text-xl font-mono font-bold text-emerald-400">00:00:00</span>
                </div>
            </div>

            <!-- Close Projector -->
            <button type="button" onclick="closeFullscreenModal()" 
                    class="p-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-2xl transition flex items-center justify-center">
                <span class="material-symbols-outlined text-[28px]">close</span>
            </button>
        </div>
    </div>

    <!-- Center Content: HUGE QR CODE & Live Counter -->
    <div class="flex-1 flex flex-col md:flex-row items-center justify-center gap-12 p-8 overflow-y-auto">
        
        <!-- BIG QR BOX -->
        <div class="flex flex-col items-center justify-center space-y-4">
            <div class="p-6 bg-white rounded-3xl shadow-2xl border-4 border-emerald-500">
                <div id="proj-qrcode-box" class="w-[320px] h-[320px] md:w-[380px] md:h-[380px] flex items-center justify-center"></div>
            </div>
            <div class="text-center space-y-1">
                <p class="text-sm font-semibold text-slate-300">Arahkan kamera aplikasi siswa ke QR Code di atas</p>
                <code class="px-4 py-1.5 bg-slate-800 border border-slate-700 text-emerald-400 text-sm font-mono font-bold rounded-xl tracking-wider inline-block">
                    <?= h($currentSession['kode_qr']) ?>
                </code>
            </div>
        </div>

        <!-- LIVE PARTICIPANTS FEED -->
        <div class="w-full max-w-md bg-slate-800/80 border border-slate-700/80 rounded-3xl p-6 flex flex-col h-[480px]">
            <div class="flex items-center justify-between border-b border-slate-700/70 pb-4 mb-4">
                <div>
                    <h4 class="text-lg font-bold text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Peserta Hadir
                    </h4>
                    <p class="text-xs text-slate-400">Siswa yang sudah berhasil scan QR</p>
                </div>
                <div class="text-right">
                    <span id="proj-stat-hadir" class="text-2xl font-bold text-emerald-400">0</span>
                    <span id="proj-stat-total" class="text-sm text-slate-400"> / 0</span>
                </div>
            </div>

            <!-- List of Attendees -->
            <div id="proj-attendee-list" class="flex-1 overflow-y-auto space-y-2.5 pr-1">
                <!-- Diisi live -->
                <div class="text-center py-12 text-slate-500 text-sm">
                    Menunggu siswa melakukan scan...
                </div>
            </div>
        </div>

    </div>

    <!-- Bottom Footer Bar -->
    <div class="px-8 py-3 bg-slate-950 border-t border-slate-800 text-center text-xs text-slate-500">
        Tekan <kbd class="px-2 py-0.5 bg-slate-800 rounded-md font-mono text-slate-300">Esc</kbd> untuk keluar dari layar penuh. SMAN 1 Bumiayu Digital Attendance System.
    </div>
</div>
<?php endif; ?>

<!-- Sound FX Audio for Attendance Ping -->
<audio id="audio-attend" preload="auto">
    <source src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU9vT18AAAA=" type="audio/wav">
</audio>

<!-- Include QR Code Library -->
<script src="<?= APP_URL ?>/assets/qrcode.min.js"></script>

<script>
const SESSION_DATA = <?= $currentSession ? json_encode($currentSession) : 'null' ?>;
let qrInstance = null;
let projQrInstance = null;
let pollingInterval = null;
let timerInterval = null;
let lastHadirCount = 0;
let initialTotalSeconds = 0;

document.addEventListener('DOMContentLoaded', () => {
    if (!SESSION_DATA) return;

    // Hitung total detik awal untuk progress bar countdown
    const berlakuSampai = new Date(SESSION_DATA.berlaku_sampai.replace(/-/g, '/')).getTime();
    const jamMulaiParts = SESSION_DATA.jam_mulai.split(':');
    const waktuMulai = new Date(SESSION_DATA.tanggal.replace(/-/g, '/'));
    waktuMulai.setHours(parseInt(jamMulaiParts[0]), parseInt(jamMulaiParts[1]), parseInt(jamMulaiParts[2] || 0));
    initialTotalSeconds = Math.max(60, Math.floor((berlakuSampai - waktuMulai.getTime()) / 1000));

    // Render QR Code
    renderQrCode(SESSION_DATA.kode_qr);

    // Jalankan Polling Data Realtime
    pollAttendees();
    pollingInterval = setInterval(pollAttendees, 3000);

    // Jalankan Countdown Timer
    updateCountdown();
    timerInterval = setInterval(updateCountdown, 1000);

    // Escape listener for projector modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeFullscreenModal();
        }
    });
});

/**
 * Render QR Code menggunakan library qrcode.min.js
 */
function renderQrCode(text) {
    const box = document.getElementById('qrcode-box');
    if (!box) return;

    box.innerHTML = '';
    qrInstance = new QRCode(box, {
        text: text,
        width: 200,
        height: 200,
        colorDark: '#0f172a',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });

    const projBox = document.getElementById('proj-qrcode-box');
    if (projBox) {
        projBox.innerHTML = '';
        const size = window.innerWidth < 768 ? 300 : 360;
        projQrInstance = new QRCode(projBox, {
            text: text,
            width: size,
            height: size,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }
}

/**
 * Update Countdown Timer setiap detik
 */
function updateCountdown() {
    if (!SESSION_DATA) return;

    const now = new Date().getTime();
    const expiry = new Date(SESSION_DATA.berlaku_sampai.replace(/-/g, '/')).getTime();
    const diff = Math.max(0, Math.floor((expiry - now) / 1000));

    const countdownText = document.getElementById('countdown-text');
    const projCountdownText = document.getElementById('proj-countdown-text');
    const progressBar = document.getElementById('countdown-progress');
    const expiredOverlay = document.getElementById('qr-expired-overlay');

    if (diff <= 0) {
        if (countdownText) countdownText.textContent = '00:00:00 (Selesai)';
        if (projCountdownText) projCountdownText.textContent = '00:00:00 (Selesai)';
        if (progressBar) progressBar.style.width = '0%';
        if (expiredOverlay) expiredOverlay.classList.remove('hidden');
        
        const badgeText = document.getElementById('session-status-text');
        const badge = document.getElementById('session-badge-status');
        if (badgeText) badgeText.textContent = 'Sesi Berakhir';
        if (badge) {
            badge.className = 'px-3 py-1 text-xs font-bold rounded-full bg-slate-200 text-slate-700 flex items-center gap-1.5';
        }
        return;
    }

    const hours = Math.floor(diff / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    const seconds = diff % 60;

    const formatted = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

    if (countdownText) countdownText.textContent = formatted;
    if (projCountdownText) projCountdownText.textContent = formatted;

    if (progressBar && initialTotalSeconds > 0) {
        const percent = Math.min(100, Math.max(0, (diff / initialTotalSeconds) * 100));
        progressBar.style.width = percent + '%';
        if (percent < 20) {
            progressBar.className = 'bg-rose-500 h-2 rounded-full transition-all duration-1000';
        } else if (percent < 50) {
            progressBar.className = 'bg-amber-500 h-2 rounded-full transition-all duration-1000';
        }
    }
}

/**
 * Polling AJAX ke actions/absen_qr/live_attendees.php
 */
function pollAttendees() {
    if (!SESSION_DATA || !SESSION_DATA.id) return;

    fetch(`<?= APP_URL ?>/actions/absen_qr/live_attendees.php?session_id=${SESSION_DATA.id}`)
        .then(res => res.json())
        .then(res => {
            if (!res.success) return;

            // Update stats
            document.getElementById('stat-hadir').textContent = res.stats.hadir;
            document.getElementById('stat-belum').textContent = res.stats.belum;
            document.getElementById('stat-total').textContent = res.stats.total;
            document.getElementById('stat-persen').textContent = res.stats.persen + '%';
            document.getElementById('stat-progress-bar').style.width = res.stats.persen + '%';

            document.getElementById('badge-count-hadir').textContent = res.stats.hadir;
            document.getElementById('badge-count-belum').textContent = res.stats.belum;

            if (document.getElementById('proj-stat-hadir')) {
                document.getElementById('proj-stat-hadir').textContent = res.stats.hadir;
                document.getElementById('proj-stat-total').textContent = ' / ' + res.stats.total;
            }

            // Bunyikan notifikasi jika ada siswa baru yang hadir
            if (res.stats.hadir > lastHadirCount && lastHadirCount > 0) {
                playNotificationSound();
            }
            lastHadirCount = res.stats.hadir;

            // Render roster siswa yang sudah hadir
            renderHadirList(res.hadir_list);

            // Render roster siswa yang belum hadir
            renderBelumList(res.belum_list);

            // Render projector attendees list
            renderProjectorList(res.hadir_list);
        })
        .catch(err => console.error('Gagal polling kehadiran:', err));
}

/**
 * Render daftar siswa yang sudah hadir
 */
function renderHadirList(list) {
    const container = document.getElementById('container-siswa-hadir');
    if (!container) return;

    if (!list || list.length === 0) {
        container.innerHTML = `
            <div class="col-span-full py-8 text-center text-slate-400 text-xs">
                Belum ada siswa yang melakukan scan QR.
            </div>
        `;
        return;
    }

    let html = '';
    list.forEach(s => {
        const isQr = s.metode === 'qr';
        const methodBadge = isQr 
            ? '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-md flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">qr_code_2</span> Scan QR</span>'
            : '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded-md">Manual</span>';

        const avatar = s.foto 
            ? `<img src="${s.foto}" class="w-10 h-10 rounded-full object-cover border border-slate-200">`
            : `<div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center text-xs">${getInitials(s.nama_lengkap)}</div>`;

        html += `
            <div class="p-3 bg-slate-50/80 hover:bg-slate-100/80 border border-slate-200/80 rounded-xl flex items-center justify-between gap-3 transition">
                <div class="flex items-center gap-3 min-w-0">
                    ${avatar}
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-800 truncate">${escapeHtml(s.nama_lengkap)}</p>
                        <p class="text-[11px] text-slate-500 font-mono">NISN: ${escapeHtml(s.nisn || '-')}</p>
                    </div>
                </div>
                <div class="text-right flex-shrink-0 space-y-0.5">
                    ${methodBadge}
                    <span class="text-[10px] text-slate-400 font-mono block">${s.jam_menit} WIB</span>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

/**
 * Render daftar siswa yang belum hadir
 */
function renderBelumList(list) {
    const container = document.getElementById('container-siswa-belum');
    if (!container) return;

    if (!list || list.length === 0) {
        container.innerHTML = `
            <div class="col-span-full py-8 text-center text-emerald-600 font-bold text-xs flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                Luar biasa! Seluruh siswa telah hadir.
            </div>
        `;
        return;
    }

    let html = '';
    list.forEach(s => {
        const avatar = s.foto 
            ? `<img src="${s.foto}" class="w-10 h-10 rounded-full object-cover border border-slate-200">`
            : `<div class="w-10 h-10 rounded-full bg-slate-200 text-slate-600 font-bold flex items-center justify-center text-xs">${getInitials(s.nama_lengkap)}</div>`;

        html += `
            <div class="p-3 bg-slate-50/80 border border-slate-200/80 rounded-xl flex items-center justify-between gap-3 transition">
                <div class="flex items-center gap-3 min-w-0">
                    ${avatar}
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-700 truncate">${escapeHtml(s.nama_lengkap)}</p>
                        <p class="text-[11px] text-slate-400 font-mono">NISN: ${escapeHtml(s.nisn || '-')}</p>
                    </div>
                </div>
                <button type="button" onclick="manualCheckin(${s.id})" 
                        class="px-2.5 py-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition flex items-center gap-1 flex-shrink-0"
                        title="Tandai Hadir Manual jika siswa tidak membawa ponsel / baterai habis">
                    <span class="material-symbols-outlined text-[14px]">done</span> Hadir
                </button>
            </div>
        `;
    });
    container.innerHTML = html;
}

/**
 * Render daftar siswa di Modal Proyektor (Fullscreen)
 */
function renderProjectorList(list) {
    const container = document.getElementById('proj-attendee-list');
    if (!container) return;

    if (!list || list.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 text-slate-500 text-sm">
                Menunggu siswa melakukan scan...
            </div>
        `;
        return;
    }

    let html = '';
    list.slice(0, 30).forEach(s => {
        html += `
            <div class="p-2.5 bg-slate-900/60 border border-slate-700/60 rounded-xl flex items-center justify-between text-xs animate-fadeIn">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-7 h-7 rounded-full bg-emerald-500/20 text-emerald-400 font-bold flex items-center justify-center text-[10px]">
                        ${getInitials(s.nama_lengkap)}
                    </div>
                    <span class="font-semibold text-slate-200 truncate">${escapeHtml(s.nama_lengkap)}</span>
                </div>
                <span class="font-mono text-[10px] text-emerald-400 font-bold">${s.jam_menit}</span>
            </div>
        `;
    });
    container.innerHTML = html;
}

/**
 * Tandai Hadir Manual dari Layar
 */
function manualCheckin(siswaId) {
    if (!SESSION_DATA) return;

    fetch('<?= APP_URL ?>/actions/absen_qr/manual_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `session_id=${SESSION_DATA.id}&siswa_id=${siswaId}&status=hadir`
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            pollAttendees();
        } else {
            alert(res.message || 'Gagal menandai hadir');
        }
    })
    .catch(err => alert('Terjadi kesalahan jaringan'));
}

/**
 * Buka Modal Layar Penuh (Proyektor)
 */
function openFullscreenModal() {
    const modal = document.getElementById('modal-projector');
    if (modal) {
        modal.classList.remove('hidden');
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(() => {});
        }
    }
}

/**
 * Tutup Modal Layar Penuh
 */
function closeFullscreenModal() {
    const modal = document.getElementById('modal-projector');
    if (modal) {
        modal.classList.add('hidden');
        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(() => {});
        }
    }
}

/**
 * Tab switcher: Hadir vs Belum Hadir
 */
function switchRosterTab(tab) {
    const btnHadir = document.getElementById('tab-btn-hadir');
    const btnBelum = document.getElementById('tab-btn-belum');
    const cntHadir = document.getElementById('tab-content-hadir');
    const cntBelum = document.getElementById('tab-content-belum');

    if (tab === 'hadir') {
        btnHadir.className = 'pb-3 border-b-2 border-emerald-600 text-emerald-600 font-bold text-xs flex items-center gap-1.5 transition';
        btnBelum.className = 'pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 font-bold text-xs flex items-center gap-1.5 transition';
        cntHadir.classList.remove('hidden');
        cntBelum.classList.add('hidden');
    } else {
        btnBelum.className = 'pb-3 border-b-2 border-emerald-600 text-emerald-600 font-bold text-xs flex items-center gap-1.5 transition';
        btnHadir.className = 'pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 font-bold text-xs flex items-center gap-1.5 transition';
        cntBelum.classList.remove('hidden');
        cntHadir.classList.add('hidden');
    }
}

/**
 * Salin Kode QR ke Clipboard
 */
function copyQrCode() {
    if (!SESSION_DATA) return;
    navigator.clipboard.writeText(SESSION_DATA.kode_qr).then(() => {
        alert('Kode QR berhasil disalin: ' + SESSION_DATA.kode_qr);
    });
}

/**
 * Download QR Code sebagai file gambar PNG
 */
function downloadQrCode() {
    const img = document.querySelector('#qrcode-box img') || document.querySelector('#qrcode-box canvas');
    if (!img) return;

    let url = '';
    if (img.tagName.toLowerCase() === 'img') {
        url = img.src;
    } else {
        url = img.toDataURL('image/png');
    }

    const a = document.createElement('a');
    a.href = url;
    a.download = `QR_${SESSION_DATA.kode_qr}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

/**
 * Suara Notifikasi saat ada siswa scan
 */
function playNotificationSound() {
    const toggle = document.getElementById('toggle-sound');
    if (toggle && !toggle.checked) return;

    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.15); // A5
        gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.2);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.2);
    } catch(e) {}
}

function getInitials(name) {
    if (!name) return 'S';
    return name.split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
