<?php
/**
 * Partial: Halaman "Arsip Rapor" — dipanggil dari export_nilai.php?tab=arsip
 * Mengelola arsip rapor siswa per semester.
 */
$pageTitle = 'Arsip Rapor';
$currentPage = 'arsip';
$user = currentUser();
$isAdmin = isAdmin();

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$semester = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? '2024/2025';
if ($kelasId === 0) {
    $firstKelas = $pdo->query("SELECT id FROM kelas ORDER BY tingkat, nama_kelas LIMIT 1")->fetch();
    if ($firstKelas) $kelasId = (int)$firstKelas['id'];
}

$daftarKelas = $pdo->query('SELECT * FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();

$whereParams = [];
$whereSql = '1=1';
if ($kelasId) { $whereSql .= ' AND ar.kelas_id = ?'; $whereParams[] = $kelasId; }
if (in_array($semester, ['Ganjil','Genap'], true)) { $whereSql .= ' AND ar.semester = ?'; $whereParams[] = $semester; }
if ($tahunAjaran) { $whereSql .= ' AND ar.tahun_ajaran = ?'; $whereParams[] = $tahunAjaran; }

$stmt = $pdo->prepare("SELECT ar.*, s.nama_lengkap, s.nis, k.nama_kelas, u.nama_lengkap AS pembuat
                       FROM arsip_rapor ar
                       JOIN siswa s ON s.id = ar.siswa_id
                       JOIN kelas k ON k.id = ar.kelas_id
                       JOIN users u ON u.id = ar.dibuat_oleh
                       WHERE $whereSql
                       ORDER BY ar.tahun_ajaran DESC, ar.semester DESC, s.nama_lengkap
                       LIMIT 500");
$stmt->execute($whereParams);
$daftarArsip = $stmt->fetchAll();

// Mode edit
$editData = null;
if (!empty($_GET['edit_arsip'])) {
    $stmtE = $pdo->prepare('SELECT * FROM arsip_rapor WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_arsip']]);
    $editData = $stmtE->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_arsip']) || $editData !== null;

// Daftar siswa kelas
$daftarSiswaKelas = [];
if ($kelasId) {
    $stmtS = $pdo->prepare("SELECT id, nama_lengkap, nis FROM siswa WHERE kelas_id = ? AND status='aktif' ORDER BY nama_lengkap");
    $stmtS->execute([$kelasId]);
    $daftarSiswaKelas = $stmtS->fetchAll();
}

require_once __DIR__ . '/../../head.php';
require_once __DIR__ . '/../../sidebar.php';
require_once __DIR__ . '/../../topbar.php';
?>

<main class="pt-16 md:ml-[280px] min-h-screen bg-background">
    <div class="p-md md:p-lg max-w-[1440px] mx-auto">
        <?php renderFlash(); ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-lg gap-4">
            <div>
                <h2 class="text-headline-lg font-headline-lg font-semibold text-text-main mb-1">Arsip Rapor</h2>
                <p class="text-body-md font-body-md text-text-muted">Arsipkan dan kelola metadata rapor siswa yang sudah dicetak/dibagikan.</p>
            </div>
            <a href="?tab=arsip&tambah_arsip=1&kelas_id=<?= $kelasId ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>" class="bg-primary hover:bg-primary-container text-white px-6 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
                <span class="material-symbols-outlined">archive</span> Tambah Arsip
            </a>
        </div>

        <!-- Filter -->
        <form method="get" class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40 flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="arsip">
            <label class="text-label-md text-text-main">Kelas:</label>
            <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <option value="">Semua</option>
                <?php foreach ($daftarKelas as $k): ?>
                    <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasId ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="text-label-md text-text-main">Semester:</label>
            <select name="semester" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <option value="">Semua</option>
                <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
            </select>
            <label class="text-label-md text-text-main">Tahun Ajaran:</label>
            <select name="tahun_ajaran" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <option value="">Semua</option>
                <?php foreach (['2023/2024', '2024/2025', '2025/2026'] as $ta): ?>
                    <option value="<?= $ta ?>" <?= $tahunAjaran === $ta ? 'selected' : '' ?>><?= $ta ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- Statistik -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-md mb-lg">
            <?php
            $statCetak = 0; $statArsip = 0;
            foreach ($daftarArsip as $a) {
                if ($a['status'] === 'cetak') $statCetak++;
                elseif ($a['status'] === 'diarsipkan') $statArsip++;
            }
            ?>
            <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
                <p class="text-label-sm text-text-muted">Total Arsip</p>
                <p class="text-headline-sm font-bold text-primary"><?= count($daftarArsip) ?></p>
            </div>
            <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
                <p class="text-label-sm text-text-muted">Status Cetak</p>
                <p class="text-headline-sm font-bold text-emerald-600"><?= $statCetak ?></p>
            </div>
            <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
                <p class="text-label-sm text-text-muted">Sudah Diarsipkan</p>
                <p class="text-headline-sm font-bold text-amber-600"><?= $statArsip ?></p>
            </div>
            <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
                <p class="text-label-sm text-text-muted">Rata-rata Nilai</p>
                <?php
                $rataAll = array_filter(array_column($daftarArsip, 'rata_rata'));
                $avg = count($rataAll) > 0 ? round(array_sum($rataAll) / count($rataAll), 2) : null;
                ?>
                <p class="text-headline-sm font-bold text-text-main"><?= $avg ?? '-' ?></p>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
            <div class="px-lg py-md">
                <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">archive</span>
                    Daftar Arsip Rapor
                </h3>
            </div>

            <?php if (empty($daftarArsip)): ?>
                <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                    <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">archive</span>
                    <p class="text-body-md text-text-muted">Belum ada data arsip rapor.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                                <th class="p-4">Siswa</th>
                                <th class="p-4">Kelas</th>
                                <th class="p-4 text-center">Semester</th>
                                <th class="p-4 text-center">Tahun Ajaran</th>
                                <th class="p-4 text-center">Rata-rata</th>
                                <th class="p-4 text-center">Peringkat</th>
                                <th class="p-4 text-center">Status</th>
                                <th class="p-4 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant text-body-sm">
                            <?php foreach ($daftarArsip as $a): ?>
                                <tr class="hover:bg-surface-bright transition-colors">
                                    <td class="p-4">
                                        <div class="font-semibold text-text-main"><?= h($a['nama_lengkap']) ?></div>
                                        <div class="text-label-sm text-text-muted"><?= h($a['nis']) ?></div>
                                    </td>
                                    <td class="p-4 text-text-main"><?= h($a['nama_kelas']) ?></td>
                                    <td class="p-4 text-center text-text-muted"><?= h($a['semester']) ?></td>
                                    <td class="p-4 text-center text-text-muted"><?= h($a['tahun_ajaran']) ?></td>
                                    <td class="p-4 text-center font-bold text-primary"><?= $a['rata_rata'] !== null ? rtrim(rtrim(number_format((float)$a['rata_rata'], 2), '0'), '.') : '-' ?></td>
                                    <td class="p-4 text-center text-text-main"><?= $a['peringkat'] ? '#'.$a['peringkat'] : '-' ?></td>
                                    <td class="p-4 text-center">
                                        <span class="inline-block px-2 py-1 <?= $a['status'] === 'cetak' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?> rounded text-label-sm font-bold uppercase">
                                            <?= h($a['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <a href="?tab=arsip&edit_arsip=<?= (int)$a['id'] ?>&kelas_id=<?= $kelasId ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>" class="text-text-muted hover:text-primary p-1">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                            </a>
                                            <form action="../actions/arsip/arsip_hapus.php" method="post" onsubmit="return confirm('Hapus arsip ini?');">
                                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                                <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
                                                <button type="submit" class="text-text-muted hover:text-error p-1">
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
    </div>

    <?php require_once __DIR__ . '/../../footer.php'; ?>

    <!-- Modal -->
    <div class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=arsip&kelas_id=<?= $kelasId ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>'"></div>
        <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg">
            <form action="../actions/arsip/arsip_simpan.php" method="post" class="p-lg">
                <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                    <h3 class="text-headline-sm font-bold text-text-main"><?= $editData ? 'Edit Arsip Rapor' : 'Tambah Arsip Rapor' ?></h3>
                    <a href="?tab=arsip&kelas_id=<?= $kelasId ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
                </div>
                <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">

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
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Kelas</label>
                            <select required name="kelas_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                <?php foreach ($daftarKelas as $k): ?>
                                    <option value="<?= (int)$k['id'] ?>" <?= (int)($editData['kelas_id'] ?? $kelasId) === (int)$k['id'] ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Semester</label>
                            <select name="semester" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                <option value="Ganjil" <?= ($editData['semester'] ?? $semester) === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                                <option value="Genap"  <?= ($editData['semester'] ?? '') === 'Genap' ? 'selected' : '' ?>>Genap</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Tahun</label>
                            <select name="tahun_ajaran" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                <?php foreach (['2023/2024', '2024/2025', '2025/2026'] as $ta): ?>
                                    <option value="<?= $ta ?>" <?= ($editData['tahun_ajaran'] ?? $tahunAjaran) === $ta ? 'selected' : '' ?>><?= $ta ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Rata-rata</label>
                            <input type="number" step="0.01" min="0" max="100" name="rata_rata" value="<?= h((string)($editData['rata_rata'] ?? '')) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="85.50">
                        </div>
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Peringkat</label>
                            <input type="number" min="1" name="peringkat" value="<?= h((string)($editData['peringkat'] ?? '')) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="3">
                        </div>
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Status</label>
                            <select name="status" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                <option value="cetak" <?= ($editData['status'] ?? 'cetak') === 'cetak' ? 'selected' : '' ?>>Cetak</option>
                                <option value="diarsipkan" <?= ($editData['status'] ?? '') === 'diarsipkan' ? 'selected' : '' ?>>Diarsipkan</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Catatan (opsional)</label>
                        <textarea name="catatan" rows="2" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"><?= h($editData['catatan'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                    <a href="?tab=arsip&kelas_id=<?= $kelasId ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                    <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</main>