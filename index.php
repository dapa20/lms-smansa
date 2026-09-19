<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
$user        = currentUser();
$isAdmin     = isAdmin();
$guruId      = (int)$user['id'];

// ---------------------------------------------------------------------
// INFO WALI KELAS & MAPEL DIAJAR
// ---------------------------------------------------------------------
$stmtWali = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE wali_kelas_id = ? ORDER BY nama_kelas");
$stmtWali->execute([$guruId]);
$kelasDiwali = $stmtWali->fetchAll(PDO::FETCH_COLUMN);
$isWaliKelas = !empty($kelasDiwali);

$stmtMapel = $pdo->prepare("SELECT DISTINCT mp.nama_mapel FROM jadwal_mengajar j JOIN mata_pelajaran mp ON mp.id = j.mapel_id WHERE j.guru_id = ?");
$stmtMapel->execute([$guruId]);
$mapelDiajarArr = $stmtMapel->fetchAll(PDO::FETCH_COLUMN);
$mapelDiajar = !empty($mapelDiajarArr) ? 'Guru ' . implode(', ', $mapelDiajarArr) : null;

// ---------------------------------------------------------------------
// KARTU STATISTIK & JADWAL HARI INI
// ---------------------------------------------------------------------
if ($isAdmin) {
    $totalSiswa   = (int)$pdo->query("SELECT COUNT(*) c FROM siswa WHERE status='aktif'")->fetch()['c'];
    $kelasAktif   = (int)$pdo->query("SELECT COUNT(*) c FROM kelas")->fetch()['c'];
} else {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) c FROM siswa s
                            JOIN jadwal_mengajar j ON j.kelas_id = s.kelas_id
                            WHERE j.guru_id = ? AND s.status = 'aktif'");
    $stmt->execute([$guruId]);
    $totalSiswa = (int)$stmt->fetch()['c'];

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT kelas_id) c FROM jadwal_mengajar WHERE guru_id = ? AND jenis = 'reguler'");
    $stmt->execute([$guruId]);
    $kelasAktif = (int)$stmt->fetch()['c'];
}

$hariIni = namaHariIndo((int)date('N'));
$sqlJadwal = "SELECT j.*, m.nama_mapel, k.nama_kelas
              FROM jadwal_mengajar j
              JOIN mata_pelajaran m ON m.id = j.mapel_id
              JOIN kelas k ON k.id = j.kelas_id
              WHERE j.hari = ? AND j.jenis IN ('reguler','rapat')" . ($isAdmin ? '' : ' AND j.guru_id = ?') . "
              ORDER BY j.jam_mulai ASC";
$stmt = $pdo->prepare($sqlJadwal);
$stmt->execute($isAdmin ? [$hariIni] : [$hariIni, $guruId]);
$jadwalHariIni = $stmt->fetchAll();
$sekarang = date('H:i:s');

