<?php
/**
 * Partial: Tab "Indikator Nilai" di halaman rekap_nilai.php
 * Mengelola indikator pencapaian kompetensi per mata pelajaran.
 */

$mapelId = (int)($_GET['mapel_id'] ?? 0);
$tingkat  = $_GET['tingkat'] ?? '';
$semester = $_GET['semester'] ?? 'Ganjil';
if ($mapelId === 0 && !empty($daftarMapel)) $mapelId = (int)$daftarMapel[0]['id'];

$whereParams = [];
$whereSql = '1=1';
if ($mapelId) { $whereSql .= ' AND i.mapel_id = ?'; $whereParams[] = $mapelId; }
if (in_array($tingkat, ['X','XI','XII'], true)) { $whereSql .= ' AND i.kelas_tingkat = ?'; $whereParams[] = $tingkat; }
if (in_array($semester, ['Ganjil','Genap'], true)) { $whereSql .= ' AND i.semester = ?'; $whereParams[] = $semester; }

$stmt = $pdo->prepare("SELECT i.*, m.nama_mapel FROM indikator_nilai i
                       JOIN mata_pelajaran m ON m.id = i.mapel_id
                       WHERE $whereSql
                       ORDER BY i.kelas_tingkat, i.urutan, i.kode_indikator");
$stmt->execute($whereParams);
$daftarIndikator = $stmt->fetchAll();

// Mode edit
$editData = null;
if (!empty($_GET['edit_indikator'])) {
    $stmtE = $pdo->prepare('SELECT * FROM indikator_nilai WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_indikator']]);
    $editData = $stmtE->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_indikator']) || $editData !== null;
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="indikator">
        <label class="text-label-md text-text-main">Mapel:</label>
        <select name="mapel_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php foreach ($daftarMapel as $mp): ?>
                <option value="<?= (int)$mp['id'] ?>" <?= (int)$mp['id'] === $mapelId ? 'selected' : '' ?>><?= h($mp['nama_mapel']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-md text-text-main">Tingkat:</label>
        <select name="tingkat" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <option value="">Semua</option>
            <option value="X" <?= $tingkat === 'X' ? 'selected' : '' ?>>X</option>
            <option value="XI" <?= $tingkat === 'XI' ? 'selected' : '' ?>>XI</option>
            <option value="XII" <?= $tingkat === 'XII' ? 'selected' : '' ?>>XII</option>
        </select>
        <label class="text-label-md text-text-main">Semester:</label>
        <select name="semester" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
            <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
        </select>
        <a href="?tab=indikator&tambah_indikator=1&mapel_id=<?= $mapelId ?>&tingkat=<?= urlencode($tingkat) ?>&semester=<?= urlencode($semester) ?>" class="ml-auto bg-primary hover:bg-primary-container text-white px-4 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">add</span> Tambah Indikator
        </a>
    </form>
</div>

<!-- Daftar Indikator -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">menu_book</span>
            Indikator Pencapaian Kompetensi
        </h3>
        <p class="text-body-sm text-text-muted mt-1">Indikator kemampuan minimal yang harus dikuasai siswa per mata pelajaran.</p>
    </div>

    <?php if (empty($daftarIndikator)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">menu_book</span>
            <p class="text-body-md text-text-muted">Belum ada indikator untuk filter ini.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                        <th class="p-4 w-20">Kode</th>
                        <th class="p-4">Mata Pelajaran</th>
                        <th class="p-4 text-center w-16">Tingkat</th>
                        <th class="p-4 text-center w-20">Smt</th>
                        <th class="p-4">Deskripsi</th>
                        <th class="p-4 text-center w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-sm">
                    <?php foreach ($daftarIndikator as $i): ?>
                        <tr class="hover:bg-surface-bright transition-colors">
                            <td class="p-4 font-bold text-primary"><?= h($i['kode_indikator']) ?></td>
                            <td class="p-4 text-text-main"><?= h($i['nama_mapel']) ?></td>
                            <td class="p-4 text-center"><span class="px-2 py-0.5 bg-primary/10 text-primary rounded text-label-sm font-bold"><?= h($i['kelas_tingkat']) ?></span></td>
                            <td class="p-4 text-center text-text-muted"><?= h($i['semester']) ?></td>
                            <td class="p-4 text-text-main"><?= h($i['deskripsi']) ?></td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="?tab=indikator&edit_indikator=<?= (int)$i['id'] ?>&mapel_id=<?= $mapelId ?>&tingkat=<?= urlencode($tingkat) ?>&semester=<?= urlencode($semester) ?>" class="text-text-muted hover:text-primary p-1">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </a>
                                    <form action="../actions/nilai_tambahan/indikator_hapus.php" method="post" onsubmit="return confirm('Hapus indikator ini?');">
                                        <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                                        <input type="hidden" name="mapel_id" value="<?= $mapelId ?>">
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

<!-- Modal -->
<div class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=indikator&mapel_id=<?= $mapelId ?>&tingkat=<?= urlencode($tingkat) ?>&semester=<?= urlencode($semester) ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg">
        <form action="../actions/nilai_tambahan/indikator_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-bold text-text-main"><?= $editData ? 'Edit Indikator' : 'Tambah Indikator' ?></h3>
                <a href="?tab=indikator&mapel_id=<?= $mapelId ?>&tingkat=<?= urlencode($tingkat) ?>&semester=<?= urlencode($semester) ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">

            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Mata Pelajaran</label>
                    <select required name="mapel_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <?php foreach ($daftarMapel as $mp): ?>
                            <option value="<?= (int)$mp['id'] ?>" <?= (int)($editData['mapel_id'] ?? $mapelId) === (int)$mp['id'] ? 'selected' : '' ?>><?= h($mp['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Tingkat</label>
                        <select required name="kelas_tingkat" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <option value="X"   <?= ($editData['kelas_tingkat'] ?? 'X') === 'X' ? 'selected' : '' ?>>X</option>
                            <option value="XI"  <?= ($editData['kelas_tingkat'] ?? '') === 'XI' ? 'selected' : '' ?>>XI</option>
                            <option value="XII" <?= ($editData['kelas_tingkat'] ?? '') === 'XII' ? 'selected' : '' ?>>XII</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Semester</label>
                        <select required name="semester" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <option value="Ganjil" <?= ($editData['semester'] ?? 'Ganjil') === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                            <option value="Genap"  <?= ($editData['semester'] ?? '') === 'Genap' ? 'selected' : '' ?>>Genap</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Kode</label>
                        <input required name="kode_indikator" value="<?= h($editData['kode_indikator'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="IPK-1">
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Deskripsi Indikator</label>
                    <textarea required name="deskripsi" rows="4" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Siswa mampu..."><?= h($editData['deskripsi'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Urutan</label>
                    <input type="number" min="1" name="urutan" value="<?= h((string)($editData['urutan'] ?? 1)) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="?tab=indikator&mapel_id=<?= $mapelId ?>&tingkat=<?= urlencode($tingkat) ?>&semester=<?= urlencode($semester) ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>