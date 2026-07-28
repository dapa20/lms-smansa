<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle   = 'Materi Pembelajaran';
$currentPage = 'materi';
$user        = currentUser();
$isAdmin     = isAdmin();

// ---------------------------------------------------------------------
// FILTER & QUERY KELAS / KURSUS (IMAGE 1 CONCEPT)
// ---------------------------------------------------------------------
$mapelId = $_GET['mapel_id'] ?? '';
$tingkat = $_GET['tingkat'] ?? '';
$kata     = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if (!$isAdmin) {
    // Untuk guru, tampilkan kelas & mapel yang diajar
    $where[] = 'j.guru_id = ?';
    $params[] = $user['id'];
}

if ($mapelId !== '') {
    $where[] = 'j.mapel_id = ?';
    $params[] = $mapelId;
}
if ($tingkat !== '') {
    $where[] = 'k.tingkat = ?';
    $params[] = $tingkat;
}
if ($kata !== '') {
    $where[] = '(k.nama_kelas LIKE ? OR mp.nama_mapel LIKE ?)';
    $params[] = "%$kata%";
    $params[] = "%$kata%";
}

$whereSql = implode(' AND ', $where);

// Query kelas + mapel
$sql = "SELECT DISTINCT k.id AS kelas_id, k.nama_kelas, k.tingkat, k.program, k.tahun_ajaran,
               mp.id AS mapel_id, mp.kode_mapel, mp.nama_mapel,
               u.nama_lengkap AS pengampu
        FROM jadwal_mengajar j
        JOIN kelas k ON k.id = j.kelas_id
        JOIN mata_pelajaran mp ON mp.id = j.mapel_id
        JOIN users u ON u.id = j.guru_id
        WHERE $whereSql
        ORDER BY k.nama_kelas, mp.nama_mapel";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarKursus = $stmt->fetchAll();

// Jika admin & belum ada filter spesifik guru, gabungkan semua kelas & mapel jika query di atas kosong
if ($isAdmin && empty($daftarKursus)) {
    $sqlAdmin = "SELECT k.id AS kelas_id, k.nama_kelas, k.tingkat, k.program, k.tahun_ajaran,
                        mp.id AS mapel_id, mp.kode_mapel, mp.nama_mapel,
                        'Admin / Pengampu' AS pengampu
                 FROM kelas k
                 CROSS JOIN mata_pelajaran mp
                 ORDER BY k.nama_kelas, mp.nama_mapel LIMIT 12";
    $daftarKursus = $pdo->query($sqlAdmin)->fetchAll();
}

$daftarMapel = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

