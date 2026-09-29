<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'Rekap Presensi';
$currentPage = 'rekap_presensi';
$user        = currentUser();
$isAdmin     = isAdmin();

$kelasId = null;
$namaKelas = '';

// Jika admin, ambil kelas pertama sebagai default
if ($isAdmin) {
    $kelas = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY tingkat, nama_kelas LIMIT 1")->fetch();
    if ($kelas) {
        $kelasId = $kelas['id'];
        $namaKelas = $kelas['nama_kelas'];
    }
} else {
    // Jika guru, cek apakah dia wali kelas
    $stmtWali = $pdo->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmtWali->execute([$user['id']]);
    $kelas = $stmtWali->fetch();
    if ($kelas) {
        $kelasId = $kelas['id'];
        $namaKelas = $kelas['nama_kelas'];
    }
}

$students = [];
if ($kelasId) {
    $stmt = $pdo->prepare("SELECT nama_lengkap FROM siswa WHERE kelas_id = ? AND status = 'aktif' ORDER BY nama_lengkap ASC");
    $stmt->execute([$kelasId]);
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Jika tabel kosong (misalnya dummy DB kosong), kita berikan data dummy sesuai screenshot untuk preview
if (empty($students) && $kelasId) {
    $students = [
        'AHMAD HUSAIN DEEDAT',
        'AISYAH DEDE JULIANTI',
        'ALLAM MUYASSAR',
        'ARDHIONA SAVA AMELIA',
        'CITRA ZILVANA SATYA MADANI',
        'DWI ESTI RAHAYU',
        'EKA LINTANG YUNIARTIKA SARI',
        'FADHIAH SUNGKAR',
        'FAWAZ FAVIAN ADAHIR',
        'FITRIA NADILA CHOERUNNISA'
    ];
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-slate-50/40">
    <div class="p-6 md:p-8 max-w-[1440px] mx-auto space-y-6">
        <?php renderFlash(); ?>

        <!-- Filter Card -->
        <div class="bg-white/90 backdrop-blur-md rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-slate-200/60 p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="flex-1">
                <h2 class="text-xl font-bold text-slate-800 mb-1">Rekapitulasi Profil Murid</h2>
                <p class="text-sm font-medium text-slate-500">Pilih semester dan bulan untuk menampilkan jumlah presensi, poin, Walas, dan BK.</p>
            </div>
            
            <form id="filterForm" method="GET" class="flex flex-col sm:flex-row items-end gap-4 w-full md:w-auto">
                <div>
                    <label class="block text-[11px] font-bold text-blue-800 uppercase tracking-wider mb-1.5 ml-1">Semester</label>
                    <div class="relative">
                        <select name="semester" class="appearance-none bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl pl-4 pr-10 py-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 shadow-sm w-full sm:w-[150px] transition-all">
                            <option value="1">I (satu)</option>
                            <option value="2">II (dua)</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[11px] font-bold text-blue-800 uppercase tracking-wider mb-1.5 ml-1">Bulan</label>
                    <div class="relative">
                        <select name="bulan" class="appearance-none bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl pl-4 pr-10 py-2.5 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 shadow-sm w-full sm:w-[200px] transition-all">
                            <option value="all">Satu Semester</option>
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                    </div>
                </div>

                <button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-sm hover:shadow transition-all flex items-center gap-2 h-[42px]">
                    <span class="material-symbols-outlined text-[18px]">search</span> Tampilkan
                </button>
            </form>
        </div>

        <!-- Content Card -->
        <div class="bg-white/90 backdrop-blur-md rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-slate-200/60 overflow-hidden flex flex-col">
            
            <?php if (!$kelasId): ?>
                <div class="p-16 flex flex-col items-center justify-center text-center">
                    <div class="w-24 h-24 mb-6 bg-slate-100 rounded-full flex items-center justify-center border border-slate-200">
                        <span class="material-symbols-outlined text-slate-400 text-[48px]">group_off</span>
                    </div>
                    <h3 class="text-xl font-bold text-slate-700 mb-2">Akses Ditolak</h3>
                    <p class="text-slate-500 max-w-sm">Halaman ini khusus untuk Wali Kelas. Saat ini Anda belum ditugaskan sebagai Wali Kelas di kelas mana pun.</p>
                </div>
            <?php else: ?>

            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 mb-0.5">Rekap Profil - Kelas <?= h($namaKelas) ?></h3>
                    <p class="text-sm font-medium text-slate-500">Ringkasan profil murid SMT- I (satu)</p>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" form="filterForm" formaction="../actions/rekap/export_rekap.php?format=pdf" formtarget="_blank" class="bg-rose-500 hover:bg-rose-600 text-white text-sm font-bold px-4 py-2 rounded-xl flex items-center gap-2 shadow-sm transition-all hover:-translate-y-0.5">
                        <span class="material-symbols-outlined text-[18px]">picture_as_pdf</span> Unduh PDF
                    </button>
                    <button type="submit" form="filterForm" formaction="../actions/rekap/export_rekap.php?format=excel" class="bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold px-4 py-2 rounded-xl flex items-center gap-2 shadow-sm transition-all hover:-translate-y-0.5">
                        <span class="material-symbols-outlined text-[18px]">table_view</span> Export Excel
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200">
                            <th rowspan="2" class="px-6 py-4 text-[11px] font-bold text-blue-800 uppercase tracking-wider text-center border-r border-slate-200/60 w-16">No</th>
                            <th rowspan="2" class="px-6 py-4 text-[11px] font-bold text-blue-800 uppercase tracking-wider border-r border-slate-200/60">Nama Siswa</th>
                            <th colspan="5" class="px-6 py-3 text-[11px] font-bold text-blue-800 uppercase tracking-wider text-center border-r border-slate-200/60 border-b border-slate-200/60">Jumlah</th>
                            <th colspan="2" class="px-6 py-3 text-[11px] font-bold text-blue-800 uppercase tracking-wider text-center border-r border-slate-200/60 border-b border-slate-200/60">Sumber Data</th>
                            <th rowspan="2" class="px-6 py-4 text-[11px] font-bold text-blue-800 uppercase tracking-wider text-center">Poin</th>
                        </tr>
                        <tr class="bg-slate-50/80 border-b border-slate-200">
                            <th class="px-4 py-2 text-[11px] font-bold text-indigo-700 text-center border-r border-slate-200/60 w-12">H</th>
                            <th class="px-4 py-2 text-[11px] font-bold text-indigo-700 text-center border-r border-slate-200/60 w-12">S</th>
                            <th class="px-4 py-2 text-[11px] font-bold text-indigo-700 text-center border-r border-slate-200/60 w-12">I</th>
                            <th class="px-4 py-2 text-[11px] font-bold text-indigo-700 text-center border-r border-slate-200/60 w-12">T</th>
                            <th class="px-4 py-2 text-[11px] font-bold text-indigo-700 text-center border-r border-slate-200/60 w-12">A</th>
                            
                            <th class="px-4 py-2 text-[11px] font-bold text-indigo-700 text-center border-r border-slate-200/60">WALAS</th>
                            <th class="px-4 py-2 text-[11px] font-bold text-indigo-700 text-center border-r border-slate-200/60">BK</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(empty($students)): ?>
                            <tr>
                                <td colspan="10" class="px-6 py-8 text-center text-slate-500 font-medium">Belum ada data siswa di kelas ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $index => $name): ?>
                            <tr class="hover:bg-blue-50/30 transition-colors">
                                <td class="px-6 py-4 text-sm font-semibold text-slate-500 text-center border-r border-slate-100/50"><?= $index + 1 ?></td>
                                <td class="px-6 py-4 text-sm font-bold text-slate-700 border-r border-slate-100/50 uppercase"><?= h($name) ?></td>
                                
                                <!-- Kehadiran -->
                                <td class="px-4 py-4 text-sm font-medium text-slate-600 text-center border-r border-slate-100/50">0</td>
                                <td class="px-4 py-4 text-sm font-medium text-slate-600 text-center border-r border-slate-100/50">0</td>
                                <td class="px-4 py-4 text-sm font-medium text-slate-600 text-center border-r border-slate-100/50">0</td>
                                <td class="px-4 py-4 text-sm font-medium text-slate-600 text-center border-r border-slate-100/50">0</td>
                                <td class="px-4 py-4 text-sm font-medium text-slate-600 text-center border-r border-slate-100/50">0</td>
                                
                                <!-- Sumber Data -->
                                <td class="px-4 py-4 text-sm font-medium text-slate-600 text-center border-r border-slate-100/50">0</td>
                                <td class="px-4 py-4 text-sm font-medium text-slate-600 text-center border-r border-slate-100/50">0</td>
                                
                                <!-- Poin -->
                                <td class="px-6 py-4 text-sm font-bold text-slate-700 text-center">0</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
