<?php
/**
 * Partial: Tab "Catatan" di halaman data_siswa.php
 * Menampilkan dan mengelola catatan wali kelas untuk siswa.
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

// Daftar catatan wali kelas
$daftarCatatan = [];
if ($kelasAktif > 0) {
    $stmt = $pdo->prepare("SELECT c.*, s.nama_lengkap, s.nis, u.nama_lengkap AS pembuat
                            FROM catatan_wali c
                            JOIN siswa s ON s.id = c.siswa_id
                            JOIN users u ON u.id = c.dibuat_oleh
                            WHERE c.kelas_id = ?
                            ORDER BY c.created_at DESC
                            LIMIT 100");
    $stmt->execute([$kelasAktif]);
    $daftarCatatan = $stmt->fetchAll();
}

// Mode edit
$editData = null;
if (!empty($_GET['edit_catatan'])) {
    $stmtE = $pdo->prepare('SELECT * FROM catatan_wali WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_catatan']]);
    $editData = $stmtE->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_catatan']) || $editData !== null;

$jenisBadge = [
    'positif'      => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'icon' => 'thumb_up'],
    'perhatian'    => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'icon' => 'visibility'],
    'pelanggaran'  => ['bg' => 'bg-rose-100',    'text' => 'text-rose-700',    'icon' => 'gavel'],
    'lainnya'      => ['bg' => 'bg-slate-100',   'text' => 'text-slate-700',   'icon' => 'edit_note'],
];
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="catatan">
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap">Kelas:</label>
        <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[200px]">
            <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasAktif ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="?tab=catatan&kelas_id=<?= $kelasAktif ?>&tambah_catatan=1" class="ml-auto bg-primary hover:bg-primary-container text-white px-4 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">edit_note</span> Tambah Catatan
        </a>
    </form>
</div>

<!-- Daftar Catatan -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">edit_note</span>
            Catatan Wali Kelas — <?= h($namaKelasAktif) ?>
        </h3>
    </div>

    <?php if (empty($daftarCatatan)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">edit_note</span>
            <p class="text-body-md text-text-muted">Belum ada catatan untuk kelas ini.</p>
        </div>
    <?php else: ?>
        <div class="p-lg space-y-3">
            <?php foreach ($daftarCatatan as $c):
                $badge = $jenisBadge[$c['jenis']] ?? $jenisBadge['lainnya'];
            ?>
                <div class="border border-outline-variant/60 rounded-xl p-4 hover:border-primary/30 transition-colors">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full <?= $badge['bg'] ?> flex items-center justify-center <?= $badge['text'] ?> flex-shrink-0">
                            <span class="material-symbols-outlined text-[20px]"><?= $badge['icon'] ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-1 flex-wrap">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-text-main"><?= h($c['nama_lengkap']) ?></span>
                                    <span class="inline-block px-2 py-0.5 <?= $badge['bg'] ?> <?= $badge['text'] ?> rounded text-[10px] font-bold uppercase"><?= h($c['jenis']) ?></span>
                                </div>
                                <span class="text-label-sm text-text-muted"><?= waktuRelatif($c['created_at']) ?></span>
                            </div>
                            <p class="text-body-sm text-text-main mb-2 whitespace-pre-line"><?= nl2br(h($c['catatan'])) ?></p>
                            <p class="text-label-sm text-text-muted">— <?= h($c['pembuat']) ?></p>
                        </div>
                        <div class="flex gap-1">
                            <a href="?tab=catatan&kelas_id=<?= $kelasAktif ?>&edit_catatan=<?= (int)$c['id'] ?>" class="text-text-muted hover:text-primary p-1" title="Edit">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </a>
                            <form action="../actions/siswa_tambahan/catatan_hapus.php" method="post" onsubmit="return confirm('Hapus catatan ini?');">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <input type="hidden" name="kelas_id" value="<?= $kelasAktif ?>">
                                <button type="submit" class="text-text-muted hover:text-error p-1" title="Hapus">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=catatan&kelas_id=<?= $kelasAktif ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg">
        <form action="../actions/siswa_tambahan/catatan_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-bold text-text-main"><?= $editData ? 'Edit Catatan' : 'Tambah Catatan' ?></h3>
                <a href="?tab=catatan&kelas_id=<?= $kelasAktif ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
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
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Jenis Catatan</label>
                    <select name="jenis" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="positif"     <?= ($editData['jenis'] ?? '') === 'positif' ? 'selected' : '' ?>>Catatan Positif</option>
                        <option value="perhatian"   <?= ($editData['jenis'] ?? 'perhatian') === 'perhatian' ? 'selected' : '' ?>>Perhatian</option>
                        <option value="pelanggaran" <?= ($editData['jenis'] ?? '') === 'pelanggaran' ? 'selected' : '' ?>>Pelanggaran</option>
                        <option value="lainnya"     <?= ($editData['jenis'] ?? '') === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Isi Catatan</label>
                    <textarea required name="catatan" rows="5" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Tuliskan catatan tentang siswa..."><?= h($editData['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="?tab=catatan&kelas_id=<?= $kelasAktif ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>