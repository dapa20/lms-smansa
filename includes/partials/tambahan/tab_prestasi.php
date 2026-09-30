<?php
/**
 * Partial: Tab "Prestasi" di halaman rekap_nilai.php
 * Mengelola data prestasi siswa (akademik/non-akademik/olahraga/seni).
 */

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$tahunFilter = $_GET['tahun'] ?? '';
if ($kelasId === 0 && !empty($daftarKelas)) $kelasId = (int)$daftarKelas[0]['id'];

$whereParams = [];
$whereSql = '1=1';
if ($kelasId) { $whereSql .= ' AND p.kelas_id = ?'; $whereParams[] = $kelasId; }
if ($tahunFilter !== '' && is_numeric($tahunFilter)) { $whereSql .= ' AND p.tahun = ?'; $whereParams[] = (int)$tahunFilter; }

$stmt = $pdo->prepare("SELECT p.*, s.nama_lengkap, s.nis, k.nama_kelas, u.nama_lengkap AS pembuat
                       FROM prestasi_siswa p
                       JOIN siswa s ON s.id = p.siswa_id
                       JOIN kelas k ON k.id = p.kelas_id
                       JOIN users u ON u.id = p.dibuat_oleh
                       WHERE $whereSql
                       ORDER BY p.tahun DESC, p.created_at DESC
                       LIMIT 200");
$stmt->execute($whereParams);
$daftarPrestasi = $stmt->fetchAll();

// Mode edit
$editData = null;
if (!empty($_GET['edit_prestasi'])) {
    $stmtE = $pdo->prepare('SELECT * FROM prestasi_siswa WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_prestasi']]);
    $editData = $stmtE->fetch() ?: null;
}
$modalTerbuka = !empty($_GET['tambah_prestasi']) || $editData !== null;

// Daftar siswa untuk dropdown
$daftarSiswaKelas = [];
if ($kelasId > 0) {
    $stmtS = $pdo->prepare("SELECT id, nama_lengkap, nis FROM siswa WHERE kelas_id = ? AND status='aktif' ORDER BY nama_lengkap");
    $stmtS->execute([$kelasId]);
    $daftarSiswaKelas = $stmtS->fetchAll();
}

$jenisBadge = [
    'akademik'      => ['bg' => 'bg-primary/10', 'text' => 'text-primary', 'icon' => 'school'],
    'non-akademik'  => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'icon' => 'auto_stories'],
    'olahraga'      => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'icon' => 'sports_soccer'],
    'seni'          => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'icon' => 'palette'],
    'lainnya'       => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'icon' => 'emoji_events'],
];
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="prestasi">
        <label class="text-label-md text-text-main">Kelas:</label>
        <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <option value="">Semua</option>
            <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasId ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-md text-text-main">Tahun:</label>
        <select name="tahun" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <option value="">Semua</option>
            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= (int)$tahunFilter === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <a href="?tab=prestasi&tambah_prestasi=1&kelas_id=<?= $kelasId ?>" class="ml-auto bg-primary hover:bg-primary-container text-white px-4 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">emoji_events</span> Tambah Prestasi
        </a>
    </form>
</div>

