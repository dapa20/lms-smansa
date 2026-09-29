<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$mapelId = (int)($_GET['mapel_id'] ?? 0);

if (!$kelasId || !$mapelId) {
    header('Location: tugas_ujian.php');
    exit;
}

$user = currentUser();
$isAdmin = isAdmin();

// Validasi akses kelas
if (!$isAdmin) {
    $stmtCek = $pdo->prepare("SELECT 1 FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ?");
    $stmtCek->execute([$user['id'], $kelasId, $mapelId]);
    if (!$stmtCek->fetch()) {
        die("Akses ditolak. Anda bukan pengampu mata pelajaran ini di kelas tersebut.");
    }
}

$kelas = $pdo->prepare("SELECT * FROM kelas WHERE id = ?");
$kelas->execute([$kelasId]);
$dataKelas = $kelas->fetch();

$mapel = $pdo->prepare("SELECT * FROM mata_pelajaran WHERE id = ?");
$mapel->execute([$mapelId]);
$dataMapel = $mapel->fetch();

if (!$dataKelas || !$dataMapel) {
    die("Data kelas atau mata pelajaran tidak ditemukan.");
}

$pageTitle = 'Ruang Tugas - ' . $dataKelas['nama_kelas'];
$currentPage = 'tugas';

// Fetch tasks
$stmtTugas = $pdo->prepare("SELECT t.*, 
       (SELECT COUNT(*) FROM siswa WHERE kelas_id = t.kelas_id AND status='aktif') AS total_siswa,
       (SELECT COUNT(*) FROM pengumpulan_tugas WHERE tugas_id = t.id AND status IN ('terkumpul','terlambat','dinilai')) AS total_kumpul
    FROM tugas_ujian t
    WHERE t.kelas_id = ? AND t.mapel_id = ?
    ORDER BY t.tanggal_deadline DESC");
$stmtTugas->execute([$kelasId, $mapelId]);
$daftarTugas = $stmtTugas->fetchAll();

$modalTambah = !empty($_GET['tambah']);
$labelJenis  = ['tugas' => 'Tugas', 'kuis' => 'Kuis', 'uts' => 'UTS', 'uas' => 'UAS'];
$warnaJenis  = ['tugas' => 'bg-primary/10 text-primary', 'kuis' => 'bg-secondary-container/20 text-secondary', 'uts' => 'bg-tertiary/10 text-tertiary', 'uas' => 'bg-error-container text-error'];

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-surface-container-lowest">
    <div class="p-md md:p-lg max-w-[1440px] mx-auto">
        <?php renderFlash(); ?>

        <!-- Breadcrumb & Header -->
        <div class="flex items-center gap-2 text-label-md font-label-md text-text-muted mb-4">
            <a href="tugas_ujian.php" class="hover:text-primary transition-colors">Tugas &amp; Ujian</a>
            <span class="material-symbols-outlined text-[16px]">chevron_right</span>
            <span class="text-text-main"><?= h($dataKelas['nama_kelas']) ?></span>
        </div>

        <!-- Banner Ruang Kelas -->
        <div class="relative bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl p-xl mb-lg overflow-hidden text-white shadow-md">
            <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle,rgba(255,255,255,0.8)_20%,transparent_20%)]" style="background-size: 24px 24px;"></div>
            <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
                <div>
                    <h1 class="text-display-sm font-display-sm font-bold mb-2"><?= h($dataMapel['nama_mapel']) ?></h1>
                    <p class="text-title-md font-title-md opacity-90 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">school</span> Kelas <?= h($dataKelas['nama_kelas']) ?>
                    </p>
                </div>
                <button onclick="window.location.href='?kelas_id=<?= $kelasId ?>&mapel_id=<?= $mapelId ?>&tambah=1'" class="bg-white text-blue-700 hover:bg-blue-50 px-6 py-3 rounded-xl text-label-lg font-label-lg font-bold flex items-center gap-2 transition-all shadow-sm">
                    <span class="material-symbols-outlined">add_task</span> Buat Tugas Baru
                </button>
            </div>
        </div>

        <!-- Stream Tugas (Google Classroom Style) -->
        <div class="flex flex-col lg:flex-row gap-lg">
            
            <!-- Main Content: Daftar Tugas -->
            <div class="flex-1 space-y-md">
                <?php if (empty($daftarTugas)): ?>
                    <div class="text-center py-20 bg-white rounded-2xl shadow-sm border border-outline-variant">
                        <div class="w-20 h-20 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="material-symbols-outlined text-[40px]">assignment</span>
                        </div>
                        <h3 class="text-title-lg font-title-lg font-semibold text-text-main mb-2">Belum ada tugas</h3>
                        <p class="text-body-md text-text-muted">Ini adalah tempat Anda akan berkomunikasi dan memberikan tugas ke kelas Anda.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($daftarTugas as $t):
                    $sudahLewat = strtotime($t['tanggal_deadline']) < time();
                    $progress   = $t['total_siswa'] > 0 ? round(($t['total_kumpul'] / $t['total_siswa']) * 100) : 0;
                ?>
                    <a href="tugas_penilaian.php?id=<?= (int)$t['id'] ?>" class="block bg-white rounded-2xl p-lg shadow-sm border border-outline-variant hover:shadow-md hover:border-primary/50 transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center flex-shrink-0 group-hover:bg-primary group-hover:text-white transition-colors">
                                <span class="material-symbols-outlined text-[24px]">assignment</span>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="text-title-md font-title-md font-bold text-text-main group-hover:text-primary transition-colors mb-1"><?= h($t['judul']) ?></h4>
                                        <p class="text-body-sm text-text-muted mb-3">Dibuat pada <?= date('d M Y, H:i', strtotime($t['created_at'])) ?></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <form action="../actions/tugas/tugas_hapus.php" method="post" onsubmit="event.preventDefault(); if(confirm('Hapus tugas ini?')) this.submit();">
                                            <?php csrfField(); ?>
                                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-full hover:bg-error-container hover:text-error text-text-muted flex items-center justify-center transition-colors"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                                        </form>
                                    </div>
                                </div>

                                <?php if ($t['deskripsi']): ?>
                                    <p class="text-body-md text-text-main mb-4 line-clamp-2"><?= nl2br(h($t['deskripsi'])) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($t['file_lampiran'])): ?>
                                    <div class="mb-4 inline-flex items-center gap-3 border border-outline-variant rounded-xl p-3 hover:bg-surface-container-lowest cursor-pointer transition-colors" onclick="event.preventDefault(); window.open('<?= APP_URL ?>/uploads/tugas/<?= h($t['file_lampiran']) ?>', '_blank')">
                                        <div class="w-10 h-10 bg-error/10 text-error rounded flex items-center justify-center">
                                            <span class="material-symbols-outlined">picture_as_pdf</span>
                                        </div>
                                        <div class="pr-4">
                                            <p class="text-label-md font-label-md text-text-main line-clamp-1"><?= h($t['file_lampiran']) ?></p>
                                            <p class="text-body-sm text-text-muted uppercase">Lampiran File</p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="flex flex-col sm:flex-row items-center gap-4 pt-4 border-t border-outline-variant mt-2">
                                    <div class="flex-1 flex items-center gap-2 text-label-md font-label-md <?= $sudahLewat ? 'text-error' : 'text-text-muted' ?>">
                                        <span class="material-symbols-outlined text-[18px]">schedule</span>
                                        Tenggat: <?= formatTanggalIndo($t['tanggal_deadline'], true) ?>
                                    </div>
                                    <div class="flex items-center gap-6">
                                        <div class="text-center">
                                            <div class="text-title-lg font-title-lg text-text-main font-semibold"><?= (int)$t['total_kumpul'] ?></div>
                                            <div class="text-label-sm font-label-sm text-text-muted">Diserahkan</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-title-lg font-title-lg text-text-main font-semibold"><?= max(0, $t['total_siswa'] - $t['total_kumpul']) ?></div>
                                            <div class="text-label-sm font-label-sm text-text-muted">Diberikan</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Sidebar Kecil: Tentang Kelas & Mendatang -->
            <div class="w-full lg:w-[320px] space-y-md">
                <div class="bg-white rounded-2xl shadow-sm border border-outline-variant p-4">
                    <h3 class="text-title-sm font-title-sm font-bold text-text-main mb-3">Kode Kelas</h3>
                    <div class="text-display-sm font-display-sm text-primary font-bold tracking-widest"><?= strtoupper(substr(md5($kelasId.$mapelId), 0, 6)) ?></div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-outline-variant p-4">
                    <h3 class="text-title-sm font-title-sm font-bold text-text-main mb-3">Mendatang</h3>
                    <?php
                        $adaMendatang = false;
                        foreach ($daftarTugas as $t) {
                            if (strtotime($t['tanggal_deadline']) > time()) {
                                $adaMendatang = true;
                                echo '<div class="text-body-sm text-text-muted mb-2">Tenggat: ' . date('d M, H:i', strtotime($t['tanggal_deadline'])) . '<br><b class="text-text-main">' . h($t['judul']) . '</b></div>';
                            }
                        }
                        if (!$adaMendatang) {
                            echo '<p class="text-body-sm text-text-muted">Hore, tidak ada tugas yang segera datang!</p>';
                        }
                    ?>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- ============================================================= -->