// Generator pola warna header kartu (persis Gambar 1)
$patterns = [
    ['bg' => 'from-pink-400 to-rose-500', 'pattern' => 'radial-gradient(circle, rgba(255,255,255,0.2) 20%, transparent 20%)', 'size' => '20px 20px'],
    ['bg' => 'from-slate-400 to-slate-600', 'pattern' => 'radial-gradient(circle, rgba(255,255,255,0.15) 35%, transparent 35%)', 'size' => '30px 30px'],
    ['bg' => 'from-teal-400 to-emerald-600', 'pattern' => 'linear-gradient(90deg, rgba(255,255,255,0.15) 1px, transparent 1px), linear-gradient(0deg, rgba(255,255,255,0.15) 1px, transparent 1px)', 'size' => '24px 24px'],
    ['bg' => 'from-cyan-500 to-blue-600', 'pattern' => 'radial-gradient(ellipse at center, rgba(255,255,255,0.2) 0%, transparent 70%)', 'size' => '40px 40px'],
    ['bg' => 'from-emerald-500 to-teal-700', 'pattern' => 'radial-gradient(circle at 100% 50%, transparent 20%, rgba(255,255,255,0.15) 21%, rgba(255,255,255,0.15) 34%, transparent 35%)', 'size' => '30px 30px'],
    ['bg' => 'from-sky-400 to-indigo-600', 'pattern' => 'linear-gradient(45deg, rgba(255,255,255,0.15) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.15) 75%)', 'size' => '20px 20px'],
];

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-background">
    <div class="p-md md:p-lg max-w-[1440px] mx-auto">
        <?php renderFlash(); ?>

        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-lg gap-4">
            <div>
                <h2 class="text-headline-lg font-headline-lg font-semibold text-text-main mb-1">Materi Pembelajaran (Kelas Saya)</h2>
                <p class="text-body-md font-body-md text-text-muted">Pilih kelas yang Anda ajar untuk mengelola modul, file, video, dan forum diskusi.</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="get" class="bg-white rounded-xl shadow-sm border border-outline-variant p-md mb-lg flex flex-col md:flex-row gap-3 md:items-center">
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
                <input type="text" name="q" value="<?= h($kata) ?>" placeholder="Cari nama kelas atau mata pelajaran..." class="w-full pl-10 pr-3 py-2 border border-outline-variant rounded-lg text-body-sm bg-white focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            </div>
            <select name="mapel_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm bg-white focus:outline-none focus:border-primary">
                <option value="">Semua Mata Pelajaran</option>
                <?php foreach ($daftarMapel as $mp): ?>
                    <option value="<?= (int)$mp['id'] ?>" <?= (string)$mapelId === (string)$mp['id'] ? 'selected' : '' ?>><?= h($mp['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tingkat" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm bg-white focus:outline-none focus:border-primary">
                <option value="">Semua Tingkat</option>
                <?php foreach (['X', 'XI', 'XII'] as $t): ?>
                    <option value="<?= $t ?>" <?= $tingkat === $t ? 'selected' : '' ?>>Kelas <?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($mapelId !== '' || $tingkat !== '' || $kata !== ''): ?>
                <a href="materi.php" class="text-error font-label-md text-label-md hover:underline flex items-center gap-1">
                    <span class="material-symbols-outlined text-[18px]">close</span> Reset
                </a>
            <?php endif; ?>
        </form>

        <!-- Grid Kursus / Kelas (Match Image 1 Design) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-lg">
            <?php if (empty($daftarKursus)): ?>
                <div class="col-span-full text-center py-xl text-text-muted bg-white rounded-xl border border-outline-variant shadow-sm p-xl">
                    <span class="material-symbols-outlined text-[48px] text-outline mb-2 block">school</span>
                    <p class="font-title-md text-title-md font-semibold text-text-main mb-1">Tidak Ada Kelas Ditemukan</p>
                    <p class="font-body-sm text-body-sm text-text-muted">Belum ada jadwal mengajar atau kelas yang sesuai dengan pencarian Anda.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($daftarKursus as $index => $k): 
                $pat = $patterns[$index % count($patterns)];
                $courseCode = rand(2025201000, 2025209999);
                $secCode = rand(100, 999);

                // Hitung statistik section & materi
                $stmtSec = $pdo->prepare("SELECT COUNT(*) FROM materi_section WHERE kelas_id = ? AND mapel_id = ?");
                $stmtSec->execute([$k['kelas_id'], $k['mapel_id']]);
                $totalSec = (int)$stmtSec->fetchColumn();

                $stmtItems = $pdo->prepare("SELECT COUNT(*) FROM materi_item mi JOIN materi_section ms ON ms.id = mi.section_id WHERE ms.kelas_id = ? AND ms.mapel_id = ?");
                $stmtItems->execute([$k['kelas_id'], $k['mapel_id']]);
                $totalItems = (int)$stmtItems->fetchColumn();
            ?>
                <div class="bg-white rounded-2xl shadow-sm border border-outline-variant/80 overflow-hidden flex flex-col hover:shadow-md transition-all duration-200">
                    
                    <!-- Banner Visual Kartu (Persis Gambar 1) -->
                    <div class="h-36 bg-gradient-to-br <?= $pat['bg'] ?> relative p-4 flex justify-end items-start" 
                         style="background-image: <?= $pat['pattern'] ?>; background-size: <?= $pat['size'] ?>;">
                        <!-- Tombol Titik Tiga (3 Dots Menu) -->
                        <button type="button" class="w-9 h-9 rounded-full bg-primary/90 hover:bg-primary text-white flex items-center justify-center shadow-md transition-colors focus:outline-none" title="Menu Opsi">
                            <span class="material-symbols-outlined text-[20px]">more_vert</span>
                        </button>
                    </div>

                    <!-- Isi Kartu Kursus / Kelas -->
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <!-- Judul Kelas & Mapel -->
                            <h3 class="font-title-md text-title-md font-bold text-text-main leading-snug mb-2 line-clamp-2">
                                <?= h($k['nama_mapel']) ?> - <?= h($k['nama_kelas']) ?> (<?= $courseCode ?>) / (<?= $secCode ?>)
                            </h3>

                            <!-- Subtitle / Kategori -->
                            <p class="font-body-sm text-body-sm text-text-muted flex items-center gap-1 mb-5">
                                <span class="material-symbols-outlined text-[16px] text-text-muted">folder</span>
                                <span>SMA Negeri 1 Bumiayu, <?= h($k['nama_kelas']) ?></span>
                            </p>
                        </div>

                        <div>
                            <!-- Tombol Utama: Enter this course (Persis Gambar 1) -->
                            <a href="materi_detail.php?kelas_id=<?= (int)$k['kelas_id'] ?>&mapel_id=<?= (int)$k['mapel_id'] ?>" 
                               class="w-full bg-[#0066cc] hover:bg-[#0052a3] text-white font-label-lg text-label-lg font-medium py-2.5 px-4 rounded-lg flex items-center justify-center transition-colors shadow-sm mb-4">
                                Enter this course
                            </a>

                            <!-- Footer Status Kartu (Persis Gambar 1) -->
                            <div class="pt-2 border-t border-outline-variant/50 flex justify-between items-center text-label-sm font-label-sm text-text-muted">
                                <span>No completion criteria</span>
                                <span class="font-semibold text-primary"><?= $totalSec ?> Section (<?= $totalItems ?> Content)</span>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</main>
</body>
</html>
