<?php
/**
 * Partial: Tab "Kenaikan" di halaman data_siswa.php
 * Mengelola data kenaikan kelas / kelulusan siswa.
 */

if (!$isAdmin) {
    echo '<div class="bg-surface-white rounded-xl p-lg text-center shadow-sm border border-outline-variant/40">
            <span class="material-symbols-outlined text-[48px] text-outline-variant block">lock</span>
            <p class="text-body-md text-text-muted mt-2">Fitur kenaikan kelas hanya dapat diakses oleh Admin.</p>
          </div>';
    return;
}

$tahunAktif = $_GET['tahun_ajaran'] ?? '2024/2025';

// Daftar kenaikan
$daftarKenaikan = $pdo->prepare("SELECT kk.*, s.nama_lengkap, s.nis, ka.nama_kelas AS nama_asal, kt.nama_kelas AS nama_tujuan, u.nama_lengkap AS pembuat
                                  FROM kenaikan_kelas kk
                                  JOIN siswa s ON s.id = kk.siswa_id
                                  JOIN kelas ka ON ka.id = kk.kelas_asal_id
                                  LEFT JOIN kelas kt ON kt.id = kk.kelas_tujuan_id
                                  JOIN users u ON u.id = kk.dibuat_oleh
                                  WHERE kk.tahun_ajaran = ?
                                  ORDER BY kk.created_at DESC");
$daftarKenaikan->execute([$tahunAktif]);
$daftarKenaikan = $daftarKenaikan->fetchAll();

// Mode edit
$editData = null;
if (!empty($_GET['edit_kenaikan'])) {
    $stmtE = $pdo->prepare('SELECT * FROM kenaikan_kelas WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_kenaikan']]);
    $editData = $stmtE->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_kenaikan']) || $editData !== null;

