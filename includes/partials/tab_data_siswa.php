<?php
/**
 * Partial: Tab "Data Siswa" di halaman data_siswa.php
 * Variabel yang sudah tersedia dari file pemanggil: $pdo, $isAdmin, $user,
 * $daftarKelas, $kelasDiajarGuru, $idKelasDiajarGuru.
 */

// ---------------------------------------------------------------------
// FILTER & PENCARIAN
// ---------------------------------------------------------------------
$tingkat = $_GET['tingkat'] ?? '';
$program = $_GET['program'] ?? '';
$kelasId = $_GET['kelas_id'] ?? '';
$kata    = trim($_GET['q'] ?? '');
$halaman = max(1, (int)($_GET['page'] ?? 1));
$perHalaman = 15;
$offset  = ($halaman - 1) * $perHalaman;

$where  = ['1=1'];
$params = [];

// Guru hanya boleh melihat siswa di kelas yang ia ajar.
if (!$isAdmin) {
    if (empty($idKelasDiajarGuru)) {
        $where[] = '1=0';
    } elseif ($kelasId !== '' && in_array((int)$kelasId, $idKelasDiajarGuru)) {
        // Guru memilih satu kelas tertentu
        $where[] = 's.kelas_id = ?';
        $params[] = (int)$kelasId;
    } else {
        // Default: tampilkan semua kelas yang diajar
        $placeholder = implode(',', array_fill(0, count($idKelasDiajarGuru), '?'));
        $where[] = "s.kelas_id IN ($placeholder)";
        array_push($params, ...$idKelasDiajarGuru);
    }
}

if ($isAdmin) {
    if ($tingkat !== '')  { $where[] = 'k.tingkat = ?';  $params[] = $tingkat; }
    if ($program !== '')  { $where[] = 'k.program = ?';  $params[] = $program; }
    if ($kelasId !== '')  { $where[] = 's.kelas_id = ?'; $params[] = $kelasId; }
}
if ($kata !== '')     { $where[] = '(s.nama_lengkap LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)'; $params[] = "%$kata%"; $params[] = "%$kata%"; $params[] = "%$kata%"; }
$whereSql = implode(' AND ', $where);

$stmtTotal = $pdo->prepare("SELECT COUNT(*) c FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id WHERE $whereSql");
$stmtTotal->execute($params);
$totalData = (int)$stmtTotal->fetch()['c'];
$totalHalaman = max(1, (int)ceil($totalData / $perHalaman));

$sql = "SELECT s.*, k.nama_kelas, k.program, k.tingkat,
               (SELECT ROUND(SUM(status='hadir') / COUNT(*) * 100)
                FROM kehadiran WHERE siswa_id = s.id) AS persen_hadir
        FROM siswa s
        LEFT JOIN kelas k ON k.id = s.kelas_id
        WHERE $whereSql
        ORDER BY s.nama_lengkap ASC
        LIMIT $perHalaman OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarSiswa = $stmt->fetchAll();