// Pengumuman (widget read-only di dashboard)
$pengumuman = $pdo->query("SELECT p.*, u.nama_lengkap FROM pengumuman p
                            JOIN users u ON u.id = p.dibuat_oleh
                            ORDER BY p.created_at DESC LIMIT 5")->fetchAll();

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-[#f3f4f6]">
    <div class="p-4 md:p-6 max-w-[1440px] mx-auto space-y-6">
        <?php renderFlash(); ?>

        <!-- Shortcut Admin ke halaman Pengumuman -->
        <?php if ($isAdmin): ?>
            <div class="bg-blue-50 border border-blue-200/80 rounded-xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-blue-600 text-[24px]">campaign</span>
                    <div>
                        <p class="font-bold text-blue-800 text-sm">Kelola Pengumuman Sekolah</p>
                        <p class="text-xs text-blue-600/80">Tulis, edit, dan hapus pengumuman untuk siswa &amp; guru.</p>
                    </div>
                </div>
                <a href="pages/pengumuman.php" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2 transition-colors flex-shrink-0">
                    <span class="material-symbols-outlined text-[17px]">open_in_new</span> Buka Halaman Pengumuman
                </a>
            </div>
        <?php endif; ?>
        <!-- Judul Halaman Profil Pengguna -->
        <div class="border-b border-outline-variant/60 pb-3">
            <h2 class="text-headline-md font-headline-md font-bold text-text-main">
                Profil Pengguna - <?= strtoupper(h($user['nama_lengkap'])) ?>
            </h2>
        </div>

        <!-- Layout 2 Kartu Profil -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-lg items-start">
            
            <!-- Kartu Kiri: Foto, Nama, NIP, Guru Mapel -->
            <div class="lg:col-span-5 bg-white rounded-2xl p-xl shadow-sm border border-outline-variant/70 text-center flex flex-col items-center">
                <div class="w-36 h-36 rounded-full overflow-hidden border-4 border-red-500/80 shadow-md mb-4 flex-shrink-0 bg-surface-container flex items-center justify-center">
                    <?php if (!empty($user['foto'])): ?>
                        <img src="<?= APP_URL ?>/uploads/avatar/<?= h($user['foto']) ?>" alt="<?= h($user['nama_lengkap']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="material-symbols-outlined text-[80px] text-text-muted">account_circle</span>
                    <?php endif; ?>
                </div>

                <h3 class="font-title-lg text-title-lg font-bold text-text-main mb-6 uppercase tracking-wide">
                    <?= h($user['nama_lengkap']) ?>
                </h3>

                <div class="w-full space-y-4 text-left border-t border-outline-variant/40 pt-4">
                    <div>
                        <div class="flex items-center gap-1.5 text-amber-600 font-label-md font-bold mb-0.5">
                            <span class="material-symbols-outlined text-[18px]">sell</span> Nama Pengguna
                        </div>
                        <p class="font-body-md text-body-md text-text-main pl-6 font-medium"><?= h($user['nama_lengkap']) ?></p>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5 text-amber-600 font-label-md font-bold mb-0.5">
                            <span class="material-symbols-outlined text-[18px]">badge</span> NIP
                        </div>
                        <p class="font-body-md text-body-md text-text-main pl-6 font-semibold"><?= h($user['nip'] ?: '-') ?></p>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5 text-rose-600 font-label-md font-bold mb-0.5">
                            <span class="material-symbols-outlined text-[18px]">menu_book</span> Guru Mapel
                        </div>
                        <p class="font-body-md text-body-md text-text-main pl-6 font-medium"><?= h($user['mapel_keahlian'] ?: ($mapelDiajar ?: 'Guru SMA Negeri 1 Bumiayu')) ?></p>
                    </div>
                </div>
            </div>

            <!-- Kartu Kanan: Wali Kelas, Status, Agama, Email, No Telp & Tombol Aksi -->
            <div class="lg:col-span-7 bg-white rounded-2xl p-xl shadow-sm border border-outline-variant/70 flex flex-col justify-between min-h-[420px]">
                
                <div class="space-y-5">
                    <div>
                        <div class="flex items-center gap-2 text-sky-600 font-label-lg font-bold mb-0.5">
                            <span class="material-symbols-outlined text-[20px]">sentiment_satisfied</span> Wali Kelas
                        </div>
                        <p class="font-body-md text-body-md text-text-main pl-7 font-bold text-primary">
                            <?= $isWaliKelas ? h($kelasDiwali[0]) : 'Bukan Wali Kelas' ?>
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center gap-2 text-emerald-600 font-label-lg font-bold mb-0.5">
                            <span class="material-symbols-outlined text-[20px]">school</span> Status
                        </div>
                        <p class="font-body-md text-body-md text-text-main pl-7 font-medium">
                            Aktif (Pegawai Portal)
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center gap-2 text-sky-600 font-label-lg font-bold mb-0.5">
                            <span class="material-symbols-outlined text-[20px]">star</span> Agama
                        </div>
                        <p class="font-body-md text-body-md text-text-main pl-7 font-medium">
                            <?= h($user['agama'] ?: 'Islam') ?>
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center gap-2 text-rose-600 font-label-lg font-bold mb-0.5">
                            <span class="material-symbols-outlined text-[20px]">mail</span> Email
                        </div>
                        <p class="font-body-md text-body-md text-text-main pl-7 font-medium">
                            <?= h($user['email']) ?>
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center gap-2 text-indigo-600 font-label-lg font-bold mb-0.5">
                            <span class="material-symbols-outlined text-[20px]">call</span> No. Telp / WhatsApp
                        </div>
                        <p class="font-body-md text-body-md text-text-main pl-7 font-medium">
                            <?= h($user['no_telp'] ?: '0812-3456-7890') ?>
                        </p>
                    </div>
                </div>

                <div class="pt-6 mt-6 border-t border-outline-variant/40 flex flex-wrap items-center gap-3">
                    <button type="button" onclick="window.location.reload()" 
                            class="bg-amber-500 hover:bg-amber-600 text-white font-label-md text-label-md font-bold px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">refresh</span> Reload Tampilan
                    </button>

                    <a href="pages/materi.php" 
                       class="bg-emerald-600 hover:bg-emerald-700 text-white font-label-md text-label-md font-bold px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">school</span> Materi Pembelajaran
                    </a>

                    <a href="pages/data_siswa.php" 
                       class="bg-pink-600 hover:bg-pink-700 text-white font-label-md text-label-md font-bold px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">group</span> Data Siswa &amp; Kelas
                    </a>
                </div>

            </div>
        </div>

        <!-- Bagian Jadwal & Pengumuman Sekolah -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg pt-4">
            
            <!-- Jadwal Mengajar Hari Ini -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-outline-variant/70 overflow-hidden">
                <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center bg-white">
                    <h3 class="text-title-lg font-bold text-text-main flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">calendar_month</span> Jadwal Mengajar Hari Ini
                    </h3>
                    <span class="text-label-md font-label-md text-primary font-bold"><?= h($hariIni) ?>, <?= formatTanggalIndo(date('Y-m-d')) ?></span>
                </div>
                <div class="p-lg">
                    <?php if (empty($jadwalHariIni)): ?>
                        <div class="text-center py-lg text-text-muted">
                            <span class="material-symbols-outlined text-[40px] mb-2 block text-outline">event_available</span>
                            Tidak ada jadwal mengajar hari ini. Selamat beristirahat!
                        </div>
                    <?php else: ?>
                        <div class="space-y-md">
                            <?php foreach ($jadwalHariIni as $j): ?>
                                <?php
                                    $sudahLewat = $j['jam_selesai'] < $sekarang;
                                    $sedangBerlangsung = $j['jam_mulai'] <= $sekarang && $sekarang <= $j['jam_selesai'];
                                ?>
                                <div class="flex flex-col md:flex-row md:items-center justify-between p-md bg-surface-container/50 rounded-xl border border-outline-variant/60 hover:border-primary/30 transition-all gap-2">
                                    <div>
                                        <p class="text-label-md font-label-md text-primary font-bold"><?= substr($j['jam_mulai'], 0, 5) ?> - <?= substr($j['jam_selesai'], 0, 5) ?></p>
                                        <h4 class="text-body-lg font-bold text-text-main"><?= h($j['nama_mapel']) ?> - <?= h($j['nama_kelas']) ?></h4>
                                        <p class="text-body-sm text-text-muted">
                                            <?= $j['keterangan'] ? h($j['keterangan']) . ' • ' : '' ?><?= h($j['ruang'] ?? '-') ?>
                                        </p>
                                    </div>
                                    <?php if ($sudahLewat): ?>
                                        <span class="text-label-sm font-label-sm bg-surface-container px-3 py-1 rounded-full text-text-muted self-start md:self-auto">Selesai</span>
                                    <?php elseif ($j['jenis'] === 'reguler'): ?>
                                        <a href="pages/materi_detail.php?kelas_id=<?= (int)$j['kelas_id'] ?>&mapel_id=<?= (int)$j['mapel_id'] ?>" class="px-4 py-2 bg-primary text-white text-label-md font-label-md rounded-lg hover:bg-primary-container transition-colors text-center shadow-2xs">Buka Kelas</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pengumuman Sekolah -->
            <div class="bg-white rounded-xl shadow-sm border border-outline-variant/70 overflow-hidden flex flex-col">
                <div class="px-lg py-md border-b border-outline-variant flex items-center gap-sm bg-white">
                    <span class="material-symbols-outlined text-primary">campaign</span>
                    <h3 class="text-title-lg font-bold text-text-main">Pengumuman Sekolah</h3>
                </div>
                <div class="p-lg space-y-md flex-1">
                    <?php if (empty($pengumuman)): ?>
                        <p class="text-body-sm text-text-muted">Belum ada pengumuman.</p>
                    <?php endif; ?>
                    <?php foreach ($pengumuman as $p): ?>
                        <?php $penting = $p['kategori'] === 'penting'; ?>
                        <div class="bg-surface-container/30 border-l-4 <?= $penting ? 'border-primary' : 'border-secondary' ?> p-md rounded-r-xl">
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-label-sm font-bold <?= $penting ? 'text-primary' : 'text-secondary' ?>"><?= $penting ? 'PENTING' : 'INFORMASI' ?></span>
                                <span class="text-body-xs text-text-muted"><?= h(waktuRelatif($p['created_at'])) ?></span>
                            </div>
                            <h4 class="text-body-md font-bold text-text-main mb-1"><?= h($p['judul']) ?></h4>
                            <p class="text-body-xs text-text-muted line-clamp-2"><?= strip_tags($p['isi']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </div>
</main>

<script>
    function formatDoc(cmd, value = null) {
        document.execCommand(cmd, false, value);
        document.getElementById('editor').focus();
    }

    let alignIndex = 0;
    const aligns = ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'];
    function toggleAlign() {
        alignIndex = (alignIndex + 1) % aligns.length;
        formatDoc(aligns[alignIndex]);
    }

    function insertTable() {
        const rows = prompt("Jumlah Baris:", "2");
        const cols = prompt("Jumlah Kolom:", "2");
        if (rows && cols) {
            let html = '<table class="w-full border-collapse border border-gray-300 my-2"><tbody>';
            for (let i = 0; i < parseInt(rows); i++) {
                html += '<tr>';
                for (let j = 0; j < parseInt(cols); j++) {
                    html += '<td class="border border-gray-300 p-2">Teks</td>';
                }
                html += '</tr>';
            }
            html += '</tbody></table>';
            formatDoc('insertHTML', html);
        }
    }

    function insertLink() {
        const url = prompt("Masukkan URL Tautan:", "https://");
        if (url) formatDoc('createLink', url);
    }

    function insertImage() {
        const url = prompt("Masukkan URL Gambar:", "https://");
        if (url) formatDoc('insertImage', url);
    }

    function insertVideo() {
        const url = prompt("Masukkan URL Embed Video (Youtube):", "");
        if (url) {
            let embedUrl = url;
            if (url.includes('watch?v=')) {
                embedUrl = url.replace('watch?v=', 'embed/');
            }
            const iframe = `<iframe width="100%" height="315" src="${embedUrl}" frameborder="0" allowfullscreen class="my-2 rounded-lg"></iframe>`;
            formatDoc('insertHTML', iframe);
        }
    }

    function toggleFullscreen() {
        const editorBox = document.getElementById('editor').closest('.border');
        if (editorBox) {
            editorBox.classList.toggle('fixed');
            editorBox.classList.toggle('inset-4');
            editorBox.classList.toggle('z-50');
            editorBox.classList.toggle('bg-white');
        }
    }

    let isCode = false;
    function toggleCodeView() {
        const editor = document.getElementById('editor');
        if (!isCode) {
            editor.innerText = editor.innerHTML;
            isCode = true;
        } else {
            editor.innerHTML = editor.innerText;
            isCode = false;
        }
    }

    function showHelp() {
        alert("Gunakan toolbar di atas untuk memformat teks pengumuman. Anda dapat menebalkan teks, membuat daftar, menyisipkan gambar, tabel, dan tautan.");
    }

    document.getElementById('formPengumuman')?.addEventListener('submit', function(e) {
        const editor = document.getElementById('editor');
        const hiddenIsi = document.getElementById('hiddenIsi');
        if (isCode) {
            hiddenIsi.value = editor.innerText;
        } else {
            hiddenIsi.value = editor.innerHTML.trim();
        }
    });

    const editorEl = document.getElementById('editor');
    if (editorEl) {
        editorEl.addEventListener('focus', function() {
            if (this.innerText.trim() === '') {
                this.dataset.placeholder = '';
            }
        });
    }
</script>

<style>
    #editor[contenteditable=true]:empty:before {
        content: attr(data-placeholder);
        color: #9ca3af;
        pointer-events: none;
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
