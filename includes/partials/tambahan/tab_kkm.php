<?php
/**
 * Partial: Tab "KKM dan Bobot" di halaman rekap_nilai.php
 * Mengelola KKM dan bobot nilai per mata pelajaran per kelas.
 */

$mapelId = (int)($_GET['mapel_id'] ?? 0);
$kelasId = (int)($_GET['kelas_id'] ?? 0);
if ($mapelId === 0 && !empty($daftarMapel)) $mapelId = (int)$daftarMapel[0]['id'];
if ($kelasId === 0 && !empty($daftarKelas)) $kelasId = (int)$daftarKelas[0]['id'];

// Ambil semua data KKM (gabungan mapel+kelas)
$daftarKKM = $pdo->query("SELECT kkm.*, m.nama_mapel, k.nama_kelas FROM kkm_mapel kkm
                            JOIN mata_pelajaran m ON m.id = kkm.mapel_id
                            JOIN kelas k ON k.id = kkm.kelas_id
                            ORDER BY m.nama_mapel, k.nama_kelas")->fetchAll();

// KKM aktif (jika ada)
$kkmAktif = null;
if ($mapelId && $kelasId) {
    $stmt = $pdo->prepare('SELECT * FROM kkm_mapel WHERE mapel_id=? AND kelas_id=?');
    $stmt->execute([$mapelId, $kelasId]);
    $kkmAktif = $stmt->fetch() ?: null;
}

// Mode edit
$editData = null;
if (!empty($_GET['edit_kkm'])) {
    $stmtE = $pdo->prepare('SELECT * FROM kkm_mapel WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_kkm']]);
    $editData = $stmtE->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_kkm']) || $editData !== null;
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="kkm">
        <label class="text-label-md text-text-main">Mapel:</label>
        <select name="mapel_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php foreach ($daftarMapel as $mp): ?>
                <option value="<?= (int)$mp['id'] ?>" <?= (int)$mp['id'] === $mapelId ? 'selected' : '' ?>><?= h($mp['nama_mapel']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-md text-text-main">Kelas:</label>
        <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasId ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="?tab=kkm&tambah_kkm=1&mapel_id=<?= $mapelId ?>&kelas_id=<?= $kelasId ?>" class="ml-auto bg-primary hover:bg-primary-container text-white px-4 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">add</span> Atur KKM
        </a>
    </form>
</div>

<!-- KKM Aktif Card -->
<div class="bg-gradient-to-br from-primary/5 to-primary/10 rounded-xl p-lg mb-lg shadow-sm border border-primary/20">
    <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2 mb-3">
        <span class="material-symbols-outlined text-primary">balance</span>
        KKM Aktif: <?= h($daftarMapel[array_search($mapelId, array_column($daftarMapel, 'id'))]['nama_mapel'] ?? '-') ?> — <?= h($daftarKelas[array_search($kelasId, array_column($daftarKelas, 'id'))]['nama_kelas'] ?? '-') ?>
    </h3>
    <?php if ($kkmAktif): ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <p class="text-label-sm text-text-muted">KKM</p>
                <p class="text-headline-md font-bold text-primary"><?= (float)$kkmAktif['kkm'] ?></p>
            </div>
            <div>
                <p class="text-label-sm text-text-muted">Bobot Tugas</p>
                <p class="text-headline-md font-bold text-text-main"><?= (float)$kkmAktif['bobot_tugas'] ?>%</p>
            </div>
            <div>
                <p class="text-label-sm text-text-muted">Bobot UTS</p>
                <p class="text-headline-md font-bold text-text-main"><?= (float)$kkmAktif['bobot_uts'] ?>%</p>
            </div>
            <div>
                <p class="text-label-sm text-text-muted">Bobot UAS</p>
                <p class="text-headline-md font-bold text-text-main"><?= (float)$kkmAktif['bobot_uas'] ?>%</p>
            </div>
        </div>
        <?php if ($kkmAktif['deskripsi']): ?>
            <p class="text-body-sm text-text-muted mt-3 italic"><?= h($kkmAktif['deskripsi']) ?></p>
        <?php endif; ?>
        <div class="mt-3">
            <a href="?tab=kkm&edit_kkm=<?= (int)$kkmAktif['id'] ?>&mapel_id=<?= $mapelId ?>&kelas_id=<?= $kelasId ?>" class="text-primary hover:underline text-label-md font-semibold">Edit</a>
        </div>
    <?php else: ?>
        <p class="text-body-md text-text-muted">Belum ada KKM untuk kombinasi mapel & kelas ini. Klik "Atur KKM" untuk menambahkan.</p>
    <?php endif; ?>
</div>

<!-- Daftar Semua KKM -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md">
        <h3 class="text-headline-sm font-bold text-text-main">Daftar KKM Terdaftar</h3>
    </div>
    <?php if (empty($daftarKKM)): ?>
        <div class="flex flex-col items-center justify-center py-12 px-6 text-center">
            <span class="material-symbols-outlined text-[48px] text-outline-variant mb-2">balance</span>
            <p class="text-body-md text-text-muted">Belum ada KKM yang terdaftar.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                        <th class="p-3">Mata Pelajaran</th>
                        <th class="p-3">Kelas</th>
                        <th class="p-3 text-center">KKM</th>
                        <th class="p-3 text-center">Tugas</th>
                        <th class="p-3 text-center">UTS</th>
                        <th class="p-3 text-center">UAS</th>
                        <th class="p-3 text-center w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-sm">
                    <?php foreach ($daftarKKM as $k): ?>
                        <tr class="hover:bg-surface-bright transition-colors">
                            <td class="p-3 font-semibold text-text-main"><?= h($k['nama_mapel']) ?></td>
                            <td class="p-3 text-text-main"><?= h($k['nama_kelas']) ?></td>
                            <td class="p-3 text-center font-bold text-primary"><?= (float)$k['kkm'] ?></td>
                            <td class="p-3 text-center text-text-muted"><?= (float)$k['bobot_tugas'] ?>%</td>
                            <td class="p-3 text-center text-text-muted"><?= (float)$k['bobot_uts'] ?>%</td>
                            <td class="p-3 text-center text-text-muted"><?= (float)$k['bobot_uas'] ?>%</td>
                            <td class="p-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="?tab=kkm&edit_kkm=<?= (int)$k['id'] ?>&mapel_id=<?= (int)$k['mapel_id'] ?>&kelas_id=<?= (int)$k['kelas_id'] ?>" class="text-text-muted hover:text-primary p-1">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </a>
                                    <form action="../actions/nilai_tambahan/kkm_hapus.php" method="post" onsubmit="return confirm('Hapus KKM ini?');">
                                        <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                        <input type="hidden" name="mapel_id" value="<?= (int)$k['mapel_id'] ?>">
                                        <input type="hidden" name="kelas_id" value="<?= (int)$k['kelas_id'] ?>">
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
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=kkm'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-md">
        <form action="../actions/nilai_tambahan/kkm_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-bold text-text-main"><?= $editData ? 'Edit KKM' : 'Atur KKM' ?></h3>
                <a href="?tab=kkm&mapel_id=<?= $mapelId ?>&kelas_id=<?= $kelasId ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
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
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Kelas</label>
                    <select required name="kelas_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <?php foreach ($daftarKelas as $k): ?>
                            <option value="<?= (int)$k['id'] ?>" <?= (int)($editData['kelas_id'] ?? $kelasId) === (int)$k['id'] ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Nilai KKM</label>
                    <input required type="number" min="0" max="100" step="0.01" name="kkm" value="<?= h((string)($editData['kkm'] ?? 75)) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Bobot (Total harus 100%)</label>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-label-sm text-text-muted block mb-1">Tugas (%)</label>
                            <input required type="number" min="0" max="100" step="0.01" name="bobot_tugas" value="<?= h((string)($editData['bobot_tugas'] ?? 30)) ?>" class="w-full px-3 py-2 rounded-lg border border-outline-variant focus:border-primary outline-none">
                        </div>
                        <div>
                            <label class="text-label-sm text-text-muted block mb-1">UTS (%)</label>
                            <input required type="number" min="0" max="100" step="0.01" name="bobot_uts" value="<?= h((string)($editData['bobot_uts'] ?? 30)) ?>" class="w-full px-3 py-2 rounded-lg border border-outline-variant focus:border-primary outline-none">
                        </div>
                        <div>
                            <label class="text-label-sm text-text-muted block mb-1">UAS (%)</label>
                            <input required type="number" min="0" max="100" step="0.01" name="bobot_uas" value="<?= h((string)($editData['bobot_uas'] ?? 40)) ?>" class="w-full px-3 py-2 rounded-lg border border-outline-variant focus:border-primary outline-none">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Deskripsi (opsional)</label>
                    <textarea name="deskripsi" rows="2" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Catatan tentang KKM ini..."><?= h($editData['deskripsi'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="?tab=kkm&mapel_id=<?= $mapelId ?>&kelas_id=<?= $kelasId ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>