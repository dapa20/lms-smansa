<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$tugasId = (int)($_GET['id'] ?? 0);
if (!$tugasId) {
    redirect('tugas_ujian.php');
}

$user = currentUser();
$isAdmin = isAdmin();

// Ambil data tugas
$stmt = $pdo->prepare('SELECT t.*, mp.nama_mapel, k.nama_kelas, k.id AS kelas_id, mp.id AS mapel_id 
                        FROM tugas_ujian t
                        JOIN mata_pelajaran mp ON mp.id = t.mapel_id
                        JOIN kelas k ON k.id = t.kelas_id WHERE t.id = ?');
$stmt->execute([$tugasId]);
$tugas = $stmt->fetch();

if (!$tugas) {
    die("Data tugas tidak ditemukan.");
}

// Validasi akses guru
if (!$isAdmin) {
    $stmtCek = $pdo->prepare("SELECT 1 FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ?");
    $stmtCek->execute([$user['id'], $tugas['kelas_id'], $tugas['mapel_id']]);
    if (!$stmtCek->fetch()) {
        die("Akses ditolak. Anda bukan pengampu mata pelajaran ini di kelas tersebut.");
    }
}

// Ambil data pengumpulan per siswa
$stmtSiswa = $pdo->prepare("SELECT s.id AS siswa_id, s.nama_lengkap, s.nis,
                                pt.status, pt.nilai, pt.waktu_kumpul, pt.file_jawaban
                            FROM siswa s
                            LEFT JOIN pengumpulan_tugas pt ON pt.siswa_id = s.id AND pt.tugas_id = ?
                            WHERE s.kelas_id = ? AND s.status = 'aktif'
                            ORDER BY s.nama_lengkap");
$stmtSiswa->execute([$tugasId, $tugas['kelas_id']]);
$daftarSiswa = $stmtSiswa->fetchAll();

// Statistik
$totalSiswa = count($daftarSiswa);
$sudahDinilai = 0;
$diserahkan = 0;
$belumKumpul = 0;

foreach ($daftarSiswa as $s) {
    $st = $s['status'] ?? 'belum';
    if ($st === 'dinilai') $sudahDinilai++;
    elseif (in_array($st, ['terkumpul', 'terlambat'])) $diserahkan++;
    else $belumKumpul++;
}

$pageTitle = 'Penilaian Tugas: ' . $tugas['judul'];
$currentPage = 'tugas';

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-surface-container-lowest">
    <div class="p-md md:p-lg max-w-[1440px] mx-auto flex flex-col h-full">
        <?php renderFlash(); ?>

        <!-- Breadcrumb & Header -->
        <div class="flex items-center gap-2 text-label-md font-label-md text-text-muted mb-4">
            <a href="tugas_ujian.php" class="hover:text-primary transition-colors">Tugas &amp; Ujian</a>
            <span class="material-symbols-outlined text-[16px]">chevron_right</span>
            <a href="tugas_detail.php?kelas_id=<?= $tugas['kelas_id'] ?>&mapel_id=<?= $tugas['mapel_id'] ?>" class="hover:text-primary transition-colors"><?= h($tugas['nama_kelas']) ?></a>
            <span class="material-symbols-outlined text-[16px]">chevron_right</span>
            <span class="text-text-main line-clamp-1"><?= h($tugas['judul']) ?></span>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center bg-white rounded-2xl p-lg shadow-sm border border-outline-variant mb-lg gap-6">
            <div class="flex items-start gap-4 flex-1">
                <div class="w-14 h-14 rounded-full bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[28px]">assignment_turned_in</span>
                </div>
                <div>
                    <h2 class="text-headline-sm font-headline-sm font-bold text-text-main mb-1"><?= h($tugas['judul']) ?></h2>
                    <p class="text-body-md text-text-muted mb-2"><?= h($tugas['nama_mapel']) ?> &bull; Tenggat: <?= formatTanggalIndo($tugas['tanggal_deadline'], true) ?></p>
                    <div class="text-label-md font-label-md px-2 py-1 rounded bg-secondary-container/20 text-secondary inline-block">Poin Maks: 100</div>
                </div>
            </div>
            
            <div class="flex gap-4">
                <div class="text-center px-4 py-2 border-r border-outline-variant/50">
                    <div class="text-display-sm font-display-sm font-bold text-text-main"><?= $diserahkan ?></div>
                    <div class="text-label-md font-label-md text-text-muted">Diserahkan</div>
                </div>
                <div class="text-center px-4 py-2 border-r border-outline-variant/50">
                    <div class="text-display-sm font-display-sm font-bold text-text-main"><?= $belumKumpul ?></div>
                    <div class="text-label-md font-label-md text-text-muted">Diberikan</div>
                </div>
                <div class="text-center px-4 py-2">
                    <div class="text-display-sm font-display-sm font-bold text-primary"><?= $sudahDinilai ?></div>
                    <div class="text-label-md font-label-md text-text-muted">Dinilai</div>
                </div>
            </div>
        </div>

        <!-- Tabel Daftar Siswa & Form Penilaian -->
        <form action="../actions/tugas/tugas_nilai_simpan.php" method="post" class="bg-white rounded-2xl shadow-sm border border-outline-variant flex-1 overflow-hidden flex flex-col">
            <?php csrfField(); ?>
            <input type="hidden" name="tugas_id" value="<?= $tugasId ?>">
            
            <!-- Sticky Toolbar -->
            <div class="p-4 border-b border-outline-variant bg-surface-container-lowest sticky top-0 z-10 flex justify-between items-center">
                <h3 class="text-title-md font-title-md font-bold text-text-main">Daftar Pengumpulan Siswa</h3>
                <button type="submit" class="bg-primary hover:bg-primary-container text-white px-6 py-2 rounded-lg text-label-lg font-label-lg shadow-sm transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined">save</span> Simpan Nilai
                </button>
            </div>

            <!-- Table Wrapper -->
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface-container/50">
                        <tr class="text-label-md font-label-md text-text-muted border-b border-outline-variant">
                            <th class="py-3 px-6 w-12 text-center">No</th>
                            <th class="py-3 px-6">Nama Siswa / NIS</th>
                            <th class="py-3 px-6">Status Pengumpulan</th>
                            <th class="py-3 px-6">File Jawaban</th>
                            <th class="py-3 px-6 w-40 text-right">Nilai (/100)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        <?php if (empty($daftarSiswa)): ?>
                            <tr>
                                <td colspan="5" class="py-10 text-center text-text-muted">Tidak ada siswa di kelas ini.</td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($daftarSiswa as $idx => $p): 
                            $st = $p['status'] ?? 'belum';
                            $statusStyle = match($st) {
                                'belum' => 'text-error bg-error-container',
                                'terkumpul' => 'text-primary bg-primary/10',
                                'terlambat' => 'text-warning bg-warning-container',
                                'dinilai' => 'text-secondary bg-secondary-container/20',
                                default => 'text-text-muted bg-surface-container'
                            };
                            $statusLabel = match($st) {
                                'belum' => 'Belum Kumpul',
                                'terkumpul' => 'Diserahkan',
                                'terlambat' => 'Terlambat',
                                'dinilai' => 'Sudah Dinilai',
                                default => 'Tidak Diketahui'
                            };
                        ?>
                            <tr class="hover:bg-surface-container-lowest transition-colors <?= $st === 'belum' ? 'opacity-70' : '' ?>">
                                <td class="py-4 px-6 text-center text-body-sm text-text-muted"><?= $idx + 1 ?></td>
                                <td class="py-4 px-6">
                                    <div class="text-title-sm font-title-sm font-bold text-text-main"><?= h($p['nama_lengkap']) ?></div>
                                    <div class="text-body-sm text-text-muted"><?= h($p['nis']) ?></div>
                                    <input type="hidden" name="siswa_id[]" value="<?= (int)$p['siswa_id'] ?>">
                                </td>
                                <td class="py-4 px-6">
                                    <span class="text-label-sm font-label-sm px-2.5 py-1 rounded-full <?= $statusStyle ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                    <?php if ($st !== 'belum' && !empty($p['waktu_kumpul'])): ?>
                                        <div class="text-body-sm text-text-muted mt-1.5"><?= date('d M Y, H:i', strtotime($p['waktu_kumpul'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6">
                                    <?php if (!empty($p['file_jawaban'])): ?>
                                        <a href="<?= APP_URL ?>/uploads/tugas_siswa/<?= h($p['file_jawaban']) ?>" target="_blank" class="inline-flex items-center gap-2 text-primary hover:text-primary-container text-label-md font-label-md transition-colors bg-primary/5 px-3 py-1.5 rounded-lg border border-primary/20">
                                            <span class="material-symbols-outlined text-[18px]">download</span> Unduh File
                                        </a>
                                    <?php else: ?>
                                        <span class="text-text-muted text-body-sm italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <div class="inline-flex items-center justify-end">
                                        <input type="number" min="0" max="100" name="nilai[]" value="<?= h((string)($p['nilai'] ?? '')) ?>" 
                                            class="w-24 text-right px-3 py-2 border <?= $st === 'dinilai' ? 'border-secondary/50 bg-secondary/5' : 'border-outline-variant bg-white' ?> rounded-lg text-title-md font-title-md text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" 
                                            placeholder="-">
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 border-t border-outline-variant bg-surface-container-lowest text-right">
                <button type="submit" class="bg-primary hover:bg-primary-container text-white px-8 py-2.5 rounded-lg text-label-lg font-label-lg shadow-sm transition-colors inline-flex items-center gap-2">
                    <span class="material-symbols-outlined">done_all</span> Simpan Semua Nilai
                </button>
            </div>
        </form>

    </div>
</main>
</body>
</html>