// ---------------------------------------------------------------------
// STATISTIK RINGKAS (dibatasi sesuai peran)
// ---------------------------------------------------------------------
if ($isAdmin) {
    $totalTerdaftar = (int)$pdo->query("SELECT COUNT(*) c FROM siswa WHERE status='aktif'")->fetch()['c'];
    $rataKehadiran  = (int)($pdo->query("SELECT ROUND(SUM(status='hadir')/COUNT(*)*100) c FROM kehadiran")->fetch()['c'] ?? 0);
    $pendingReview  = (int)$pdo->query("SELECT COUNT(*) c FROM pengumpulan_tugas WHERE status='terkumpul'")->fetch()['c'];
} elseif (!empty($idKelasDiajarGuru)) {
    $placeholder = implode(',', array_fill(0, count($idKelasDiajarGuru), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM siswa WHERE status='aktif' AND kelas_id IN ($placeholder)");
    $stmt->execute($idKelasDiajarGuru);
    $totalTerdaftar = (int)$stmt->fetch()['c'];

    $stmt = $pdo->prepare("SELECT ROUND(SUM(status='hadir')/COUNT(*)*100) c FROM kehadiran WHERE kelas_id IN ($placeholder)");
    $stmt->execute($idKelasDiajarGuru);
    $rataKehadiran = (int)($stmt->fetch()['c'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM pengumpulan_tugas pt JOIN tugas_ujian t ON t.id = pt.tugas_id WHERE pt.status='terkumpul' AND t.dibuat_oleh = ?");
    $stmt->execute([$user['id']]);
    $pendingReview = (int)$stmt->fetch()['c'];
} else {
    $totalTerdaftar = 0; $rataKehadiran = 0; $pendingReview = 0;
}

// ---------------------------------------------------------------------
// MODE EDIT SISWA (khusus Admin)
// ---------------------------------------------------------------------
$editData = null;
if ($isAdmin && !empty($_GET['edit'])) {
    $stmtEdit = $pdo->prepare('SELECT * FROM siswa WHERE id = ?');
    $stmtEdit->execute([(int)$_GET['edit']]);
    $editData = $stmtEdit->fetch() ?: null;
}
$modalTerbuka = $isAdmin && (!empty($_GET['tambah']) || $editData !== null);

// ---------------------------------------------------------------------
// MODE KEHADIRAN (khusus Guru): ?kehadiran=1 (pilih kelas) atau
// ?kehadiran=1&kelas_id=X (isi daftar hadir kelas tsb)
// ---------------------------------------------------------------------
$modeKehadiran = !$isAdmin && !empty($_GET['kehadiran']);
$kelasKehadiran = $_GET['kelas_id_hadir'] ?? '';
$tanggalKehadiran = $_GET['tanggal_hadir'] ?? date('Y-m-d');
$daftarSiswaKehadiran = [];
if ($modeKehadiran && $kelasKehadiran !== '' && in_array((int)$kelasKehadiran, $idKelasDiajarGuru)) {
    $stmt = $pdo->prepare("SELECT s.id, s.nama_lengkap, s.nis,
                                   kh.status AS status_tercatat
                            FROM siswa s
                            LEFT JOIN kehadiran kh ON kh.siswa_id = s.id AND kh.tanggal = ?
                            WHERE s.kelas_id = ? AND s.status = 'aktif'
                            ORDER BY s.nama_lengkap");
    $stmt->execute([$tanggalKehadiran, $kelasKehadiran]);
    $daftarSiswaKehadiran = $stmt->fetchAll();
}
?>
<div class="flex justify-end mb-md -mt-2">
    <?php if ($isAdmin): ?>
        <a href="?tambah=1" class="bg-primary hover:bg-surface-tint text-on-primary px-6 py-2 rounded-DEFAULT text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
            <span class="material-symbols-outlined">add</span> Tambah Siswa
        </a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-12 gap-6">
    <!-- Kolom Kiri: Filter & Ringkasan -->
    <div class="md:col-span-3 flex flex-col gap-6">

        <?php if ($isAdmin): ?>
        <!-- Filter lengkap untuk Admin -->
        <form method="get" class="bg-surface-white rounded-xl p-lg shadow-[0px_4px_20px_rgba(0,0,0,0.05)]">
            <h3 class="text-headline-sm font-headline-sm text-text-main mb-4 border-b border-outline-variant pb-2">Filter</h3>
            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-muted block mb-1">Tingkat Kelas</label>
                    <select name="tingkat" onchange="this.form.submit()" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-body-md font-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua Tingkat (X, XI, XII)</option>
                        <option value="X" <?= $tingkat === 'X' ? 'selected' : '' ?>>Kelas X</option>
                        <option value="XI" <?= $tingkat === 'XI' ? 'selected' : '' ?>>Kelas XI</option>
                        <option value="XII" <?= $tingkat === 'XII' ? 'selected' : '' ?>>Kelas XII</option>
                    </select>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-muted block mb-1">Program / Jurusan</label>
                    <select name="program" onchange="this.form.submit()" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-body-md font-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <option value="">Semua Program</option>
                        <option value="MIPA" <?= $program === 'MIPA' ? 'selected' : '' ?>>MIPA</option>
                        <option value="IPS" <?= $program === 'IPS' ? 'selected' : '' ?>>IPS</option>
                    </select>
                </div>
                <?php if ($kata !== ''): ?><input type="hidden" name="q" value="<?= h($kata) ?>"><?php endif; ?>
                <?php if ($tingkat !== '' || $program !== '' || $kelasId !== ''): ?>
                    <a href="data_siswa.php" class="text-label-md font-label-md text-primary hover:underline flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">close</span> Reset Filter
                    </a>
                <?php endif; ?>
            </div>
        </form>
        <?php else: ?>
        <!-- Pilih Kelas untuk Guru (hanya kelas yang diajar) -->
        <div class="bg-surface-white rounded-xl p-lg shadow-[0px_4px_20px_rgba(0,0,0,0.05)]">
            <h3 class="text-headline-sm font-headline-sm text-text-main mb-4 border-b border-outline-variant pb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">class</span>
                Pilih Kelas
            </h3>
            <?php if (empty($kelasDiajarGuru)): ?>
                <p class="text-body-sm text-text-muted">Anda belum ditugaskan mengajar kelas manapun. Hubungi Admin.</p>
            <?php else: ?>
            <div class="space-y-2">
                <a href="data_siswa.php<?= $kata !== '' ? '?q='.urlencode($kata) : '' ?>"
                   class="flex items-center gap-2 px-3 py-2 rounded-lg text-body-sm font-medium transition-colors <?= $kelasId === '' ? 'bg-primary text-white' : 'text-text-muted hover:bg-surface-container-low' ?>">
                    <span class="material-symbols-outlined text-[16px]">groups</span>
                    Semua Kelas Saya
                </a>
                <?php foreach ($kelasDiajarGuru as $k): ?>
                <a href="data_siswa.php?kelas_id=<?= (int)$k['id'] ?><?= $kata !== '' ? '&q='.urlencode($kata) : '' ?>"
                   class="flex items-center gap-2 px-3 py-2 rounded-lg text-body-sm font-medium transition-colors <?= (string)$kelasId === (string)$k['id'] ? 'bg-primary text-white' : 'text-text-muted hover:bg-surface-container-low' ?>">
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <?= h($k['nama_kelas']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Ringkasan -->
        <div class="bg-surface-white rounded-xl p-lg shadow-[0px_4px_20px_rgba(0,0,0,0.05)]">
            <h3 class="text-headline-sm font-headline-sm text-text-main mb-4 border-b border-outline-variant pb-2">Ringkasan</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-body-md font-body-md text-text-muted">Total Siswa Aktif</span>
                    <span class="text-label-lg font-label-lg font-semibold text-text-main"><?= number_format($totalTerdaftar, 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-body-md font-body-md text-text-muted">Rata-rata Kehadiran</span>
                    <span class="text-label-lg font-label-lg font-semibold text-primary"><?= $rataKehadiran ?>%</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-body-md font-body-md text-text-muted">Tugas Menunggu Nilai</span>
                    <span class="text-label-lg font-label-lg font-semibold text-error"><?= $pendingReview ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Tabel Siswa -->
    <div class="md:col-span-9">
        <div class="bg-surface-white rounded-xl shadow-[0px_4px_20px_rgba(0,0,0,0.05)] overflow-hidden border border-surface-variant">
            <div class="p-4 border-b border-outline-variant flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between bg-surface-bright">
                <form method="get" class="relative flex-1 max-w-sm">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
                    <input type="text" name="q" value="<?= h($kata) ?>" placeholder="Cari nama, NIS, atau NISN..." class="w-full pl-10 pr-3 py-2 border border-outline-variant rounded-lg text-body-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <?php if ($tingkat !== ''): ?><input type="hidden" name="tingkat" value="<?= h($tingkat) ?>"><?php endif; ?>
                    <?php if ($program !== ''): ?><input type="hidden" name="program" value="<?= h($program) ?>"><?php endif; ?>
                </form>
                <span class="text-label-md font-label-md text-text-muted"><?= number_format($totalData, 0, ',', '.') ?> siswa ditemukan</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider">
                            <th class="p-4 font-medium">Data Siswa</th>
                            <th class="p-4 font-medium hidden sm:table-cell">NIS / NISN</th>
                            <th class="p-4 font-medium hidden md:table-cell">Kelas</th>
                            <th class="p-4 font-medium">Kehadiran</th>
                            <?php if ($isAdmin): ?><th class="p-4 font-medium text-right">Aksi</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="text-body-sm font-body-sm text-text-main divide-y divide-outline-variant">
                        <?php if (empty($daftarSiswa)): ?>
                            <tr><td colspan="<?= $isAdmin ? 5 : 4 ?>" class="p-8 text-center text-text-muted">
                                <?= (!$isAdmin && empty($idKelasDiajarGuru)) ? 'Anda belum ditugaskan mengajar kelas manapun. Hubungi Admin untuk pengaturan jadwal.' : 'Tidak ada data siswa yang cocok dengan filter.' ?>
                            </td></tr>
                        <?php endif; ?>
                        <?php foreach ($daftarSiswa as $s): ?>
                            <?php
                                $persen = $s['persen_hadir'];
                                $barColor = $persen === null ? 'bg-outline-variant' : ($persen < 75 ? 'bg-error' : ($persen < 90 ? 'bg-secondary-container' : 'bg-primary'));
                                $rowBg = $persen !== null && $persen < 75 ? 'bg-error-container/10' : '';
                            ?>
                            <tr class="hover:bg-surface-bright transition-colors group <?= $rowBg ?>">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container font-bold text-label-md flex-shrink-0">
                                            <?= h(inisialNama($s['nama_lengkap'])) ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-text-main group-hover:text-primary transition-colors"><?= h($s['nama_lengkap']) ?></div>
                                            <div class="text-text-muted text-label-md font-label-md"><?= h($s['program'] ?? '-') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 hidden sm:table-cell text-text-muted">
                                    <?= h($s['nis']) ?><br><span class="text-xs"><?= h($s['nisn']) ?></span>
                                </td>
                                <td class="p-4 hidden md:table-cell">
                                    <span class="inline-block px-2 py-1 rounded bg-surface-container text-text-main text-xs font-medium"><?= h($s['nama_kelas'] ?? 'Belum ada kelas') ?></span>
                                </td>
                                <td class="p-4">
                                    <?php if ($persen === null): ?>
                                        <span class="text-label-md text-text-muted">Belum ada data</span>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2">
                                            <div class="w-16 h-2 bg-surface-container rounded-full overflow-hidden">
                                                <div class="h-full <?= $barColor ?>" style="width: <?= (int)$persen ?>%"></div>
                                            </div>
                                            <span class="text-label-md font-label-md <?= $persen < 75 ? 'text-error' : 'text-text-muted' ?>"><?= (int)$persen ?>%</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isAdmin): ?>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-1">
                                        <a href="?edit=<?= (int)$s['id'] ?>" class="text-text-muted hover:text-primary transition-colors p-1" title="Edit">
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </a>
                                        <form action="../actions/siswa/siswa_hapus.php" method="post" onsubmit="return confirm('Hapus data siswa \'<?= h(addslashes($s['nama_lengkap'])) ?>\'? Tindakan ini tidak bisa dibatalkan.');">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="text-text-muted hover:text-error transition-colors p-1" title="Hapus">
                                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-outline-variant flex justify-between items-center bg-surface-bright text-label-md font-label-md text-text-muted">
                <span>Menampilkan <?= $totalData ? $offset + 1 : 0 ?>-<?= min($offset + $perHalaman, $totalData) ?> dari <?= $totalData ?></span>
                <div class="flex gap-1">
                    <?php
                        $queryTanpaPage = $_GET;
                        for ($p = 1; $p <= $totalHalaman; $p++):
                            $queryTanpaPage['page'] = $p;
                            $urlHalaman = 'data_siswa.php?' . http_build_query($queryTanpaPage);
                    ?>
                        <a href="<?= h($urlHalaman) ?>" class="px-2 py-1 rounded <?= $p === $halaman ? 'bg-primary text-white' : 'hover:bg-surface-container' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- ============================================================= -->
<!-- MODAL TAMBAH / EDIT SISWA (khusus Admin)                      -->
<!-- ============================================================= -->
<div id="modal-siswa" class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='data_siswa.php'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <form action="../actions/siswa/siswa_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-headline-sm text-text-main"><?= $editData ? 'Edit Data Siswa' : 'Tambah Siswa Baru' ?></h3>
                <a href="data_siswa.php" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>

            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">

            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Nama Lengkap</label>
                    <input required name="nama_lengkap" value="<?= h($editData['nama_lengkap'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" placeholder="Contoh: Budi Santoso">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">NIS</label>
                        <input required name="nis" value="<?= h($editData['nis'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">NISN</label>
                        <input required name="nisn" value="<?= h($editData['nisn'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                            <option value="L" <?= ($editData['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= ($editData['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                            <?php foreach (['aktif' => 'Aktif', 'pindah' => 'Pindah', 'lulus' => 'Lulus'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($editData['status'] ?? 'aktif') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Kelas</label>
                    <select required name="kelas_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($daftarKelas as $k): ?>
                            <option value="<?= (int)$k['id'] ?>" <?= (int)($editData['kelas_id'] ?? 0) === (int)$k['id'] ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="data_siswa.php" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
