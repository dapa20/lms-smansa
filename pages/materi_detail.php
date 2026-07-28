<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user = currentUser();
$isAdmin = isAdmin();

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$mapelId = (int)($_GET['mapel_id'] ?? 0);

if ($kelasId <= 0 || $mapelId <= 0) {
    setFlash('error', 'Kelas atau Mata Pelajaran tidak ditemukan.');
    redirect('materi.php');
}

// Ambil info kelas & mapel
$stmtK = $pdo->prepare("SELECT k.*, mp.nama_mapel, mp.kode_mapel, u.nama_lengkap AS nama_guru, u.nip AS nip_guru
                       FROM kelas k
                       JOIN mata_pelajaran mp ON mp.id = ?
                       LEFT JOIN jadwal_mengajar j ON j.kelas_id = k.id AND j.mapel_id = mp.id
                       LEFT JOIN users u ON u.id = j.guru_id
                       WHERE k.id = ?");
$stmtK->execute([$mapelId, $kelasId]);
$infoKelas = $stmtK->fetch();

if (!$infoKelas) {
    setFlash('error', 'Detail kelas tidak ditemukan.');
    redirect('materi.php');
}

// Hitung total siswa di kelas ini
$stmtSiswa = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE kelas_id = ? AND status = 'aktif'");
$stmtSiswa->execute([$kelasId]);
$totalSiswa = (int)$stmtSiswa->fetchColumn();

// Ambil semua section beserta item materi
$stmtSec = $pdo->prepare("SELECT * FROM materi_section WHERE kelas_id = ? AND mapel_id = ? ORDER BY urutan ASC, id ASC");
$stmtSec->execute([$kelasId, $mapelId]);
$sections = $stmtSec->fetchAll();

// Jika belum ada section sama sekali, buatkan section default
if (empty($sections)) {
    $insGen = $pdo->prepare("INSERT INTO materi_section (kelas_id, mapel_id, judul, urutan, dibuat_oleh) VALUES (?, ?, 'General', 1, ?)");
    $insGen->execute([$kelasId, $mapelId, $user['id']]);
    
    $insSec1 = $pdo->prepare("INSERT INTO materi_section (kelas_id, mapel_id, judul, urutan, dibuat_oleh) VALUES (?, ?, 'New section', 2, ?)");
    $insSec1->execute([$kelasId, $mapelId, $user['id']]);

    // Re-fetch
    $stmtSec->execute([$kelasId, $mapelId]);
    $sections = $stmtSec->fetchAll();
}

// Ambil item per section
$sectionItems = [];
foreach ($sections as $s) {
    $stmtItem = $pdo->prepare("SELECT mi.*, u.nama_lengkap AS nama_pengunggah
                               FROM materi_item mi
                               JOIN users u ON u.id = mi.diunggah_oleh
                               WHERE mi.section_id = ?
                               ORDER BY mi.created_at ASC");
    $stmtItem->execute([$s['id']]);
    $sectionItems[$s['id']] = $stmtItem->fetchAll();
}

$pageTitle   = h($infoKelas['nama_mapel']) . ' - ' . h($infoKelas['nama_kelas']);
$currentPage = 'materi';

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-background">
    <div class="p-md md:p-lg max-w-[1440px] mx-auto">
        <?php renderFlash(); ?>

        <!-- Breadcrumb & Top Bar -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-md gap-4">
            <div>
                <nav class="flex items-center gap-2 text-body-sm font-body-sm text-text-muted mb-1">
                    <a href="materi.php" class="hover:text-primary transition-colors">Materi Pembelajaran</a>
                    <span>/</span>
                    <span class="text-text-main font-semibold"><?= h($infoKelas['nama_kelas']) ?></span>
                </nav>
                <h2 class="text-headline-lg font-headline-lg font-semibold text-text-main">
                    <?= h($infoKelas['nama_mapel']) ?> (<?= h($infoKelas['nama_kelas']) ?>)
                </h2>
            </div>

            <!-- Top Right Bar (Your Progress & Collapse All Buttons - Match Image 2) -->
            <div class="flex items-center gap-4 self-end md:self-auto">
                <div class="text-right">
                    <span class="text-label-sm font-label-sm text-text-muted block">Your progress <strong class="text-primary">0%</strong></span>
                    <div class="w-32 h-2 bg-surface-container rounded-full overflow-hidden mt-1">
                        <div class="h-full bg-primary rounded-full" style="width: 0%;"></div>
                    </div>
                </div>

                <button type="button" onclick="toggleAllSections()" id="btn-toggle-all"
                        class="text-label-md font-label-md text-text-muted hover:text-primary transition-colors border border-outline-variant px-3 py-1.5 rounded-lg bg-white shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[18px]">unfold_less</span>
                    <span id="label-toggle-all">Collapse all</span>
                </button>

                <button type="button" onclick="openModalAddSection()" 
                        class="bg-primary hover:bg-primary-container text-white px-4 py-2 rounded-lg text-label-md font-label-md flex items-center gap-2 transition-colors shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">add</span> Tambah Section
                </button>
            </div>
        </div>

        <!-- Main Grid Layout (Left: Course Sections | Right: Course Content & Info Panel - Match Image 2) -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-lg">
            
            <!-- Left Column: Sections List (3 Columns wide) -->
            <div class="lg:col-span-3 space-y-md">
                <?php foreach ($sections as $index => $sec): 
                    $items = $sectionItems[$sec['id']] ?? [];
                    $isGeneral = strtolower(trim($sec['judul'])) === 'general';
                ?>
                    <!-- Section Card Accordion (Persis Gambar 2) -->
                    <div class="bg-white rounded-xl shadow-sm border border-outline-variant/80 overflow-hidden transition-all duration-200 section-card" id="section-<?= $sec['id'] ?>">
                        
                        <!-- Header Accordion -->
                        <div class="bg-[#f4f6f8] border-b border-outline-variant/60 px-md py-3.5 flex items-center justify-between cursor-pointer select-none"
                             onclick="toggleSection(<?= $sec['id'] ?>)">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <button type="button" class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-text-muted hover:text-primary transition-colors flex-shrink-0">
                                    <span class="material-symbols-outlined text-[20px] transition-transform duration-200 chevron-icon" id="chevron-<?= $sec['id'] ?>">expand_more</span>
                                </button>
                                
                                <h3 class="font-title-md text-title-md font-bold text-text-main truncate">
                                    <?= h($sec['judul']) ?>
                                </h3>
                                <span class="text-label-sm font-label-sm bg-surface-container-high text-text-muted px-2 py-0.5 rounded-full flex-shrink-0">
                                    <?= count($items) ?> item
                                </span>
                            </div>

                            <!-- Opsi Aksi untuk Guru/Admin -->
                            <div class="flex items-center gap-2" onclick="event.stopPropagation()">
                                <button type="button" onclick="openModalAddItem(<?= $sec['id'] ?>, '<?= h(addslashes($sec['judul'])) ?>')" 
                                        class="bg-primary/10 hover:bg-primary/20 text-primary px-3 py-1.5 rounded-lg text-label-sm font-label-sm font-medium flex items-center gap-1 transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">add_circle</span> Tambah Konten
                                </button>

                                <button type="button" onclick="openModalEditSection(<?= $sec['id'] ?>, '<?= h(addslashes($sec['judul'])) ?>')" 
                                        class="p-1.5 text-text-muted hover:text-primary rounded-lg hover:bg-surface-container transition-colors" title="Edit Judul Section">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>

                                <?php if (!$isGeneral): ?>
                                    <form action="actions/materi_action.php?action=delete_section" method="post" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus section ini beserta seluruh raises item di dalamnya?')">
                                        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                                        <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
                                        <input type="hidden" name="mapel_id" value="<?= $mapelId ?>">
                                        <button type="submit" class="p-1.5 text-text-muted hover:text-error rounded-lg hover:bg-error-container/30 transition-colors" title="Hapus Section">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Content Accordion Body -->
                        <div class="p-md space-y-md section-body" id="section-body-<?= $sec['id'] ?>">
                            <?php if (empty($items)): ?>
                                <div class="text-center py-md border border-dashed border-outline-variant rounded-lg text-text-muted bg-surface-white">
                                    <p class="font-body-sm text-body-sm mb-2">Belum ada konten pada section ini.</p>
                                    <button type="button" onclick="openModalAddItem(<?= $sec['id'] ?>, '<?= h(addslashes($sec['judul'])) ?>')" 
                                            class="text-primary font-label-sm font-semibold hover:underline flex items-center justify-center gap-1 mx-auto">
                                        <span class="material-symbols-outlined text-[16px]">add</span> Tambah File, Link, Gambar, Video, atau Diskusi
                                    </button>
                                </div>
                            <?php endif; ?>

                            <?php foreach ($items as $item): ?>
                                <div class="p-md border border-outline-variant/80 rounded-xl bg-white hover:border-primary/40 transition-all shadow-2xs" id="item-<?= $item['id'] ?>">
                                    <div class="flex items-start justify-between gap-3">
                                        
                                        <div class="flex items-start gap-3 flex-1 min-w-0">
                                            <!-- Ikon Tipe Konten -->
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5 
                                                <?php
                                                    switch($item['tipe']) {
                                                        case 'file': echo 'bg-blue-100 text-blue-600'; break;
                                                        case 'link': echo 'bg-purple-100 text-purple-600'; break;
                                                        case 'gambar': echo 'bg-emerald-100 text-emerald-600'; break;
                                                        case 'video': echo 'bg-rose-100 text-rose-600'; break;
                                                        case 'diskusi': echo 'bg-teal-100 text-teal-600'; break;
                                                        default: echo 'bg-gray-100 text-gray-600';
                                                    }
                                                ?>">
                                                <span class="material-symbols-outlined text-[24px]">
                                                    <?php
                                                        switch($item['tipe']) {
                                                            case 'file': echo 'description'; break;
                                                            case 'link': echo 'link'; break;
                                                            case 'gambar': echo 'image'; break;
                                                            case 'video': echo 'play_circle'; break;
                                                            case 'diskusi': echo 'forum'; break;
                                                        }
                                                    ?>
                                                </span>
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="uppercase text-[10px] font-bold tracking-wider px-2 py-0.5 rounded-full 
                                                        <?php
                                                            switch($item['tipe']) {
                                                                case 'file': echo 'bg-blue-50 text-blue-700 border border-blue-200'; break;
                                                                case 'link': echo 'bg-purple-50 text-purple-700 border border-purple-200'; break;
                                                                case 'gambar': echo 'bg-emerald-50 text-emerald-700 border border-emerald-200'; break;
                                                                case 'video': echo 'bg-rose-50 text-rose-700 border border-rose-200'; break;
                                                                case 'diskusi': echo 'bg-teal-50 text-teal-700 border border-teal-200'; break;
                                                            }
                                                        ?>">
                                                        <?= strtoupper($item['tipe']) ?>
                                                    </span>
                                                    <span class="text-body-xs text-text-muted">Diunggah oleh <?= h($item['nama_pengunggah']) ?> • <?= date('d M Y, H:i', strtotime($item['created_at'])) ?></span>
                                                </div>

                                                <h4 class="font-title-sm text-title-sm font-semibold text-text-main mb-1">
                                                    <?= h($item['judul']) ?>
                                                </h4>

                                                <?php if (!empty($item['deskripsi'])): ?>
                                                    <p class="font-body-sm text-body-sm text-text-muted mb-2 whitespace-pre-line"><?= h($item['deskripsi']) ?></p>
                                                <?php endif; ?>

                                                <!-- Display Konten Sesuai Tipe -->

                                                <!-- TIPE FILE -->
                                                <?php if ($item['tipe'] === 'file' && !empty($item['nama_file'])): ?>
                                                    <div class="mt-2 flex items-center gap-3 bg-surface-container/60 p-2.5 rounded-lg border border-outline-variant/60">
                                                        <span class="material-symbols-outlined text-primary text-[22px]">download_for_offline</span>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="font-label-md text-label-md font-semibold text-text-main truncate"><?= h($item['nama_file_asli'] ?: $item['nama_file']) ?></p>
                                                            <span class="text-body-xs text-text-muted"><?= formatUkuranFile($item['ukuran_file']) ?></span>
                                                        </div>
                                                        <a href="uploads/materi/<?= h($item['nama_file']) ?>" download class="bg-primary hover:bg-primary-container text-white px-3 py-1.5 rounded-md text-label-sm font-medium transition-colors">
                                                            Unduh File
                                                        </a>
                                                    </div>

                                                <!-- TIPE LINK -->
                                                <?php elseif ($item['tipe'] === 'link' && !empty($item['url_link'])): ?>
                                                    <div class="mt-2">
                                                        <a href="<?= h($item['url_link']) ?>" target="_blank" rel="noopener noreferrer" 
                                                           class="inline-flex items-center gap-1.5 text-primary hover:underline font-label-md text-label-md">
                                                            <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                                                            <?= h($item['url_link']) ?>
                                                        </a>
                                                    </div>

                                                <!-- TIPE GAMBAR -->
                                                <?php elseif ($item['tipe'] === 'gambar'): ?>
                                                    <div class="mt-3">
                                                        <?php if (!empty($item['nama_file'])): ?>
                                                            <img src="uploads/materi/<?= h($item['nama_file']) ?>" alt="<?= h($item['judul']) ?>" class="max-h-72 rounded-lg border border-outline-variant object-cover shadow-sm">
                                                        <?php elseif (!empty($item['url_link'])): ?>
                                                            <img src="<?= h($item['url_link']) ?>" alt="<?= h($item['judul']) ?>" class="max-h-72 rounded-lg border border-outline-variant object-cover shadow-sm">
                                                        <?php endif; ?>
                                                    </div>

                                                <!-- TIPE VIDEO -->
                                                <?php elseif ($item['tipe'] === 'video'): ?>
                                                    <div class="mt-3 max-w-xl">
                                                        <?php 
                                                            $ytEmbed = getYoutubeEmbedUrl($item['url_link']);
                                                        ?>
                                                        <?php if ($ytEmbed): ?>
                                                            <div class="aspect-video w-full rounded-xl overflow-hidden border border-outline-variant shadow-sm">
                                                                <iframe class="w-full h-full" src="<?= $ytEmbed ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                                            </div>
                                                        <?php elseif (!empty($item['nama_file'])): ?>
                                                            <video controls class="w-full rounded-xl border border-outline-variant max-h-80 shadow-sm">
                                                                <source src="uploads/materi/<?= h($item['nama_file']) ?>">
                                                                Browser Anda tidak mendukung pemutar video HTML5.
                                                            </video>
                                                        <?php elseif (!empty($item['url_link'])): ?>
                                                            <a href="<?= h($item['url_link']) ?>" target="_blank" class="inline-flex items-center gap-2 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-2 rounded-lg font-label-md hover:bg-rose-100">
                                                                <span class="material-symbols-outlined">play_circle</span> Tonton Video Pembelajaran
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>

                                                <!-- TIPE DISKUSI -->
                                                <?php elseif ($item['tipe'] === 'diskusi'): ?>
                                                    <?php
                                                        // Ambil balasan diskusi
                                                        $stmtB = $pdo->prepare("SELECT mdb.*, u.nama_lengkap, u.foto, u.role
                                                                               FROM materi_diskusi_balasan mdb
                                                                               JOIN users u ON u.id = mdb.user_id
                                                                               WHERE mdb.item_id = ?
                                                                               ORDER BY mdb.created_at ASC");
                                                        $stmtB->execute([$item['id']]);
                                                        $balasanList = $stmtB->fetchAll();
                                                    ?>
                                                    <div class="mt-3 bg-surface-white rounded-xl p-md border border-outline-variant/60 space-y-md">
                                                        <div class="flex items-center justify-between border-b border-outline-variant/40 pb-2">
                                                            <h5 class="font-label-lg font-bold text-text-main flex items-center gap-1.5">
                                                                <span class="material-symbols-outlined text-teal-600 text-[18px]">forum</span>
                                                                Diskusi &amp; Pertanyaan (<?= count($balasanList) ?> Balasan)
                                                            </h5>
                                                        </div>

                                                        <!-- Daftar Komentar Balasan -->
                                                        <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                                                            <?php if (empty($balasanList)): ?>
                                                                <p class="text-body-xs text-text-muted italic">Belum ada tanggapan. Jadilah yang pertama memberikan pertanyaan/komentar.</p>
                                                            <?php endif; ?>
                                                            <?php foreach ($balasanList as $b): ?>
                                                                <div class="flex items-start gap-2.5 bg-white p-3 rounded-lg border border-outline-variant/40 shadow-2xs">
                                                                    <div class="w-7 h-7 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-[12px] flex-shrink-0">
                                                                        <?= strtoupper(substr($b['nama_lengkap'], 0, 1)) ?>
                                                                    </div>
                                                                    <div class="flex-1 min-w-0">
                                                                        <div class="flex items-center justify-between">
                                                                            <span class="font-label-sm font-semibold text-text-main text-[13px]">
                                                                                <?= h($b['nama_lengkap']) ?>
                                                                                <?php if ($b['role'] === 'guru' || $b['role'] === 'admin'): ?>
                                                                                    <span class="bg-primary/10 text-primary px-1.5 py-0.2 text-[10px] rounded font-bold">Guru/Admin</span>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                            <span class="text-[11px] text-text-muted"><?= date('d/m/Y H:i', strtotime($b['created_at'])) ?></span>
                                                                        </div>
                                                                        <p class="font-body-sm text-body-sm text-text-main mt-0.5 whitespace-pre-line"><?= h($b['pesan']) ?></p>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>

                                                        <!-- Form Tulis Balasan Komentar -->
                                                        <form action="actions/materi_action.php?action=add_reply" method="post" class="mt-3 flex gap-2">
                                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                            <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
                                                            <input type="hidden" name="mapel_id" value="<?= $mapelId ?>">
                                                            <input type="text" name="pesan" placeholder="Tulis tanggapan atau pertanyaan Anda di sini..." required
                                                                   class="flex-1 border border-outline-variant rounded-lg px-3 py-1.5 text-body-sm bg-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                            <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-1.5 rounded-lg font-label-sm font-medium transition-colors flex items-center gap-1">
                                                                <span class="material-symbols-outlined text-[16px]">send</span> Kirim
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>

                                            </div>
                                        </div>

                                        <!-- Hapus Item Konten -->
                                        <form action="actions/materi_action.php?action=delete_item" method="post" onsubmit="return confirm('Hapus item konten ini?')" class="flex-shrink-0">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
                                            <input type="hidden" name="mapel_id" value="<?= $mapelId ?>">
                                            <button type="submit" class="p-1 text-text-muted hover:text-error rounded transition-colors" title="Hapus Item">
                                                <span class="material-symbols-outlined text-[18px]">close</span>
                                            </button>
                                        </form>

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Right Sidebar Column: Course Content & Course Information Widget (Match Image 2) -->
            <div class="space-y-lg">
                
                <!-- Navigasi Widget: Course content & Course information (Persis Tombol Kanan Gambar 2) -->
                <div class="bg-white rounded-xl shadow-sm border border-outline-variant overflow-hidden">
                    <div class="bg-[#0066cc] text-white px-md py-3 font-title-sm font-bold flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">menu_open</span> Course content
                        </span>
                    </div>

                    <div class="p-md space-y-2">
                        <?php foreach ($sections as $s): ?>
                            <a href="#section-<?= $s['id'] ?>" class="flex items-center justify-between p-2 rounded-lg hover:bg-surface-container text-body-sm text-text-main font-medium transition-colors">
                                <span class="truncate flex-1"><?= h($s['judul']) ?></span>
                                <span class="material-symbols-outlined text-[16px] text-text-muted">chevron_right</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Card Info Kelas (Course Information) -->
                <div class="bg-white rounded-xl shadow-sm border border-outline-variant p-md">
                    <h4 class="font-title-sm font-bold text-text-main mb-3 flex items-center gap-2 border-b border-outline-variant/60 pb-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">info</span> Course Information
                    </h4>

                    <dl class="space-y-3 text-body-sm">
                        <div>
                            <dt class="text-text-muted text-body-xs font-medium">Mata Pelajaran &amp; Kode</dt>
                            <dd class="font-semibold text-text-main"><?= h($infoKelas['nama_mapel']) ?> (<?= h($infoKelas['kode_mapel']) ?>)</dd>
                        </div>
                        <div>
                            <dt class="text-text-muted text-body-xs font-medium">Kelas / Tingkat</dt>
                            <dd class="font-semibold text-text-main"><?= h($infoKelas['nama_kelas']) ?> (<?= h($infoKelas['tingkat']) ?> <?= h($infoKelas['program']) ?>)</dd>
                        </div>
                        <div>
                            <dt class="text-text-muted text-body-xs font-medium">Guru Pengampu</dt>
                            <dd class="font-semibold text-text-main"><?= h($infoKelas['nama_guru'] ?: 'Belum ditentukan') ?></dd>
                        </div>
                        <div>
                            <dt class="text-text-muted text-body-xs font-medium">Tahun Ajaran</dt>
                            <dd class="font-semibold text-text-main"><?= h($infoKelas['tahun_ajaran']) ?></dd>
                        </div>
                        <div>
                            <dt class="text-text-muted text-body-xs font-medium">Total Siswa Aktif</dt>
                            <dd class="font-semibold text-primary"><?= $totalSiswa ?> Siswa Terdaftar</dd>
                        </div>
                    </dl>
                </div>

            </div>

        </div>
    </div>
</main>

<!-- ================================================================= -->
<!-- MODAL: Tambah Section Baru -->
<!-- ================================================================= -->
<div id="modal-add-section" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-lg shadow-xl animate-fade-in">
        <div class="flex justify-between items-center mb-md border-b border-outline-variant/60 pb-3">
            <h3 class="font-title-md font-bold text-text-main flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">add_circle</span> Tambah Section Baru
            </h3>
            <button onclick="closeModalAddSection()" class="text-text-muted hover:text-text-main">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="actions/materi_action.php?action=add_section" method="post" class="space-y-md">
            <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
            <input type="hidden" name="mapel_id" value="<?= $mapelId ?>">

            <div>
                <label class="block text-label-md font-label-md text-text-main mb-1">Judul Section / Topik</label>
                <input type="text" name="judul" placeholder="Contoh: Pertemuan 3 - Aljabar Linier" required
                       class="w-full border border-outline-variant rounded-lg px-3 py-2 text-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            </div>

            <div class="flex justify-end gap-2 pt-sm">
                <button type="button" onclick="closeModalAddSection()" class="px-4 py-2 border border-outline-variant rounded-lg text-label-md font-medium text-text-muted hover:bg-surface-container">Batal</button>
                <button type="submit" class="px-5 py-2 bg-primary hover:bg-primary-container text-white rounded-lg text-label-md font-medium">Simpan Section</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================= -->
<!-- MODAL: Edit Judul Section -->
<!-- ================================================================= -->
<div id="modal-edit-section" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-lg shadow-xl animate-fade-in">
        <div class="flex justify-between items-center mb-md border-b border-outline-variant/60 pb-3">
            <h3 class="font-title-md font-bold text-text-main flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">edit</span> Edit Judul Section
            </h3>
            <button onclick="closeModalEditSection()" class="text-text-muted hover:text-text-main">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="actions/materi_action.php?action=edit_section" method="post" class="space-y-md">
            <input type="hidden" name="section_id" id="edit-section-id">
            <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
            <input type="hidden" name="mapel_id" value="<?= $mapelId ?>">

            <div>
                <label class="block text-label-md font-label-md text-text-main mb-1">Judul Section / Topik</label>
                <input type="text" name="judul" id="edit-section-judul" required
                       class="w-full border border-outline-variant rounded-lg px-3 py-2 text-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            </div>

            <div class="flex justify-end gap-2 pt-sm">
                <button type="button" onclick="closeModalEditSection()" class="px-4 py-2 border border-outline-variant rounded-lg text-label-md font-medium text-text-muted hover:bg-surface-container">Batal</button>
                <button type="submit" class="px-5 py-2 bg-primary hover:bg-primary-container text-white rounded-lg text-label-md font-medium">Perbarui Judul</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================= -->
<!-- MODAL: Tambah Item Konten (File, Link, Gambar, Video, Diskusi) -->
<!-- ================================================================= -->
<div id="modal-add-item" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full p-lg shadow-xl animate-fade-in max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-md border-b border-outline-variant/60 pb-3">
            <div>
                <h3 class="font-title-md font-bold text-text-main flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">post_add</span> Tambah Konten Materi
                </h3>
                <p class="text-body-xs text-text-muted" id="add-item-section-name">Section: -</p>
            </div>
            <button onclick="closeModalAddItem()" class="text-text-muted hover:text-text-main">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="actions/materi_action.php?action=add_item" method="post" enctype="multipart/form-data" class="space-y-md">
            <input type="hidden" name="section_id" id="add-item-section-id">
            <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
            <input type="hidden" name="mapel_id" value="<?= $mapelId ?>">

            <!-- Pilih Tipe Konten -->
            <div>
                <label class="block text-label-md font-label-md text-text-main mb-2">Pilih Tipe Konten</label>
                <div class="grid grid-cols-5 gap-2" id="type-selector">
                    <button type="button" onclick="selectItemType('file')" id="type-btn-file" class="type-btn p-2 border-2 border-primary bg-primary/10 text-primary rounded-xl flex flex-col items-center gap-1 text-center font-label-sm">
                        <span class="material-symbols-outlined">description</span> File/PDF
                    </button>
                    <button type="button" onclick="selectItemType('link')" id="type-btn-link" class="type-btn p-2 border border-outline-variant text-text-muted rounded-xl flex flex-col items-center gap-1 text-center font-label-sm hover:bg-surface-container">
                        <span class="material-symbols-outlined">link</span> Link
                    </button>
                    <button type="button" onclick="selectItemType('gambar')" id="type-btn-gambar" class="type-btn p-2 border border-outline-variant text-text-muted rounded-xl flex flex-col items-center gap-1 text-center font-label-sm hover:bg-surface-container">
                        <span class="material-symbols-outlined">image</span> Gambar
                    </button>
                    <button type="button" onclick="selectItemType('video')" id="type-btn-video" class="type-btn p-2 border border-outline-variant text-text-muted rounded-xl flex flex-col items-center gap-1 text-center font-label-sm hover:bg-surface-container">
                        <span class="material-symbols-outlined">play_circle</span> Video
                    </button>
                    <button type="button" onclick="selectItemType('diskusi')" id="type-btn-diskusi" class="type-btn p-2 border border-outline-variant text-text-muted rounded-xl flex flex-col items-center gap-1 text-center font-label-sm hover:bg-surface-container">
                        <span class="material-symbols-outlined">forum</span> Diskusi
                    </button>
                </div>
                <input type="hidden" name="tipe" id="input-item-type" value="file">
            </div>

            <!-- Judul -->
            <div>
                <label class="block text-label-md font-label-md text-text-main mb-1">Judul Konten <span class="text-error">*</span></label>
                <input type="text" name="judul" placeholder="Masukkan judul materi/konten..." required
                       class="w-full border border-outline-variant rounded-lg px-3 py-2 text-body-md focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            </div>

            <!-- Deskripsi -->
            <div>
                <label class="block text-label-md font-label-md text-text-main mb-1">Deskripsi / Catatan (Opsional)</label>
                <textarea name="deskripsi" rows="3" placeholder="Penjelasan singkat mengenai materi ini..."
                          class="w-full border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
            </div>

            <!-- Form Upload File (Aktif jika tipe = file, gambar, video) -->
            <div id="field-file-upload">
                <label class="block text-label-md font-label-md text-text-main mb-1">Upload File (PDF, DOCX, PPTX, MP4, PNG, JPG)</label>
                <input type="file" name="file_upload"
                       class="w-full border border-outline-variant rounded-lg px-3 py-2 text-body-sm bg-white focus:outline-none focus:border-primary">
            </div>

            <!-- Form Link / URL (Aktif jika tipe = link, video, gambar) -->
            <div id="field-url-link" class="hidden">
                <label class="block text-label-md font-label-md text-text-main mb-1">Tautan / URL Link</label>
                <input type="url" name="url_link" placeholder="https://youtube.com/watch?v=... atau https://..."
                       class="w-full border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
            </div>

            <div class="flex justify-end gap-2 pt-sm border-t border-outline-variant/60">
                <button type="button" onclick="closeModalAddItem()" class="px-4 py-2 border border-outline-variant rounded-lg text-label-md font-medium text-text-muted hover:bg-surface-container">Batal</button>
                <button type="submit" class="px-5 py-2 bg-primary hover:bg-primary-container text-white rounded-lg text-label-md font-medium">Tambahkan Konten</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Accordion Toggle Functions
    function toggleSection(secId) {
        const body = document.getElementById('section-body-' + secId);
        const chevron = document.getElementById('chevron-' + secId);
        if (body.classList.contains('hidden')) {
            body.classList.remove('hidden');
            chevron.style.transform = 'rotate(0deg)';
        } else {
            body.classList.add('hidden');
            chevron.style.transform = 'rotate(-90deg)';
        }
    }

    let allCollapsed = false;
    function toggleAllSections() {
        const bodies = document.querySelectorAll('.section-body');
        const chevrons = document.querySelectorAll('.chevron-icon');
        const label = document.getElementById('label-toggle-all');
        
        allCollapsed = !allCollapsed;
        bodies.forEach(b => {
            if (allCollapsed) b.classList.add('hidden');
            else b.classList.remove('hidden');
        });
        chevrons.forEach(c => {
            c.style.transform = allCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
        });
        label.textContent = allCollapsed ? 'Expand all' : 'Collapse all';
    }

    // Modal Add Section
    function openModalAddSection() {
        document.getElementById('modal-add-section').classList.remove('hidden');
    }
    function closeModalAddSection() {
        document.getElementById('modal-add-section').classList.add('hidden');
    }

    // Modal Edit Section
    function openModalEditSection(secId, title) {
        document.getElementById('edit-section-id').value = secId;
        document.getElementById('edit-section-judul').value = title;
        document.getElementById('modal-edit-section').classList.remove('hidden');
    }
    function closeModalEditSection() {
        document.getElementById('modal-edit-section').classList.add('hidden');
    }

    // Modal Add Item
    function openModalAddItem(secId, secTitle) {
        document.getElementById('add-item-section-id').value = secId;
        document.getElementById('add-item-section-name').textContent = 'Section: ' + secTitle;
        document.getElementById('modal-add-item').classList.remove('hidden');
        selectItemType('file');
    }
    function closeModalAddItem() {
        document.getElementById('modal-add-item').classList.add('hidden');
    }

    function selectItemType(type) {
        document.getElementById('input-item-type').value = type;
        
        // Reset classes
        const types = ['file', 'link', 'gambar', 'video', 'diskusi'];
        types.forEach(t => {
            const btn = document.getElementById('type-btn-' + t);
            btn.className = 'type-btn p-2 border border-outline-variant text-text-muted rounded-xl flex flex-col items-center gap-1 text-center font-label-sm hover:bg-surface-container';
        });

        // Active class
        const activeBtn = document.getElementById('type-btn-' + type);
        activeBtn.className = 'type-btn p-2 border-2 border-primary bg-primary/10 text-primary rounded-xl flex flex-col items-center gap-1 text-center font-label-sm';

        // Toggle form fields
        const fieldFile = document.getElementById('field-file-upload');
        const fieldUrl = document.getElementById('field-url-link');

        if (type === 'file') {
            fieldFile.classList.remove('hidden');
            fieldUrl.classList.add('hidden');
        } else if (type === 'link') {
            fieldFile.classList.add('hidden');
            fieldUrl.classList.remove('hidden');
        } else if (type === 'gambar' || type === 'video') {
            fieldFile.classList.remove('hidden');
            fieldUrl.classList.remove('hidden');
        } else if (type === 'diskusi') {
            fieldFile.classList.add('hidden');
            fieldUrl.classList.add('hidden');
        }
    }
</script>

<?php
// Helper untuk mendeteksi Youtube URL
function getYoutubeEmbedUrl($url) {
    if (empty($url)) return false;
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
        return 'https://www.youtube.com/embed/' . $match[1];
    }
    return false;
}
?>
</body>
</html>
