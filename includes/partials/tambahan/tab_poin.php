<?php
/**
 * Partial: Tab "Poin Kelas / Input Poin Siswa" di halaman data_siswa.php
 * Menampilkan dan mengelola poin pelanggaran & prestasi siswa.
 */

$kelasAktif = (int)($_GET['kelas_id'] ?? 0);
if ($kelasAktif === 0) {
    if (!$isAdmin && !empty($kelasDiajarGuru)) $kelasAktif = (int)$kelasDiajarGuru[0]['id'];
    elseif (!empty($daftarKelas)) $kelasAktif = (int)$daftarKelas[0]['id'];
}

$namaKelasAktif = '';
foreach ($daftarKelas as $k) {
    if ((int)$k['id'] === $kelasAktif) { $namaKelasAktif = $k['nama_kelas']; break; }
}

$daftarSiswaKelas = [];
if ($kelasAktif > 0) {
    $stmt = $pdo->prepare("SELECT id, nama_lengkap, nis FROM siswa WHERE kelas_id = ? AND status='aktif' ORDER BY nama_lengkap");
    $stmt->execute([$kelasAktif]);
    $daftarSiswaKelas = $stmt->fetchAll();
}

// Filter jenis
$filterJenis = $_GET['jenis'] ?? 'semua';
$validJenis = ['semua','pelanggaran','prestasi'];

$whereSql = 'p.kelas_id = ?';
$params = [$kelasAktif];
if (in_array($filterJenis, ['pelanggaran','prestasi'], true)) {
    $whereSql .= ' AND p.jenis = ?';
    $params[] = $filterJenis;
}

