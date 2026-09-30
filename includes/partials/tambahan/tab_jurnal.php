<?php
/**
 * Partial: Tab "Jurnal" di halaman kelas_jadwal.php
 * Menampilkan dan mengelola jurnal mengajar harian guru.
 */

// Ambil semua jadwal milik guru (admin: pilih guru dari dropdown)
// Default ke $guruId yang sudah diset di halaman utama
$guruJurnal = $isAdmin ? ($_GET['guru_id'] ?? $guruId) : $user['id'];
$filterMapelId = $_GET['mapel_id'] ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';

// Daftar jadwal guru (untuk opsi jurnal)
$stmtJ = $pdo->prepare("SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, j.ruang, m.nama_mapel, k.nama_kelas, k.id AS kelas_id_val
                         FROM jadwal_mengajar j
                         JOIN mata_pelajaran m ON m.id = j.mapel_id
                         JOIN kelas k ON k.id = j.kelas_id
                         WHERE j.guru_id = ? AND j.jenis = 'reguler'
                         ORDER BY FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai");
$stmtJ->execute([$guruJurnal]);
$daftarJadwalGuru = $stmtJ->fetchAll();

// Query jurnal
$whereParams = [$guruJurnal];
$whereJ = 'j.guru_id = ?';
if ($filterMapelId !== '') { $whereJ .= ' AND j.mapel_id = ?'; $whereParams[] = (int)$filterMapelId; }
if ($filterTanggal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTanggal)) { $whereJ .= ' AND j.tanggal = ?'; $whereParams[] = $filterTanggal; }