<!-- MODAL: BUAT TUGAS BARU (Google Classroom Style)               -->
<!-- ============================================================= -->
<div class="fixed inset-0 z-[60] <?= $modalTambah ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="window.location.href='?kelas_id=<?= $kelasId ?>&mapel_id=<?= $mapelId ?>'"></div>
    <div class="relative bg-surface-white rounded-2xl shadow-2xl w-full max-w-3xl h-[90vh] flex flex-col overflow-hidden">
        <form action="../actions/tugas/tugas_simpan.php" method="post" enctype="multipart/form-data" class="flex flex-col h-full">
            <?php csrfField(); ?>
            <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
            <input type="hidden" name="mapel_id" value="<?= $mapelId ?>">
            <input type="hidden" name="jenis" value="tugas"> <!-- Default -->
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant bg-surface-white">
                <div class="flex items-center gap-4">
                    <button type="button" onclick="window.location.href='?kelas_id=<?= $kelasId ?>&mapel_id=<?= $mapelId ?>'" class="w-10 h-10 rounded-full hover:bg-surface-container flex items-center justify-center text-text-muted transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                    <h3 class="text-title-lg font-title-lg font-semibold text-text-main">Tugas</h3>
                </div>
                <button type="submit" class="bg-primary hover:bg-primary-container text-white px-6 py-2 rounded-lg text-label-lg font-label-lg shadow-sm transition-colors">Tugaskan</button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-6 bg-surface-container-lowest">
                <div class="flex flex-col md:flex-row gap-6">
                    <!-- Kiri: Input Konten -->
                    <div class="flex-1 space-y-6">
                        <!-- Judul -->
                        <div class="bg-white rounded-xl border border-outline-variant p-1 shadow-sm focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all">
                            <input required type="text" name="judul" placeholder="Judul Tugas" class="w-full px-4 py-3 bg-transparent border-none outline-none text-title-lg font-title-lg placeholder-text-muted/60">
                        </div>

                        <!-- Deskripsi -->
                        <div class="bg-white rounded-xl border border-outline-variant shadow-sm focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all flex flex-col">
                            <div class="px-4 py-2 border-b border-outline-variant/60 flex items-center gap-2 text-text-muted">
                                <span class="material-symbols-outlined text-[18px]">format_bold</span>
                                <span class="material-symbols-outlined text-[18px]">format_italic</span>
                                <span class="material-symbols-outlined text-[18px]">format_underlined</span>
                                <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span>
                            </div>
                            <textarea name="deskripsi" rows="6" placeholder="Petunjuk (opsional)" class="w-full px-4 py-3 bg-transparent border-none outline-none text-body-lg resize-none placeholder-text-muted/60"></textarea>
                        </div>

                        <!-- Lampiran -->
                        <div>
                            <p class="text-label-md font-label-md text-text-main mb-2">Lampirkan File</p>
                            <label class="border-2 border-dashed border-outline-variant rounded-xl p-8 flex flex-col items-center justify-center text-text-muted hover:bg-surface-container hover:border-primary cursor-pointer transition-all">
                                <div class="w-12 h-12 bg-primary/10 text-primary rounded-full flex items-center justify-center mb-3">
                                    <span class="material-symbols-outlined text-[24px]">cloud_upload</span>
                                </div>
                                <span class="text-label-lg font-label-lg text-primary">Upload File Materi/Soal</span>
                                <span class="text-body-sm mt-1">Maks 10MB (PDF, DOCX, XLSX, JPG, PNG)</span>
                                <input type="file" name="file_lampiran" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                            </label>
                        </div>
                    </div>

                    <!-- Kanan: Setting Sidebar -->
                    <div class="w-full md:w-[280px] space-y-6">
                        <div>
                            <p class="text-label-md font-label-md text-text-main mb-2">Untuk Kelas</p>
                            <div class="bg-surface-container px-4 py-2.5 rounded-lg text-body-md text-text-main">
                                <?= h($dataKelas['nama_kelas']) ?>
                            </div>
                        </div>

                        <div>
                            <p class="text-label-md font-label-md text-text-main mb-2">Poin</p>
                            <select class="w-full bg-white border border-outline-variant rounded-lg px-4 py-2.5 text-body-md focus:border-primary outline-none">
                                <option value="100">100</option>
                                <option value="0">Tidak Dinilai</option>
                            </select>
                        </div>

                        <div>
                            <p class="text-label-md font-label-md text-text-main mb-2">Tenggat</p>
                            <input required type="datetime-local" name="tanggal_deadline" class="w-full bg-white border border-outline-variant rounded-lg px-4 py-2.5 text-body-md focus:border-primary outline-none">
                        </div>

                        <div>
                            <p class="text-label-md font-label-md text-text-main mb-2">Topik / Jenis</p>
                            <select name="jenis" class="w-full bg-white border border-outline-variant rounded-lg px-4 py-2.5 text-body-md focus:border-primary outline-none">
                                <?php foreach ($labelJenis as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Preview file name on upload
    const fileInput = document.querySelector('input[type="file"]');
    if(fileInput) {
        fileInput.addEventListener('change', function(e) {
            if(this.files && this.files[0]) {
                const label = this.parentElement.querySelector('.text-primary');
                if(label) label.textContent = this.files[0].name;
            }
        });
    }
</script>

</body>
</html>
