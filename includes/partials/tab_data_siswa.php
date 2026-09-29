<?php
/**
 * =====================================================================
 * Partial: Tab "Data Siswa" di halaman data_siswa.php
 * Tampilan Full-Width (1 Halaman Penuh) dengan Hover Bar Cari Kelas di Kanan Atas
 * Persis seperti desain referensi tabel siswa.
 * =====================================================================
 */

// ---------------------------------------------------------------------
// FILTER, PENCARIAN & PAGINATION
// ---------------------------------------------------------------------
$tingkat = $_GET['tingkat'] ?? '';
$program = $_GET['program'] ?? '';
$kelasId = $_GET['kelas_id'] ?? '';
$kata    = trim($_GET['q'] ?? '');
$halaman = max(1, (int)($_GET['page'] ?? 1));
$perHalaman = max(5, min(100, (int)($_GET['per_page'] ?? 10))); // default 10 sesuai screenshot
$offset  = ($halaman - 1) * $perHalaman;

// Kelas yang boleh dipilih
$daftarKelasPilihan = $isAdmin ? $daftarKelas : $kelasDiajarGuru;

// Hitung jumlah siswa per kelas untuk dropdown hover bar
$stmtCount = $pdo->query("SELECT kelas_id, COUNT(*) as total FROM siswa WHERE status = 'aktif' GROUP BY kelas_id");
$siswaPerKelas = [];
foreach ($stmtCount->fetchAll() as $sc) {
    $siswaPerKelas[$sc['kelas_id']] = (int)$sc['total'];
}

// Tentukan Nama Kelas yang sedang aktif
$namaKelasAktif = 'Semua Kelas';
if ($kelasId !== '') {
    foreach ($daftarKelas as $k) {
        if ((string)$k['id'] === (string)$kelasId) {
            $namaKelasAktif = $k['nama_kelas'];
            break;
        }
    }
}

$where  = ['1=1'];
$params = [];

// Guru hanya boleh melihat siswa di kelas yang ia ajar
if (!$isAdmin) {
    if (empty($idKelasDiajarGuru)) {
        $where[] = '1=0';
    } elseif ($kelasId !== '' && in_array((int)$kelasId, $idKelasDiajarGuru)) {
        $where[] = 's.kelas_id = ?';
        $params[] = (int)$kelasId;
    } else {
        if ($kelasId !== '') {
            $where[] = 's.kelas_id = ?';
            $params[] = (int)$kelasId;
        } else {
            $placeholder = implode(',', array_fill(0, count($idKelasDiajarGuru), '?'));
            $where[] = "s.kelas_id IN ($placeholder)";
            array_push($params, ...$idKelasDiajarGuru);
        }
    }
} else {
    if ($kelasId !== '')  { $where[] = 's.kelas_id = ?'; $params[] = (int)$kelasId; }
    if ($tingkat !== '')  { $where[] = 'k.tingkat = ?';  $params[] = $tingkat; }
    if ($program !== '')  { $where[] = 'k.program = ?';  $params[] = $program; }
}

if ($kata !== '') {
    $where[] = '(s.nama_lengkap LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)';
    $params[] = "%$kata%";
    $params[] = "%$kata%";
    $params[] = "%$kata%";
}
$whereSql = implode(' AND ', $where);

// Hitung Total Data
$stmtTotal = $pdo->prepare("SELECT COUNT(*) c FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id WHERE $whereSql");
$stmtTotal->execute($params);
$totalData = (int)$stmtTotal->fetch()['c'];
$totalHalaman = max(1, (int)ceil($totalData / $perHalaman));

// Ambil Data Siswa
$sql = "SELECT s.*, k.nama_kelas, k.program, k.tingkat
        FROM siswa s
        LEFT JOIN kelas k ON k.id = s.kelas_id
        WHERE $whereSql
        ORDER BY s.nama_lengkap ASC
        LIMIT $perHalaman OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarSiswa = $stmt->fetchAll();

