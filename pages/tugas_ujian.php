<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'Tugas & Ujian';
$currentPage = 'tugas';
$user        = currentUser();
$isAdmin     = isAdmin();

// ---------------------------------------------------------------------
// FILTER
// ---------------------------------------------------------------------
$mapelId = $_GET['mapel_id'] ?? '';
$kelasId = $_GET['kelas_id'] ?? '';
$jenis   = $_GET['jenis'] ?? '';
$status  = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];
if (!$isAdmin) { $where[] = 't.dibuat_oleh = ?'; $params[] = $user['id']; }
if ($mapelId !== '') { $where[] = 't.mapel_id = ?'; $params[] = $mapelId; }
if ($kelasId !== '') { $where[] = 't.kelas_id = ?'; $params[] = $kelasId; }
if ($jenis !== '')   { $where[] = 't.jenis = ?';   $params[] = $jenis; }
if ($status !== '')  { $where[] = 't.status = ?';  $params[] = $status; }
$whereSql = implode(' AND ', $where);

$sql = "SELECT t.*, mp.nama_mapel, k.nama_kelas,
               (SELECT COUNT(*) FROM siswa WHERE kelas_id = t.kelas_id AND status='aktif') AS total_siswa,
               (SELECT COUNT(*) FROM pengumpulan_tugas WHERE tugas_id = t.id AND status IN ('terkumpul','terlambat','dinilai')) AS total_kumpul
        FROM tugas_ujian t
        JOIN mata_pelajaran mp ON mp.id = t.mapel_id
        JOIN kelas k ON k.id = t.kelas_id
        WHERE $whereSql
        ORDER BY t.tanggal_deadline DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarTugas = $stmt->fetchAll();

