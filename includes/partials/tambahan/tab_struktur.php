<?php
/**
 * Partial: Tab "Struktur" di halaman data_siswa.php
 * Menampilkan dan mengelola organisasi kelas (ketua/wakil/sekretaris/bendahara)
 */

// Pastikan tabel ada (auto-migrate ringan)
ensureFiturTambahanTables($pdo);

// Ambil kelas aktif (default: kelas pertama yang diajar jika guru, atau kelas pertama jika admin)
$kelasAktif = $_GET['kelas_id'] ?? '';
if ($kelasAktif === '') {
    if (!$isAdmin && !empty($kelasDiajarGuru)) {
        $kelasAktif = $kelasDiajarGuru[0]['id'];
    } elseif (!empty($daftarKelas)) {
        $kelasAktif = $daftarKelas[0]['id'];
    }
}
$kelasAktif = (int)$kelasAktif;

$namaKelasAktif = '';
foreach ($daftarKelas as $k) {
    if ((int)$k['id'] === $kelasAktif) { $namaKelasAktif = $k['nama_kelas']; break; }
}

// Daftar struktur organisasi
$daftarStruktur = [];
if ($kelasAktif > 0) {
    $stmt = $pdo->prepare("SELECT sk.*, s.nama_lengkap, s.nis, s.jenis_kelamin, s.foto
                            FROM struktur_kelas sk
                            JOIN siswa s ON s.id = sk.siswa_id
                            WHERE sk.kelas_id = ?
                            ORDER BY sk.urutan ASC, sk.jabatan ASC");
    $stmt->execute([$kelasAktif]);
    $daftarStruktur = $stmt->fetchAll();
}

// Daftar siswa kelas ini (untuk dropdown pilihan)
$daftarSiswaKelas = [];
if ($kelasAktif > 0) {
    $stmt = $pdo->prepare("SELECT id, nama_lengkap, nis, jenis_kelamin FROM siswa WHERE kelas_id = ? AND status='aktif' ORDER BY nama_lengkap");
    $stmt->execute([$kelasAktif]);
    $daftarSiswaKelas = $stmt->fetchAll();
}

// Mode edit
$editData = null;
if (!empty($_GET['edit_struktur'])) {
    $stmtE = $pdo->prepare('SELECT * FROM struktur_kelas WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_struktur']]);
    $editData = $stmtE->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_struktur']) || $editData !== null;

// Daftar jabatan standar
$jabatanStandar = ['Ketua Kelas', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Anggota'];
?>

<!-- Filter Kelas -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="struktur">
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap">Pilih Kelas:</label>
        <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[200px]">
            <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasAktif ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($isAdmin): ?>
        <a href="?tab=struktur&kelas_id=<?= $kelasAktif ?>&tambah_struktur=1" class="ml-auto bg-primary hover:bg-primary-container text-white px-4 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">person_add</span> Tambah Jabatan
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Daftar Struktur -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">account_tree</span>
            Struktur Organisasi Kelas <?= h($namaKelasAktif) ?>
        </h3>
        <p class="text-body-sm text-text-muted mt-1">Daftar siswa yang memegang jabatan organisasi di kelas ini.</p>
    </div>

    <?php if (empty($daftarStruktur)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">groups</span>
            <p class="text-body-md text-text-muted">Belum ada struktur organisasi untuk kelas ini.</p>
            <?php if ($isAdmin): ?>
                <a href="?tab=struktur&kelas_id=<?= $kelasAktif ?>&tambah_struktur=1" class="mt-3 text-primary hover:underline text-label-md font-semibold">Tambah sekarang</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                        <th class="p-4 w-12 text-center">No</th>
                        <th class="p-4">Siswa</th>
                        <th class="p-4">Jabatan</th>
                        <th class="p-4">NIS</th>
                        <th class="p-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-sm">
                    <?php foreach ($daftarStruktur as $no => $s): ?>
                        <tr class="hover:bg-surface-bright transition-colors">
                            <td class="p-4 text-center text-text-muted"><?= $no + 1 ?></td>
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-label-md">
                                        <?= h(inisialNama($s['nama_lengkap'])) ?>
                                    </div>
                                    <span class="font-semibold text-text-main"><?= h($s['nama_lengkap']) ?></span>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="inline-block px-3 py-1 bg-primary/10 text-primary rounded-full text-label-md font-semibold">
                                    <?= h($s['jabatan']) ?>
                                </span>
                            </td>
                            <td class="p-4 text-text-muted font-mono text-xs"><?= h($s['nis']) ?></td>
                            <td class="p-4 text-center">
                                <?php if ($isAdmin): ?>
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="?tab=struktur&kelas_id=<?= $kelasAktif ?>&edit_struktur=<?= (int)$s['id'] ?>" class="text-text-muted hover:text-primary p-1" title="Edit">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </a>
                                        <form action="../actions/siswa_tambahan/struktur_hapus.php" method="post" onsubmit="return confirm('Hapus <?= h(addslashes($s['nama_lengkap'])) ?> dari struktur kelas?');">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <input type="hidden" name="kelas_id" value="<?= $kelasAktif ?>">
                                            <button type="submit" class="text-text-muted hover:text-error p-1" title="Hapus">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah/Edit Struktur -->
<?php if ($isAdmin): ?>
<div class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=struktur&kelas_id=<?= $kelasAktif ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-md">
        <form action="../actions/siswa_tambahan/struktur_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-bold text-text-main"><?= $editData ? 'Edit Jabatan' : 'Tambah Jabatan' ?></h3>
                <a href="?tab=struktur&kelas_id=<?= $kelasAktif ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
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
                    <label class="text-label-md font-label-md text-text-main block mb-1">Jabatan</label>
                    <input list="list-jabatan" required name="jabatan" value="<?= h($editData['jabatan'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: Ketua Kelas">
                    <datalist id="list-jabatan">
                        <?php foreach ($jabatanStandar as $j): ?><option value="<?= h($j) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Urutan</label>
                    <input type="number" min="1" name="urutan" value="<?= h((string)($editData['urutan'] ?? 1)) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    <p class="text-label-sm text-text-muted mt-1">Angka lebih kecil tampil lebih dulu.</p>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="?tab=struktur&kelas_id=<?= $kelasAktif ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>