<!-- Statistik -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-md mb-lg">
    <?php
    $stat = ['akademik' => 0, 'non-akademik' => 0, 'olahraga' => 0, 'seni' => 0, 'lainnya' => 0];
    foreach ($daftarPrestasi as $p) {
        if (isset($stat[$p['jenis_prestasi']])) $stat[$p['jenis_prestasi']]++;
    }
    ?>
    <?php foreach ($stat as $j => $jml):
        $badge = $jenisBadge[$j] ?? $jenisBadge['lainnya'];
    ?>
        <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg <?= $badge['bg'] ?> flex items-center justify-center <?= $badge['text'] ?>">
                <span class="material-symbols-outlined text-[20px]"><?= $badge['icon'] ?></span>
            </div>
            <div>
                <p class="text-label-sm text-text-muted capitalize"><?= h(str_replace('-', ' ', $j)) ?></p>
                <p class="text-headline-sm font-bold text-text-main"><?= $jml ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Daftar Prestasi -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">emoji_events</span>
            Daftar Prestasi Siswa
        </h3>
    </div>

    <?php if (empty($daftarPrestasi)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">emoji_events</span>
            <p class="text-body-md text-text-muted">Belum ada data prestasi.</p>
        </div>
    <?php else: ?>
        <div class="p-lg grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($daftarPrestasi as $p):
                $badge = $jenisBadge[$p['jenis_prestasi']] ?? $jenisBadge['lainnya'];
            ?>
                <div class="border border-outline-variant/60 rounded-xl p-4 hover:border-primary/30 hover:shadow-sm transition-all">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 <?= $badge['bg'] ?> <?= $badge['text'] ?> rounded text-label-sm font-bold">
                            <span class="material-symbols-outlined text-[14px]"><?= $badge['icon'] ?></span>
                            <?= h(ucfirst(str_replace('-', ' ', $p['jenis_prestasi']))) ?>
                        </span>
                        <span class="text-label-sm text-text-muted"><?= h($p['tahun']) ?></span>
                    </div>
                    <h4 class="text-label-lg font-bold text-text-main mb-1"><?= h($p['nama_prestasi']) ?></h4>
                    <p class="text-body-sm text-text-muted mb-2">
                        <span class="font-semibold text-text-main"><?= h($p['nama_lengkap']) ?></span>
                        <span class="text-text-muted"> · <?= h($p['nama_kelas']) ?></span>
                    </p>
                    <div class="flex items-center gap-2 flex-wrap text-label-sm text-text-muted mb-2">
                        <span class="px-2 py-0.5 bg-surface-container rounded font-semibold"><?= h($p['tingkat']) ?></span>
                        <?php if ($p['peringkat']): ?>
                            <span class="px-2 py-0.5 bg-amber-50 text-amber-700 rounded font-semibold"><?= h($p['peringkat']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($p['keterangan']): ?>
                        <p class="text-label-sm text-text-muted italic mb-2"><?= h($p['keterangan']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center gap-1 pt-2 border-t border-outline-variant/40">
                        <a href="?tab=prestasi&edit_prestasi=<?= (int)$p['id'] ?>&kelas_id=<?= $kelasId ?>&tahun=<?= urlencode((string)$tahunFilter) ?>" class="text-text-muted hover:text-primary p-1" title="Edit">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </a>
                        <form action="../actions/nilai_tambahan/prestasi_hapus.php" method="post" onsubmit="return confirm('Hapus data prestasi ini?');" class="inline">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
                            <button type="submit" class="text-text-muted hover:text-error p-1" title="Hapus">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=prestasi&kelas_id=<?= $kelasId ?>&tahun=<?= urlencode((string)$tahunFilter) ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg">
        <form action="../actions/nilai_tambahan/prestasi_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-bold text-text-main"><?= $editData ? 'Edit Prestasi' : 'Tambah Prestasi' ?></h3>
                <a href="?tab=prestasi&kelas_id=<?= $kelasId ?>&tahun=<?= urlencode((string)$tahunFilter) ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
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
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Kelas</label>
                        <select required name="kelas_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach ($daftarKelas as $k): ?>
                                <option value="<?= (int)$k['id'] ?>" <?= (int)($editData['kelas_id'] ?? $kelasId) === (int)$k['id'] ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jenis Prestasi</label>
                        <select required name="jenis_prestasi" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach (['akademik','non-akademik','olahraga','seni','lainnya'] as $jp): ?>
                                <option value="<?= $jp ?>" <?= ($editData['jenis_prestasi'] ?? 'akademik') === $jp ? 'selected' : '' ?>><?= ucfirst(str_replace('-', ' ', $jp)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Nama Prestasi</label>
                    <input required name="nama_prestasi" value="<?= h($editData['nama_prestasi'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: Juara 1 Olimpiade Matematika">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Tingkat</label>
                        <select required name="tingkat" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach (['Sekolah','Kecamatan','Kabupaten','Provinsi','Nasional','Internasional'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($editData['tingkat'] ?? 'Sekolah') === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Peringkat</label>
                        <input name="peringkat" value="<?= h($editData['peringkat'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Juara 1">
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Tahun</label>
                        <input required type="number" name="tahun" min="2000" max="2100" value="<?= h((string)($editData['tahun'] ?? date('Y'))) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Keterangan (opsional)</label>
                    <textarea name="keterangan" rows="2" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"><?= h($editData['keterangan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="?tab=prestasi&kelas_id=<?= $kelasId ?>&tahun=<?= urlencode((string)$tahunFilter) ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>