$daftarPoin = [];
if ($kelasAktif > 0) {
    $stmt = $pdo->prepare("SELECT p.*, s.nama_lengkap, s.nis, u.nama_lengkap AS pembuat
                            FROM poin_siswa p
                            JOIN siswa s ON s.id = p.siswa_id
                            JOIN users u ON u.id = p.dibuat_oleh
                            WHERE $whereSql
                            ORDER BY p.tanggal DESC, p.created_at DESC
                            LIMIT 200");
    $stmt->execute($params);
    $daftarPoin = $stmt->fetchAll();
}

// Statistik per siswa
$statistikSiswa = [];
if ($kelasAktif > 0) {
    $stmt = $pdo->prepare("SELECT s.id, s.nama_lengkap,
                                COALESCE(SUM(CASE WHEN p.jenis='pelanggaran' THEN p.poin END), 0) AS total_pelanggaran,
                                COALESCE(SUM(CASE WHEN p.jenis='prestasi' THEN p.poin END), 0) AS total_prestasi
                            FROM siswa s
                            LEFT JOIN poin_siswa p ON p.siswa_id = s.id AND p.kelas_id = ?
                            WHERE s.kelas_id = ? AND s.status='aktif'
                            GROUP BY s.id
                            ORDER BY total_pelanggaran DESC, s.nama_lengkap");
    $stmt->execute([$kelasAktif, $kelasAktif]);
    $statistikSiswa = $stmt->fetchAll();
}

// Mode edit
$editData = null;
if (!empty($_GET['edit_poin'])) {
    $stmtE = $pdo->prepare('SELECT * FROM poin_siswa WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_poin']]);
    $editData = $stmtE->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_poin']) || $editData !== null;
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="poin">
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap">Kelas:</label>
        <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[180px]">
            <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasAktif ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap ml-2">Filter:</label>
        <select name="jenis" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[150px]">
            <option value="semua" <?= $filterJenis === 'semua' ? 'selected' : '' ?>>Semua</option>
            <option value="pelanggaran" <?= $filterJenis === 'pelanggaran' ? 'selected' : '' ?>>Pelanggaran</option>
            <option value="prestasi" <?= $filterJenis === 'prestasi' ? 'selected' : '' ?>>Prestasi</option>
        </select>
        <a href="?tab=poin&kelas_id=<?= $kelasAktif ?>&tambah_poin=1" class="ml-auto bg-primary hover:bg-primary-container text-white px-4 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">add</span> Input Poin
        </a>
    </form>
</div>

<!-- Statistik -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-md mb-lg">
    <?php
    $jumlahPelanggaran = 0; $jumlahPrestasi = 0; $totalPoinNegatif = 0; $totalPoinPositif = 0;
    foreach ($statistikSiswa as $ss) {
        $jumlahPelanggaran += (int)$ss['total_pelanggaran'] > 0 ? 1 : 0;
        $jumlahPrestasi += (int)$ss['total_prestasi'] > 0 ? 1 : 0;
        $totalPoinNegatif += (int)$ss['total_pelanggaran'];
        $totalPoinPositif += (int)$ss['total_prestasi'];
    }
    ?>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Siswa Punya Poin</p>
        <p class="text-headline-sm font-bold text-primary"><?= count(array_filter($statistikSiswa, fn($s) => (int)$s['total_pelanggaran']>0 || (int)$s['total_prestasi']>0)) ?></p>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Total Poin Pelanggaran</p>
        <p class="text-headline-sm font-bold text-error">-<?= $totalPoinNegatif ?></p>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Total Poin Prestasi</p>
        <p class="text-headline-sm font-bold text-emerald-600">+<?= $totalPoinPositif ?></p>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Net Poin Kelas</p>
        <p class="text-headline-sm font-bold <?= ($totalPoinPositif - $totalPoinNegatif) >= 0 ? 'text-emerald-600' : 'text-error' ?>">
            <?= ($totalPoinPositif - $totalPoinNegatif) >= 0 ? '+' : '' ?><?= $totalPoinPositif - $totalPoinNegatif ?>
        </p>
    </div>
</div>

<!-- Daftar Poin -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md border-b border-outline-variant/40 flex items-center justify-between flex-wrap">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">star</span>
            Daftar Poin Siswa — <?= h($namaKelasAktif) ?>
        </h3>
        <span class="text-label-sm text-text-muted"><?= count($daftarPoin) ?> entri</span>
    </div>

    <?php if (empty($daftarPoin)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">star</span>
            <p class="text-body-md text-text-muted">Belum ada data poin untuk kelas ini.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                        <th class="p-4 w-28">Tanggal</th>
                        <th class="p-4">Siswa</th>
                        <th class="p-4">Jenis</th>
                        <th class="p-4">Kategori</th>
                        <th class="p-4 text-center w-20">Poin</th>
                        <th class="p-4">Keterangan</th>
                        <th class="p-4 text-center w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-sm">
                    <?php foreach ($daftarPoin as $p): ?>
                        <tr class="hover:bg-surface-bright transition-colors">
                            <td class="p-4 text-text-muted font-mono text-xs"><?= formatTanggalIndo($p['tanggal']) ?></td>
                            <td class="p-4">
                                <div class="font-semibold text-text-main"><?= h($p['nama_lengkap']) ?></div>
                                <div class="text-label-sm text-text-muted"><?= h($p['nis']) ?></div>
                            </td>
                            <td class="p-4">
                                <span class="inline-block px-2 py-0.5 <?= $p['jenis'] === 'pelanggaran' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' ?> rounded text-label-sm font-bold uppercase">
                                    <?= h($p['jenis']) ?>
                                </span>
                            </td>
                            <td class="p-4 font-semibold text-text-main"><?= h($p['kategori']) ?></td>
                            <td class="p-4 text-center font-bold <?= $p['jenis'] === 'pelanggaran' ? 'text-error' : 'text-emerald-600' ?>">
                                <?= $p['jenis'] === 'pelanggaran' ? '-' : '+' ?><?= (int)$p['poin'] ?>
                            </td>
                            <td class="p-4 text-text-muted text-body-sm"><?= h($p['keterangan'] ?? '-') ?></td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="?tab=poin&kelas_id=<?= $kelasAktif ?>&edit_poin=<?= (int)$p['id'] ?>&jenis=<?= $filterJenis ?>" class="text-text-muted hover:text-primary p-1" title="Edit">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </a>
                                    <form action="../actions/siswa_tambahan/poin_hapus.php" method="post" onsubmit="return confirm('Hapus data poin ini?');">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="kelas_id" value="<?= $kelasAktif ?>">
                                        <button type="submit" class="text-text-muted hover:text-error p-1" title="Hapus">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=poin&kelas_id=<?= $kelasAktif ?>&jenis=<?= $filterJenis ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg">
        <form action="../actions/siswa_tambahan/poin_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-bold text-text-main"><?= $editData ? 'Edit Poin' : 'Input Poin' ?></h3>
                <a href="?tab=poin&kelas_id=<?= $kelasAktif ?>&jenis=<?= $filterJenis ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">
            <input type="hidden" name="kelas_id" value="<?= $kelasAktif ?>">

            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Siswa</label>
                    <select required name="siswa_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="">-- Pilih Siswa --</option>
                        <?php foreach ($daftarSiswaKelas as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= ($editData && (int)$editData['siswa_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= h($s['nama_lengkap']) ?> (<?= h($s['nis']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jenis</label>
                        <select name="jenis" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <option value="pelanggaran" <?= ($editData['jenis'] ?? 'pelanggaran') === 'pelanggaran' ? 'selected' : '' ?>>Pelanggaran</option>
                            <option value="prestasi" <?= ($editData['jenis'] ?? '') === 'prestasi' ? 'selected' : '' ?>>Prestasi</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Poin</label>
                        <input type="number" min="0" name="poin" value="<?= h((string)($editData['poin'] ?? 5)) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" required>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Kategori</label>
                    <input required name="kategori" value="<?= h($editData['kategori'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: Terlambat masuk sekolah">
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Tanggal</label>
                    <input required type="date" name="tanggal" value="<?= h($editData['tanggal'] ?? date('Y-m-d')) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Keterangan (opsional)</label>
                    <textarea name="keterangan" rows="2" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Tambahan informasi..."><?= h($editData['keterangan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="?tab=poin&kelas_id=<?= $kelasAktif ?>&jenis=<?= $filterJenis ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>