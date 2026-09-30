<?php
/**
 * Partial: Tab "Sikap Spiritual" / "Sikap Sosial" di halaman rekap_nilai.php
 * Mengelola nilai sikap siswa per kelas per semester.
 */

$jenisTab = ($_GET['tab'] ?? 'spiritual') === 'sosial' ? 'sosial' : 'spiritual';
$labelJenis = $jenisTab === 'sosial' ? 'Sosial' : 'Spiritual';
$iconJenis  = $jenisTab === 'sosial' ? 'groups' : 'self_improvement';

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$semester = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? '2024/2025';
if ($kelasId === 0 && !empty($daftarKelas)) $kelasId = (int)$daftarKelas[0]['id'];

$namaKelas = '';
foreach ($daftarKelas as $k) { if ((int)$k['id'] === $kelasId) { $namaKelas = $k['nama_kelas']; break; } }

// Daftar siswa + nilai sikap
$daftarSiswaKelas = [];
if ($kelasId > 0) {
    $stmt = $pdo->prepare("SELECT s.id, s.nis, s.nama_lengkap,
                            ns.id AS sikap_id, ns.predikat, ns.deskripsi
                           FROM siswa s
                           LEFT JOIN nilai_sikap ns ON ns.siswa_id = s.id AND ns.kelas_id = ?
                               AND ns.jenis = ? AND ns.semester = ? AND ns.tahun_ajaran = ?
                           WHERE s.kelas_id = ? AND s.status='aktif'
                           ORDER BY s.nama_lengkap");
    $stmt->execute([$kelasId, $jenisTab, $semester, $tahunAjaran, $kelasId]);
    $daftarSiswaKelas = $stmt->fetchAll();
}

// Mode edit
$editData = null;
if (!empty($_GET['edit_sikap'])) {
    $stmtE = $pdo->prepare('SELECT * FROM nilai_sikap WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_sikap']]);
    $editData = $stmtE->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_sikap']) || $editData !== null;

$predikatBadge = [
    'Sangat Baik'      => 'bg-emerald-100 text-emerald-700',
    'Baik'             => 'bg-primary/10 text-primary',
    'Cukup'            => 'bg-amber-100 text-amber-700',
    'Perlu Bimbingan'  => 'bg-rose-100 text-rose-700',
];
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="<?= $jenisTab ?>">
        <label class="text-label-md text-text-main">Kelas:</label>
        <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasId ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-md text-text-main">Semester:</label>
        <select name="semester" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
            <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
        </select>
        <label class="text-label-md text-text-main">Tahun:</label>
        <select name="tahun_ajaran" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php foreach (['2023/2024', '2024/2025', '2025/2026'] as $ta): ?>
                <option value="<?= $ta ?>" <?= $tahunAjaran === $ta ? 'selected' : '' ?>><?= $ta ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Daftar -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary"><?= $iconJenis ?></span>
            Nilai Sikap <?= $labelJenis ?> — <?= h($namaKelas) ?> (<?= h($semester) ?> <?= h($tahunAjaran) ?>)
        </h3>
        <p class="text-body-sm text-text-muted mt-1">Berikan predikat sikap dan deskripsi untuk setiap siswa.</p>
    </div>

    <?php if (empty($daftarSiswaKelas)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">person_off</span>
            <p class="text-body-md text-text-muted">Tidak ada siswa aktif di kelas ini.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                        <th class="p-4 w-32">NIS</th>
                        <th class="p-4">Nama Siswa</th>
                        <th class="p-4 text-center w-40">Predikat</th>
                        <th class="p-4">Deskripsi</th>
                        <th class="p-4 text-center w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-sm">
                    <?php foreach ($daftarSiswaKelas as $s):
                        $badge = $predikatBadge[$s['predikat'] ?? ''] ?? 'bg-slate-100 text-slate-700';
                    ?>
                        <tr class="hover:bg-surface-bright transition-colors">
                            <td class="p-4 font-mono text-text-muted"><?= h($s['nis']) ?></td>
                            <td class="p-4 font-semibold text-text-main"><?= h($s['nama_lengkap']) ?></td>
                            <td class="p-4 text-center">
                                <?php if (!empty($s['predikat'])): ?>
                                    <span class="inline-block px-3 py-1 <?= $badge ?> rounded-full text-label-md font-bold"><?= h($s['predikat']) ?></span>
                                <?php else: ?>
                                    <span class="text-text-muted text-label-md italic">Belum dinilai</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-text-muted text-body-sm"><?= h($s['deskripsi'] ?? '-') ?></td>
                            <td class="p-4 text-center">
                                <a href="?tab=<?= $jenisTab ?>&edit_sikap=<?= (int)$s['id'] ?>&kelas_id=<?= $kelasId ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>" class="text-text-muted hover:text-primary p-1 inline-flex items-center gap-1 text-label-md font-semibold">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                    <?= $s['predikat'] ? 'Edit' : 'Input' ?>
                                </a>
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
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=<?= $jenisTab ?>&kelas_id=<?= $kelasId ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg">
        <form action="../actions/nilai_tambahan/sikap_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-bold text-text-main">Input Nilai Sikap <?= $labelJenis ?></h3>
                <a href="?tab=<?= $jenisTab ?>&kelas_id=<?= $kelasId ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">
            <input type="hidden" name="jenis" value="<?= $jenisTab ?>">
            <input type="hidden" name="tab_tujuan" value="<?= $jenisTab ?>">

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
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Predikat</label>
                    <select required name="predikat" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <?php foreach (['Sangat Baik', 'Baik', 'Cukup', 'Perlu Bimbingan'] as $p): ?>
                            <option value="<?= $p ?>" <?= ($editData['predikat'] ?? 'Baik') === $p ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Deskripsi (opsional)</label>
                    <textarea name="deskripsi" rows="3" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Catatan / deskripsi sikap siswa..."><?= h($editData['deskripsi'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="?tab=<?= $jenisTab ?>&kelas_id=<?= $kelasId ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahunAjaran) ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>