// Hitung total seluruh siswa sekolah
$totalSemuaSiswa = (int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")->fetchColumn();

// Modal Edit Siswa (Admin)
$editData = null;
if ($isAdmin && !empty($_GET['edit'])) {
    $stmtEdit = $pdo->prepare('SELECT * FROM siswa WHERE id = ?');
    $stmtEdit->execute([(int)$_GET['edit']]);
    $editData = $stmtEdit->fetch() ?: null;
}
$modalTerbuka = $isAdmin && (!empty($_GET['tambah']) || $editData !== null);
?>

<!-- ===================================================================== -->
<!-- CONTAINER DAFTAR SISWA (FULL WIDTH 1 HALAMAN) -->
<!-- ===================================================================== -->
<div class="w-full bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
    
    <!-- TOP HEADER: JUDUL KELAS & HOVER BAR CARI KELAS -->
    <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white">
        
        <!-- Judul Kelas -->
        <div class="space-y-0.5">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight flex items-center gap-2">
                Siswa Kelas <?= h($namaKelasAktif) ?>
            </h2>
            <p class="text-xs text-slate-500 font-medium">
                Daftar data induk siswa, kredensial akun portal, dan status aktif akademik.
            </p>
        </div>

        <!-- Right Side: Action Buttons & HOVER BAR CARI KELAS -->
        <div class="flex items-center gap-3 flex-wrap">
            
            <?php if ($isAdmin): ?>
                <a href="?tambah=1<?= $kelasId !== '' ? '&kelas_id='.$kelasId : '' ?>" 
                   class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-[0.98]">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    Tambah Siswa
                </a>
            <?php endif; ?>

            <!-- ============================================================== -->
            <!-- HOVER BAR CARI KELAS DI KANAN ATAS -->
            <!-- ============================================================== -->
            <div class="relative group" id="hover-bar-kelas">
                
                <!-- Trigger Bar -->
                <button type="button" 
                        class="flex items-center gap-2.5 px-4 py-2 bg-slate-50 hover:bg-slate-100 border border-slate-300 hover:border-emerald-500 rounded-xl shadow-2xs transition-all duration-200">
                    <span class="material-symbols-outlined text-emerald-600 text-[20px]">school</span>
                    <div class="text-left">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block leading-tight">Cari / Pilih Kelas</span>
                        <span class="text-xs font-bold text-slate-800 leading-tight block">
                            <?= h($namaKelasAktif) ?>
                        </span>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 text-[18px] group-hover:rotate-180 transition-transform duration-200">keyboard_arrow_down</span>
                </button>

                <!-- Floating Hover Menu -->
                <div class="absolute right-0 top-full pt-2 w-80 z-50 hidden group-hover:block transition-all duration-200 animate-fadeIn">
                    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xl p-3.5 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-emerald-600 text-[16px]">filter_list</span>
                                Filter Kelas
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium"><?= count($daftarKelasPilihan) ?> Kelas Tersedia</span>
                        </div>

                        <!-- Live Search Input di dalam Hover Bar -->
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                            <input type="text" id="hover-search-kelas" placeholder="Ketik nama kelas (mis. XI.3, X MIPA)..."
                                   onkeyup="filterKelasList(this.value)"
                                   autocomplete="off"
                                   class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                        </div>

                        <!-- Daftar Opsi Kelas -->
                        <div class="max-h-60 overflow-y-auto space-y-1 pr-1 custom-scrollbar" id="daftar-opsi-kelas">
                            <!-- Opsi Semua Kelas -->
                            <a href="data_siswa.php<?= $perHalaman !== 10 ? '?per_page='.$perHalaman : '' ?>" 
                               class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold <?= empty($kelasId) ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-slate-700 hover:bg-slate-50' ?> transition">
                                <span class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[16px]">apps</span>
                                    Semua Kelas
                                </span>
                                <span class="text-[10px] px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full font-bold">
                                    <?= $totalSemuaSiswa ?> Siswa
                                </span>
                            </a>

                            <!-- Loop Kelas -->
                            <?php foreach ($daftarKelasPilihan as $k): 
                                $isCurrent = ((string)$kelasId === (string)$k['id']);
                                $jml = $siswaPerKelas[$k['id']] ?? 0;
                            ?>
                                <a href="data_siswa.php?kelas_id=<?= (int)$k['id'] ?><?= $perHalaman !== 10 ? '&per_page='.$perHalaman : '' ?>" 
                                   data-kelas-name="<?= strtolower($k['nama_kelas']) ?>"
                                   class="item-kelas-hover flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold <?= $isCurrent ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-50' ?> transition">
                                    <span class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[16px] <?= $isCurrent ? 'text-white' : 'text-emerald-600' ?>">class</span>
                                        <?= h($k['nama_kelas']) ?>
                                    </span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full <?= $isCurrent ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-500' ?>">
                                        <?= $jml ?> Siswa
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- ============================================================== -->
    <!-- CONTROLS ROW: SHOW ENTRIES (LEFT) & SEARCH INPUT (RIGHT) -->
    <!-- ============================================================== -->
    <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white">
        
        <!-- Show Entries Dropdown (Persis Screenshot: Show 10 entries) -->
        <div class="flex items-center gap-2 text-xs text-slate-600 font-medium">
            <span>Show</span>
            <div class="relative inline-block">
                <select id="select-per-page" onchange="changePerPage(this.value)" 
                        class="appearance-none pl-3 pr-7 py-1 bg-white border border-slate-300 hover:border-slate-400 rounded-md text-xs font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer">
                    <option value="10" <?= $perHalaman === 10 ? 'selected' : '' ?>>10</option>
                    <option value="25" <?= $perHalaman === 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $perHalaman === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $perHalaman === 100 ? 'selected' : '' ?>>100</option>
                </select>
                <span class="material-symbols-outlined absolute right-1.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[16px]">unfold_more</span>
            </div>
            <span>entries</span>
        </div>

        <!-- Search Input (Persis Screenshot: Search: [ ]) -->
        <div class="flex items-center gap-2">
            <label for="table-search-input" class="text-xs text-slate-600 font-medium">Search:</label>
            <div class="relative">
                <input type="text" id="table-search-input" value="<?= h($kata) ?>" placeholder=""
                       onkeyup="handleInstantSearch(this.value)"
                       class="px-3 py-1 bg-white border border-slate-300 hover:border-slate-400 rounded-md text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 w-48 sm:w-60 transition">
            </div>
        </div>

    </div>

    <!-- ============================================================== -->
    <!-- TABEL DATA SISWA (PERSIS DENGAN SCREENSHOT) -->
    <!-- ============================================================== -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="tabel-siswa">
            
            <!-- Table Header -->
            <thead class="bg-white border-b border-slate-200 text-xs font-bold text-slate-700 select-none">
                <tr>
                    <!-- No. -->
                    <th class="w-12 px-4 py-3.5 text-center">
                        <div class="flex items-center justify-center gap-1 cursor-pointer">
                            <span>No.</span>
                            <span class="material-symbols-outlined text-[14px] text-slate-400">height</span>
                        </div>
                    </th>

                    <!-- Siswa -->
                    <th class="px-4 py-3.5">
                        <div class="flex items-center gap-1 cursor-pointer">
                            <span>Siswa</span>
                            <span class="material-symbols-outlined text-[14px] text-slate-400">height</span>
                        </div>
                    </th>

                    <!-- NIS & NISN -->
                    <th class="w-40 px-4 py-3.5">
                        <div class="flex items-center gap-1 cursor-pointer">
                            <span>NIS &amp; NISN</span>
                            <span class="material-symbols-outlined text-[14px] text-slate-400">height</span>
                        </div>
                    </th>

                    <!-- Username Password -->
                    <th class="w-48 px-4 py-3.5">
                        <div class="flex items-center gap-1 cursor-pointer">
                            <span>Username<br>Password</span>
                            <span class="material-symbols-outlined text-[14px] text-slate-400">height</span>
                        </div>
                    </th>

                    <!-- Status -->
                    <th class="w-32 px-4 py-3.5 text-center">
                        <div class="flex items-center justify-center gap-1 cursor-pointer">
                            <span>Status</span>
                            <span class="material-symbols-outlined text-[14px] text-slate-400">height</span>
                        </div>
                    </th>

                    <!-- Aksi -->
                    <th class="w-24 px-4 py-3.5 text-center">
                        <div class="flex items-center justify-center gap-1 cursor-pointer">
                            <span>Aksi</span>
                            <span class="material-symbols-outlined text-[14px] text-slate-400">height</span>
                        </div>
                    </th>
                </tr>
            </thead>

            <!-- Table Body -->
            <tbody class="divide-y divide-slate-100 text-xs text-slate-800" id="tabel-siswa-body">
                <?php if (empty($daftarSiswa)): ?>
                    <tr>
                        <td colspan="6" class="p-12 text-center text-slate-400 text-xs">
                            Tidak ada data siswa yang ditemukan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $noUrut = $offset + 1;
                    foreach ($daftarSiswa as $s): 
                        $avatarUrl = !empty($s['foto']) 
                            ? APP_URL . '/uploads/avatar/' . rawurlencode($s['foto'])
                            : null;
                        
                        $nis = $s['nis'] ?: '-';
                        $nisn = $s['nisn'] ?: '-';
                        
                        // Password tidak ditampilkan untuk keamanan
                    ?>
                        <tr class="hover:bg-slate-50/80 transition-colors baris-siswa">
                            
                            <!-- No. -->
                            <td class="px-4 py-3 text-center font-medium text-slate-600">
                                <?= $noUrut++ ?>
                            </td>

                            <!-- Siswa (Avatar + Nama + Badge Kelas & Gender) -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <!-- User Image -->
                                    <div class="w-9 h-9 rounded-full overflow-hidden bg-slate-100 border border-slate-200 flex-shrink-0 flex items-center justify-center">
                                        <?php if ($avatarUrl): ?>
                                            <img src="<?= $avatarUrl ?>" alt="Foto" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="material-symbols-outlined text-slate-400 text-[20px]">person</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Nama & Badge -->
                                    <div class="space-y-1">
                                        <div class="font-bold text-slate-900 text-xs uppercase tracking-tight">
                                            <?= h($s['nama_lengkap']) ?>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <!-- Pill Badge Kelas (Cyan) -->
                                            <span class="px-2 py-0.5 bg-[#0284c7] text-white text-[10px] font-bold rounded tracking-wide leading-none">
                                                <?= h($s['nama_kelas'] ?? 'XI.3') ?>
                                            </span>
                                            <!-- Pill Badge Gender (L: Teal, P: Rose) -->
                                            <span class="px-2 py-0.5 <?= ($s['jenis_kelamin'] ?? 'L') === 'P' ? 'bg-[#db2777]' : 'bg-[#0d9488]' ?> text-white text-[10px] font-bold rounded leading-none">
                                                <?= h($s['jenis_kelamin'] ?? 'L') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- NIS & NISN (Stacked) -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-bold text-slate-900 text-xs block leading-tight"><?= h($nis) ?></span>
                                <span class="font-bold text-slate-900 text-xs block leading-tight mt-0.5"><?= h($nisn) ?></span>
                            </td>

                            <!-- Username (Password disembunyikan untuk keamanan) -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="text-xs font-semibold text-slate-700 block leading-tight"><?= h($nisn) ?></span>
                                <span class="text-[10px] text-slate-400 block leading-tight mt-0.5">Login: NISN / Password Default</span>
                            </td>

                            <!-- Status (Stacked Badges: Kelas & Aktif) -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="flex flex-col items-center gap-1">
                                    <!-- Badge Kelas -->
                                    <span class="px-2.5 py-0.5 bg-[#0284c7] text-white text-[10px] font-bold rounded uppercase tracking-wider leading-none">
                                        <?= h($s['nama_kelas'] ?? 'XI.3') ?>
                                    </span>
                                    <!-- Badge Status -->
                                    <span class="px-2.5 py-0.5 <?= ($s['status'] ?? 'aktif') === 'aktif' ? 'bg-[#16a34a]' : 'bg-slate-400' ?> text-white text-[10px] font-bold rounded uppercase tracking-wider leading-none">
                                        <?= ucfirst(h($s['status'] ?? 'Aktif')) ?>
                                    </span>
                                </div>
                            </td>

                            <!-- Aksi (Yellow Button Edit) -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="?edit=<?= (int)$s['id'] ?><?= $kelasId !== '' ? '&kelas_id='.$kelasId : '' ?><?= $perHalaman !== 10 ? '&per_page='.$perHalaman : '' ?>" 
                                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-[#eab308] hover:bg-[#ca8a04] text-slate-900 font-bold text-xs rounded-md shadow-2xs transition active:scale-[0.97]"
                                       title="Edit Data Siswa">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                        <span>Edit</span>
                                    </a>

                                    <?php if ($isAdmin): ?>
                                        <form action="../actions/siswa/siswa_hapus.php" method="post" class="inline" 
                                              onsubmit="return confirm('Hapus data siswa \'<?= h(addslashes($s['nama_lengkap'])) ?>\'? Tindakan ini tidak bisa dibatalkan.');">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-md transition" title="Hapus Siswa">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ============================================================== -->
    <!-- BOTTOM FOOTER: SHOWING ENTRIES & PAGINATION -->
    <!-- ============================================================== -->
    <div class="px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white text-xs text-slate-600">
        
        <!-- Info Showing X to Y of Z entries -->
        <div>
            Showing <strong class="text-slate-800"><?= $totalData ? $offset + 1 : 0 ?></strong> to 
            <strong class="text-slate-800"><?= min($offset + $perHalaman, $totalData) ?></strong> of 
            <strong class="text-slate-800"><?= $totalData ?></strong> entries
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalHalaman > 1): ?>
            <div class="flex items-center gap-1">
                <?php
                    $qp = $_GET;
                    // Tombol Previous
                    if ($halaman > 1):
                        $qp['page'] = $halaman - 1;
                ?>
                    <a href="data_siswa.php?<?= http_build_query($qp) ?>" class="px-3 py-1.5 border border-slate-200 rounded-md font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Previous
                    </a>
                <?php else: ?>
                    <span class="px-3 py-1.5 border border-slate-100 rounded-md font-semibold text-slate-300 cursor-not-allowed">
                        Previous
                    </span>
                <?php endif; ?>

                <!-- Nomor Halaman -->
                <?php
                    $startP = max(1, $halaman - 2);
                    $endP = min($totalHalaman, $halaman + 2);
                    for ($p = $startP; $p <= $endP; $p++):
                        $qp['page'] = $p;
                ?>
                    <a href="data_siswa.php?<?= http_build_query($qp) ?>" 
                       class="px-3 py-1.5 border rounded-md font-bold transition <?= $p === $halaman ? 'bg-[#0284c7] text-white border-[#0284c7]' : 'border-slate-200 text-slate-700 hover:bg-slate-50' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <!-- Tombol Next -->
                <?php
                    if ($halaman < $totalHalaman):
                        $qp['page'] = $halaman + 1;
                ?>
                    <a href="data_siswa.php?<?= http_build_query($qp) ?>" class="px-3 py-1.5 border border-slate-200 rounded-md font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Next
                    </a>
                <?php else: ?>
                    <span class="px-3 py-1.5 border border-slate-100 rounded-md font-semibold text-slate-300 cursor-not-allowed">
                        Next
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- ============================================================= -->
<!-- MODAL TAMBAH / EDIT SISWA (ADMIN)                             -->
<!-- ============================================================= -->
<?php if ($isAdmin): ?>
<div id="modal-siswa" class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='data_siswa.php<?= $kelasId !== '' ? '?kelas_id='.$kelasId : '' ?>'"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto border border-slate-200">
        <form action="../actions/siswa/siswa_simpan.php" method="post" class="p-6">
            <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-slate-800"><?= $editData ? 'Edit Data Siswa' : 'Tambah Siswa Baru' ?></h3>
                <a href="data_siswa.php<?= $kelasId !== '' ? '?kelas_id='.$kelasId : '' ?>" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </a>
            </div>

            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">
            <?php if ($kelasId !== ''): ?><input type="hidden" name="redirect_kelas_id" value="<?= (int)$kelasId ?>"><?php endif; ?>

            <div class="space-y-3.5 text-xs">
                <div>
                    <label class="font-bold text-slate-700 block mb-1">Nama Lengkap Siswa <span class="text-red-500">*</span></label>
                    <input required name="nama_lengkap" value="<?= h($editData['nama_lengkap'] ?? '') ?>" 
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none text-xs" 
                           placeholder="Contoh: AHMAD HUSAIN DEEDAT">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">NIS <span class="text-red-500">*</span></label>
                        <input required name="nis" value="<?= h($editData['nis'] ?? '') ?>" 
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none text-xs" 
                               placeholder="Contoh: 014152">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">NISN <span class="text-red-500">*</span></label>
                        <input required name="nisn" value="<?= h($editData['nisn'] ?? '') ?>" 
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none text-xs" 
                               placeholder="Contoh: 0097279624">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-emerald-500 outline-none text-xs font-semibold">
                            <option value="L" <?= ($editData['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki (L)</option>
                            <option value="P" <?= ($editData['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan (P)</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Status Siswa</label>
                        <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-emerald-500 outline-none text-xs font-semibold">
                            <?php foreach (['aktif' => 'Aktif', 'pindah' => 'Pindah', 'lulus' => 'Lulus'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($editData['status'] ?? 'aktif') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="font-bold text-slate-700 block mb-1">Kelas <span class="text-red-500">*</span></label>
                    <select required name="kelas_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-emerald-500 outline-none text-xs font-semibold">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($daftarKelas as $k): ?>
                            <option value="<?= (int)$k['id'] ?>" <?= ((int)($editData['kelas_id'] ?? $kelasId)) === (int)$k['id'] ? 'selected' : '' ?>>
                                <?= h($k['nama_kelas']) ?> (<?= h($k['tingkat'] ?? '') ?> <?= h($k['program'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 mt-4 border-t border-slate-100">
                <a href="data_siswa.php<?= $kelasId !== '' ? '?kelas_id='.$kelasId : '' ?>" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition">Batal</a>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition shadow-xs">Simpan Data</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- JAVASCRIPT: FILTER KELAS DI HOVER BAR & SEARCH TABLE -->
<script>
/**
 * Filter daftar kelas di dalam Hover Bar live saat diketik
 */
function filterKelasList(val) {
    const query = val.toLowerCase().trim();
    const items = document.querySelectorAll('#daftar-opsi-kelas .item-kelas-hover');
    items.forEach(item => {
        const name = item.getAttribute('data-kelas-name') || '';
        item.style.display = name.includes(query) ? 'flex' : 'none';
    });
}

/**
 * Ubah jumlah per page
 */
function changePerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}

/**
 * Instant Client-Side Search
 */
let searchTimer = null;
function handleInstantSearch(val) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const query = val.toLowerCase().trim();
        const rows = document.querySelectorAll('#tabel-siswa-body .baris-siswa');
        let visibleCount = 0;

        rows.forEach(r => {
            const text = r.textContent.toLowerCase();
            const match = text.includes(query);
            r.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        // Jika user tekan Enter atau kosongkan, sinkronkan ke query URL jika perlu
    }, 150);
}

// Support Enter on Search box
document.getElementById('table-search-input')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        const url = new URL(window.location.href);
        if (e.target.value.trim() !== '') {
            url.searchParams.set('q', e.target.value.trim());
        } else {
            url.searchParams.delete('q');
        }
        url.searchParams.set('page', 1);
        window.location.href = url.toString();
    }
});
</script>