$daftarMapel = $pdo->query('SELECT * FROM mata_pelajaran ORDER BY nama_mapel')->fetchAll();
$daftarKelas = $pdo->query('SELECT * FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();

$modalTambah = !empty($_GET['tambah']);

// ---------------------------------------------------------------------
// PANEL PENILAIAN (?nilai=ID)
// ---------------------------------------------------------------------
$tugasDinilai = null;
$daftarPengumpulan = [];
if (!empty($_GET['nilai'])) {
    $stmt = $pdo->prepare('SELECT t.*, mp.nama_mapel, k.nama_kelas FROM tugas_ujian t
                            JOIN mata_pelajaran mp ON mp.id = t.mapel_id
                            JOIN kelas k ON k.id = t.kelas_id WHERE t.id = ?');
    $stmt->execute([(int)$_GET['nilai']]);
    $tugasDinilai = $stmt->fetch() ?: null;

    if ($tugasDinilai) {
        $stmt = $pdo->prepare("SELECT s.id AS siswa_id, s.nama_lengkap, s.nis,
                                       pt.status, pt.nilai, pt.waktu_kumpul
                                FROM siswa s
                                LEFT JOIN pengumpulan_tugas pt ON pt.siswa_id = s.id AND pt.tugas_id = ?
                                WHERE s.kelas_id = ? AND s.status = 'aktif'
                                ORDER BY s.nama_lengkap");
        $stmt->execute([$tugasDinilai['id'], $tugasDinilai['kelas_id']]);
        $daftarPengumpulan = $stmt->fetchAll();
    }
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';

$labelJenis  = ['tugas' => 'Tugas', 'kuis' => 'Kuis', 'uts' => 'UTS', 'uas' => 'UAS'];
$warnaJenis  = ['tugas' => 'bg-primary/10 text-primary', 'kuis' => 'bg-secondary-container/20 text-secondary', 'uts' => 'bg-tertiary/10 text-tertiary', 'uas' => 'bg-error-container text-error'];
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-background">
    <div class="p-md md:p-lg max-w-[1440px] mx-auto">
        <?php renderFlash(); ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-lg gap-4">
            <div>
                <h2 class="text-headline-lg font-headline-lg font-semibold text-text-main mb-1">Tugas &amp; Ujian</h2>
                <p class="text-body-md font-body-md text-text-muted">Kelola tugas, kuis, dan jadwal ujian untuk setiap kelas.</p>
            </div>
            <a href="?tambah=1" class="bg-primary hover:bg-primary-container text-white px-6 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
                <span class="material-symbols-outlined">add</span> Buat Tugas Baru
            </a>
        </div>

        <!-- Filter -->
        <form method="get" class="bg-surface-white rounded-xl p-md mb-lg flex flex-wrap gap-3 items-center ambient-shadow-1">
            <select name="jenis" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <option value="">Semua Jenis</option>
                <?php foreach ($labelJenis as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $jenis === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <select name="mapel_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <option value="">Semua Mapel</option>
                <?php foreach ($daftarMapel as $mp): ?>
                    <option value="<?= (int)$mp['id'] ?>" <?= (string)$mapelId === (string)$mp['id'] ? 'selected' : '' ?>><?= h($mp['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <option value="">Semua Kelas</option>
                <?php foreach ($daftarKelas as $k): ?>
                    <option value="<?= (int)$k['id'] ?>" <?= (string)$kelasId === (string)$k['id'] ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <option value="">Semua Status</option>
                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
            </select>
            <?php if ($mapelId !== '' || $kelasId !== '' || $jenis !== '' || $status !== ''): ?>
                <a href="tugas_ujian.php" class="text-label-md font-label-md text-primary hover:underline">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Daftar Tugas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">
            <?php if (empty($daftarTugas)): ?>
                <div class="col-span-full text-center py-xl text-text-muted bg-surface-white rounded-xl ambient-shadow-1">
                    <span class="material-symbols-outlined text-[48px] mb-2 block">assignment</span>
                    Belum ada tugas/ujian yang cocok dengan filter ini.
                </div>
            <?php endif; ?>
            <?php foreach ($daftarTugas as $t):
                $sudahLewat = strtotime($t['tanggal_deadline']) < time();
                $progress   = $t['total_siswa'] > 0 ? round(($t['total_kumpul'] / $t['total_siswa']) * 100) : 0;
            ?>
                <div class="bg-surface-white rounded-xl p-lg ambient-shadow-1 flex flex-col gap-md">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex gap-2 flex-wrap">
                            <span class="text-label-md font-label-md px-2 py-1 rounded <?= $warnaJenis[$t['jenis']] ?>"><?= $labelJenis[$t['jenis']] ?></span>
                            <span class="text-label-md font-label-md px-2 py-1 rounded <?= $t['status'] === 'aktif' ? 'bg-secondary-container/20 text-secondary' : 'bg-surface-container text-text-muted' ?>"><?= $t['status'] === 'aktif' ? 'Aktif' : 'Selesai' ?></span>
                        </div>
                        <form action="../actions/tugas/tugas_hapus.php" method="post" onsubmit="return confirm('Hapus tugas ini beserta semua data pengumpulannya?');">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="text-text-muted hover:text-error p-1" title="Hapus"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                        </form>
                    </div>
                    <div>
                        <h4 class="text-headline-sm font-headline-sm text-text-main mb-1"><?= h($t['judul']) ?></h4>
                        <p class="text-body-sm text-text-muted"><?= h($t['nama_mapel']) ?> &bull; <?= h($t['nama_kelas']) ?></p>
                        <?php if ($t['deskripsi']): ?><p class="text-body-sm text-text-muted mt-2"><?= h($t['deskripsi']) ?></p><?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2 text-label-md font-label-md <?= $sudahLewat && $t['status'] === 'aktif' ? 'text-error' : 'text-text-muted' ?>">
                        <span class="material-symbols-outlined text-[18px]">schedule</span>
                        Batas waktu: <?= formatTanggalIndo($t['tanggal_deadline'], true) ?>
                    </div>
                    <div>
                        <div class="flex justify-between text-label-md font-label-md text-text-muted mb-1">
                            <span>Pengumpulan</span>
                            <span><?= (int)$t['total_kumpul'] ?> / <?= (int)$t['total_siswa'] ?> siswa</span>
                        </div>
                        <div class="w-full h-2 bg-surface-container rounded-full overflow-hidden">
                            <div class="h-full bg-primary" style="width: <?= $progress ?>%"></div>
                        </div>
                    </div>
                    <a href="?nilai=<?= (int)$t['id'] ?>" class="w-full text-center py-2 rounded-lg bg-primary text-white text-label-lg font-label-lg hover:bg-primary-container transition-colors">
                        Lihat Pengumpulan &amp; Beri Nilai
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- ============================================================= -->
<!-- MODAL: BUAT TUGAS BARU                                        -->
<!-- ============================================================= -->
<div class="fixed inset-0 z-[60] <?= $modalTambah ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='tugas_ujian.php'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <form action="../actions/tugas/tugas_simpan.php" method="post" class="p-lg">
            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-headline-sm text-text-main">Buat Tugas / Ujian Baru</h3>
                <a href="tugas_ujian.php" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Judul</label>
                    <input required name="judul" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: Kuis Trigonometri Bab 2">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Jenis</label>
                        <select name="jenis" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach ($labelJenis as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Batas Waktu</label>
                        <input required type="datetime-local" name="tanggal_deadline" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Mata Pelajaran</label>
                        <select required name="mapel_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach ($daftarMapel as $mp): ?>
                                <option value="<?= (int)$mp['id'] ?>"><?= h($mp['nama_mapel']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-label-md font-label-md text-text-main block mb-1">Kelas</label>
                        <select required name="kelas_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php foreach ($daftarKelas as $k): ?>
                                <option value="<?= (int)$k['id'] ?>"><?= h($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-label-md font-label-md text-text-main block mb-1">Deskripsi (opsional)</label>
                    <textarea name="deskripsi" rows="3" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Instruksi tambahan untuk siswa..."></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-outline-variant">
                <a href="tugas_ujian.php" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL: PENGUMPULAN & PENILAIAN                                -->
<!-- ============================================================= -->
<div class="fixed inset-0 z-[60] <?= $tugasDinilai ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='tugas_ujian.php'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <?php if ($tugasDinilai): ?>
        <form action="../actions/tugas/tugas_nilai_simpan.php" method="post" class="p-lg">
            <input type="hidden" name="tugas_id" value="<?= (int)$tugasDinilai['id'] ?>">
            <div class="flex items-center justify-between mb-md border-b border-outline-variant pb-4">
                <div>
                    <h3 class="text-headline-sm font-headline-sm text-text-main"><?= h($tugasDinilai['judul']) ?></h3>
                    <p class="text-body-sm text-text-muted"><?= h($tugasDinilai['nama_mapel']) ?> &bull; <?= h($tugasDinilai['nama_kelas']) ?></p>
                </div>
                <a href="tugas_ujian.php" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <div class="max-h-[50vh] overflow-y-auto -mx-lg px-lg">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-label-md font-label-md text-text-muted border-b border-outline-variant">
                            <th class="py-2">Nama Siswa</th>
                            <th class="py-2">Status</th>
                            <th class="py-2 w-24">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        <?php foreach ($daftarPengumpulan as $p): ?>
                            <?php $st = $p['status'] ?? 'belum'; ?>
                            <tr>
                                <td class="py-2 text-body-sm text-text-main">
                                    <?= h($p['nama_lengkap']) ?>
                                    <input type="hidden" name="siswa_id[]" value="<?= (int)$p['siswa_id'] ?>">
                                </td>
                                <td class="py-2">
                                    <span class="text-label-md px-2 py-0.5 rounded <?= $st === 'dinilai' ? 'bg-primary/10 text-primary' : ($st === 'belum' ? 'bg-error-container text-error' : 'bg-secondary-container/20 text-secondary') ?>">
                                        <?= ['belum' => 'Belum Kumpul', 'terkumpul' => 'Sudah Kumpul', 'terlambat' => 'Terlambat', 'dinilai' => 'Dinilai'][$st] ?>
                                    </span>
                                </td>
                                <td class="py-2">
                                    <input type="number" min="0" max="100" name="nilai[]" value="<?= h((string)($p['nilai'] ?? '')) ?>" class="w-20 px-2 py-1 border border-outline-variant rounded-lg text-body-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="-">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end gap-3 pt-6 mt-4 border-t border-outline-variant">
                <a href="tugas_ujian.php" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Tutup</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan Nilai</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