$stmt = $pdo->prepare("SELECT j.*, m.nama_mapel, k.nama_kelas, u.nama_lengkap AS nama_guru
                        FROM jurnal_mengajar j
                        JOIN mata_pelajaran m ON m.id = j.mapel_id
                        JOIN kelas k ON k.id = j.kelas_id
                        JOIN users u ON u.id = j.guru_id
                        WHERE $whereJ
                        ORDER BY j.tanggal DESC, j.jam_mulai DESC
                        LIMIT 100");
$stmt->execute($whereParams);
$daftarJurnal = $stmt->fetchAll();

// Daftar mapel untuk filter
$daftarMapelJ = $pdo->query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

// Mode edit
$editData = null;
if (!empty($_GET['edit_jurnal'])) {
    $stmtE = $pdo->prepare('SELECT * FROM jurnal_mengajar WHERE id=?');
    $stmtE->execute([(int)$_GET['edit_jurnal']]);
    $editData = $stmtE->fetch() ?: null;
    if ($editData && !$isAdmin && (int)$editData['guru_id'] !== (int)$user['id']) $editData = null;
}
$modalTerbuka = !empty($_GET['tambah_jurnal']) || $editData !== null;
?>

<!-- Filter & Action -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="jurnal">
        <?php if ($isAdmin): ?>
            <input type="hidden" name="guru_id" value="<?= (int)$guruJurnal ?>">
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap">Guru:</label>
            <select name="guru_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[200px]">
                <?php foreach ($daftarGuru as $g): ?>
                    <option value="<?= (int)$g['id'] ?>" <?= (int)$guruJurnal === (int)$g['id'] ? 'selected' : '' ?>><?= h($g['nama_lengkap']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap ml-2">Mapel:</label>
        <select name="mapel_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[160px]">
            <option value="">Semua Mapel</option>
            <?php foreach ($daftarMapelJ as $mp): ?>
                <option value="<?= (int)$mp['id'] ?>" <?= (string)$filterMapelId === (string)$mp['id'] ? 'selected' : '' ?>><?= h($mp['nama_mapel']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap ml-2">Tanggal:</label>
        <input type="date" name="tanggal" value="<?= h($filterTanggal) ?>" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
        <?php if ($filterTanggal !== '' || $filterMapelId !== ''): ?>
            <a href="?tab=jurnal<?= $isAdmin ? '&guru_id='.(int)$guruJurnal : '' ?>" class="text-primary hover:underline text-label-md">Reset</a>
        <?php endif; ?>
        <a href="?tab=jurnal&<?= $isAdmin ? 'guru_id='.(int)$guruJurnal.'&' : '' ?>tambah_jurnal=1" class="ml-auto bg-primary hover:bg-primary-container text-white px-4 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">edit_note</span> Tambah Jurnal
        </a>
    </form>
</div>

<!-- Ringkasan -->
<?php
$totalJurnal = count($daftarJurnal);
$totalJam = 0;
foreach ($daftarJurnal as $j) {
    $totalJam += (strtotime($j['jam_selesai']) - strtotime($j['jam_mulai'])) / 3600;
}
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-md mb-lg">
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Total Jurnal</p>
        <p class="text-headline-sm font-bold text-primary"><?= $totalJurnal ?></p>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40">
        <p class="text-label-sm text-text-muted">Total Jam Mengajar</p>
        <p class="text-headline-sm font-bold text-text-main"><?= round($totalJam, 1) ?> jam</p>
    </div>
</div>

<!-- Daftar Jurnal -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">auto_stories</span>
            Jurnal Mengajar
        </h3>
    </div>

    <?php if (empty($daftarJurnal)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">auto_stories</span>
            <p class="text-body-md text-text-muted">Belum ada jurnal mengajar. Klik "Tambah Jurnal" untuk mulai mencatat.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                        <th class="p-4 w-28">Tanggal</th>
                        <th class="p-4">Mapel</th>
                        <th class="p-4">Kelas</th>
                        <th class="p-4">Jam</th>
                        <th class="p-4">Materi</th>
                        <th class="p-4 text-center w-20">Kehadiran</th>
                        <th class="p-4 text-center w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-sm">
                    <?php foreach ($daftarJurnal as $j):
                        $totalAbsen = (int)$j['siswa_hadir'] + (int)$j['siswa_izin'] + (int)$j['siswa_sakit'] + (int)$j['siswa_alpa'];
                    ?>
                        <tr class="hover:bg-surface-bright transition-colors">
                            <td class="p-4 text-text-main font-mono text-xs"><?= formatTanggalIndo($j['tanggal']) ?></td>
                            <td class="p-4 font-semibold text-text-main"><?= h($j['nama_mapel']) ?></td>
                            <td class="p-4 text-text-main"><?= h($j['nama_kelas']) ?></td>
                            <td class="p-4 text-text-muted font-mono text-xs"><?= substr($j['jam_mulai'],0,5) ?>–<?= substr($j['jam_selesai'],0,5) ?></td>
                            <td class="p-4 text-text-main max-w-xs">
                                <div class="font-semibold line-clamp-1"><?= h($j['materi']) ?></div>
                                <?php if ($j['kegiatan']): ?>
                                    <div class="text-label-sm text-text-muted line-clamp-1"><?= h($j['kegiatan']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php if ($totalAbsen > 0): ?>
                                    <span class="inline-block px-2 py-1 bg-emerald-50 text-emerald-700 rounded text-label-sm font-bold">
                                        <?= (int)$j['siswa_hadir'] ?>/<?= $totalAbsen ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-text-muted text-label-sm">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="?tab=jurnal<?= $isAdmin ? '&guru_id='.(int)$guruJurnal : '' ?>&edit_jurnal=<?= (int)$j['id'] ?>" class="text-text-muted hover:text-primary p-1" title="Edit">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </a>
                                    <form action="../actions/jurnal/jurnal_hapus.php" method="post" onsubmit="return confirm('Hapus jurnal ini?');">
                                        <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
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

<!-- Modal Tambah/Edit Jurnal -->
<div class="fixed inset-0 z-[60] <?= $modalTerbuka ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='?tab=jurnal<?= $isAdmin ? '&guru_id='.(int)$guruJurnal : '' ?>&mapel_id=<?= urlencode((string)$filterMapelId) ?>&tanggal=<?= urlencode((string)$filterTanggal) ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <form action="../actions/jurnal/jurnal_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-bold text-text-main"><?= $editData ? 'Edit Jurnal Mengajar' : 'Tambah Jurnal Mengajar' ?></h3>
                <a href="?tab=jurnal<?= $isAdmin ? '&guru_id='.(int)$guruJurnal : '' ?>&mapel_id=<?= urlencode((string)$filterMapelId) ?>&tanggal=<?= urlencode((string)$filterTanggal) ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">

            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Pilih Jadwal</label>
                    <select required name="jadwal_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="">-- Pilih Jadwal --</option>
                        <?php foreach ($daftarJadwalGuru as $j): ?>
                            <option value="<?= (int)$j['id'] ?>" <?= ($editData && (int)$editData['jadwal_id'] === (int)$j['id']) ? 'selected' : '' ?>>
                                <?= h($j['hari']) ?> <?= substr($j['jam_mulai'],0,5) ?>–<?= substr($j['jam_selesai'],0,5) ?> | <?= h($j['nama_mapel']) ?> — <?= h($j['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Tanggal</label>
                        <input required type="date" name="tanggal" value="<?= h($editData['tanggal'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jam Mulai</label>
                        <input type="time" name="jam_mulai" value="<?= h(substr($editData['jam_mulai'] ?? '',0,5)) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jam Selesai</label>
                        <input type="time" name="jam_selesai" value="<?= h(substr($editData['jam_selesai'] ?? '',0,5)) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Materi Pembelajaran <span class="text-error">*</span></label>
                    <input required name="materi" value="<?= h($editData['materi'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: Persamaan Trigonometri">
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Metode / Kegiatan Pembelajaran</label>
                    <textarea name="kegiatan" rows="3" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Diskusi kelompok, ceramah, dll"><?= h($editData['kegiatan'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Rekap Kehadiran Siswa</label>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <label class="text-label-sm text-text-muted block mb-1">Hadir</label>
                            <input type="number" min="0" name="siswa_hadir" value="<?= h((string)($editData['siswa_hadir'] ?? 0)) ?>" class="w-full px-3 py-2 rounded-lg border border-outline-variant focus:border-primary outline-none">
                        </div>
                        <div>
                            <label class="text-label-sm text-text-muted block mb-1">Izin</label>
                            <input type="number" min="0" name="siswa_izin" value="<?= h((string)($editData['siswa_izin'] ?? 0)) ?>" class="w-full px-3 py-2 rounded-lg border border-outline-variant focus:border-primary outline-none">
                        </div>
                        <div>
                            <label class="text-label-sm text-text-muted block mb-1">Sakit</label>
                            <input type="number" min="0" name="siswa_sakit" value="<?= h((string)($editData['siswa_sakit'] ?? 0)) ?>" class="w-full px-3 py-2 rounded-lg border border-outline-variant focus:border-primary outline-none">
                        </div>
                        <div>
                            <label class="text-label-sm text-text-muted block mb-1">Alpa</label>
                            <input type="number" min="0" name="siswa_alpa" value="<?= h((string)($editData['siswa_alpa'] ?? 0)) ?>" class="w-full px-3 py-2 rounded-lg border border-outline-variant focus:border-primary outline-none">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Catatan / Hambatan</label>
                    <textarea name="catatan" rows="2" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Catatan tambahan, hambatan, hal istimewa..."><?= h($editData['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="?tab=jurnal<?= $isAdmin ? '&guru_id='.(int)$guruJurnal : '' ?>&mapel_id=<?= urlencode((string)$filterMapelId) ?>&tanggal=<?= urlencode((string)$filterTanggal) ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan Jurnal</button>
            </div>
        </form>
    </div>
</div>