// Daftar siswa aktif untuk default siswa
$totalSiswaAktif = (int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE status='aktif'")->fetchColumn();
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="kenaikan">
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap">Tahun Ajaran:</label>
        <select name="tahun_ajaran" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[180px]">
            <?php foreach (['2023/2024', '2024/2025', '2025/2026'] as $ta): ?>
                <option value="<?= $ta ?>" <?= $tahunAktif === $ta ? 'selected' : '' ?>><?= $ta ?></option>
            <?php endforeach; ?>
        </select>
        <a href="?tab=kenaikan&tahun_ajaran=<?= urlencode($tahunAktif) ?>&tambah_kenaikan=1" class="ml-auto bg-primary hover:bg-primary-container text-white px-4 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">add</span> Tambah Kenaikan
        </a>
    </form>
</div>

<!-- Ringkasan -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-md mb-lg">
    <?php
    $jumlahNaik = 0; $jumlahTinggal = 0; $jumlahLulus = 0; $jumlahPindah = 0;
    foreach ($daftarKenaikan as $k) {
        if ($k['status'] === 'naik') $jumlahNaik++;
        elseif ($k['status'] === 'tinggal') $jumlahTinggal++;
        elseif ($k['status'] === 'lulus') $jumlahLulus++;
        elseif ($k['status'] === 'pindah') $jumlahPindah++;
    }
    ?>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Naik</p>
        <p class="text-headline-sm font-bold text-emerald-600"><?= $jumlahNaik ?></p>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Tinggal</p>
        <p class="text-headline-sm font-bold text-amber-600"><?= $jumlahTinggal ?></p>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Lulus</p>
        <p class="text-headline-sm font-bold text-primary"><?= $jumlahLulus ?></p>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Pindah</p>
        <p class="text-headline-sm font-bold text-text-muted"><?= $jumlahPindah ?></p>
    </div>
</div>

<!-- Daftar Kenaikan -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">groups</span>
            Daftar Kenaikan Kelas — TA <?= h($tahunAktif) ?>
        </h3>
    </div>

    <?php if (empty($daftarKenaikan)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">school</span>
            <p class="text-body-md text-text-muted">Belum ada data kenaikan untuk tahun ajaran ini.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                        <th class="p-4">Siswa</th>
                        <th class="p-4">Kelas Asal</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Kelas Tujuan</th>
                        <th class="p-4">Catatan</th>
                        <th class="p-4 text-center w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-sm">
                    <?php foreach ($daftarKenaikan as $k):
                        $statusBadge = match ($k['status']) {
                            'naik' => 'bg-emerald-100 text-emerald-700',
                            'tinggal' => 'bg-amber-100 text-amber-700',
                            'lulus' => 'bg-primary/10 text-primary',
                            'pindah' => 'bg-slate-100 text-slate-700',
                            default => 'bg-slate-100 text-slate-700',
                        };
                    ?>
                        <tr class="hover:bg-surface-bright transition-colors">
                            <td class="p-4">
                                <div class="font-semibold text-text-main"><?= h($k['nama_lengkap']) ?></div>
                                <div class="text-label-sm text-text-muted"><?= h($k['nis']) ?></div>
                            </td>
                            <td class="p-4 text-text-main"><?= h($k['nama_asal']) ?></td>
                            <td class="p-4">
                                <span class="inline-block px-2 py-1 <?= $statusBadge ?> rounded text-label-sm font-bold uppercase"><?= h($k['status']) ?></span>
                            </td>
                            <td class="p-4 text-text-main"><?= h($k['nama_tujuan'] ?? '-') ?></td>
                            <td class="p-4 text-text-muted text-body-sm"><?= h($k['catatan'] ?? '-') ?></td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="?tab=kenaikan&tahun_ajaran=<?= urlencode($tahunAktif) ?>&edit_kenaikan=<?= (int)$k['id'] ?>" class="text-text-muted hover:text-primary p-1" title="Edit">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </a>
                                    <form action="../actions/siswa_tambahan/kenaikan_hapus.php" method="post" onsubmit="return confirm('Hapus data kenaikan ini? Status kelas siswa terkait tidak akan diubah secara otomatis.');">
                                        <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
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
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=kenaikan&tahun_ajaran=<?= urlencode($tahunAktif) ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg">
        <form action="../actions/siswa_tambahan/kenaikan_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-bold text-text-main"><?= $editData ? 'Edit Kenaikan' : 'Tambah Kenaikan' ?></h3>
                <a href="?tab=kenaikan&tahun_ajaran=<?= urlencode($tahunAktif) ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">

            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Siswa</label>
                    <select required name="siswa_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="">-- Pilih Siswa --</option>
                        <?php
                        $allSiswa = $pdo->query("SELECT s.id, s.nama_lengkap, s.nis, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id ORDER BY k.nama_kelas, s.nama_lengkap")->fetchAll();
                        foreach ($allSiswa as $s):
                        ?>
                            <option value="<?= (int)$s['id'] ?>" <?= ($editData && (int)$editData['siswa_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= h($s['nama_lengkap']) ?> — <?= h($s['nama_kelas'] ?? 'Tanpa Kelas') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Kelas Asal</label>
                        <select required name="kelas_asal_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($daftarKelas as $k): ?>
                                <option value="<?= (int)$k['id'] ?>" <?= ($editData && (int)$editData['kelas_asal_id'] === (int)$k['id']) ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Status</label>
                        <select required name="status" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <option value="naik"    <?= ($editData['status'] ?? 'naik') === 'naik' ? 'selected' : '' ?>>Naik</option>
                            <option value="tinggal" <?= ($editData['status'] ?? '') === 'tinggal' ? 'selected' : '' ?>>Tinggal</option>
                            <option value="lulus"   <?= ($editData['status'] ?? '') === 'lulus' ? 'selected' : '' ?>>Lulus</option>
                            <option value="pindah"  <?= ($editData['status'] ?? '') === 'pindah' ? 'selected' : '' ?>>Pindah</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Kelas Tujuan (opsional)</label>
                    <select name="kelas_tujuan_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="">-- Tetap di kelas asal --</option>
                        <?php foreach ($daftarKelas as $k): ?>
                            <option value="<?= (int)$k['id'] ?>" <?= ($editData && (int)$editData['kelas_tujuan_id'] === (int)$k['id']) ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-label-sm text-text-muted mt-1">Kosongkan jika tinggal kelas. Jika Naik/Lulus + kelas tujuan diisi, kelas siswa akan otomatis dipindahkan.</p>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Tahun Ajaran</label>
                    <select name="tahun_ajaran" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <?php foreach (['2023/2024', '2024/2025', '2025/2026'] as $ta): ?>
                            <option value="<?= $ta ?>" <?= ($editData['tahun_ajaran'] ?? $tahunAktif) === $ta ? 'selected' : '' ?>><?= $ta ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Catatan (opsional)</label>
                    <textarea name="catatan" rows="2" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"><?= h($editData['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="?tab=kenaikan&tahun_ajaran=<?= urlencode($tahunAktif) ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>