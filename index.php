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
<main class="pt-16 md:ml-[280px] min-h-screen bg-slate-50/40">
    <div class="p-6 md:p-8 max-w-[1440px] mx-auto space-y-8">
        <?php renderFlash(); ?>

        <!-- Shortcut Admin ke halaman Pengumuman -->
        <?php if ($isAdmin): ?>
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50/30 border border-blue-100/50 rounded-2xl px-6 py-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-100/50 flex items-center justify-center flex-shrink-0 border border-blue-200/50">
                        <span class="material-symbols-outlined text-blue-600 text-[26px]">campaign</span>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800 text-base mb-0.5">Kelola Pengumuman Sekolah</p>
                        <p class="text-sm text-slate-500 font-medium">Tulis, edit, dan hapus pengumuman untuk siswa &amp; guru.</p>
                    </div>
                </div>
                <a href="pages/pengumuman.php" class="bg-white hover:bg-slate-50 border border-slate-200 text-blue-600 text-sm font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 transition-all shadow-sm hover:shadow-md flex-shrink-0">
                    Buka Halaman <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Judul Halaman Profil Pengguna -->
        <div class="flex items-end justify-between pb-2">
            <div>
                <p class="text-sm font-semibold text-blue-600 mb-1 tracking-wide uppercase">Dashboard</p>
                <h2 class="text-3xl font-bold text-slate-800 tracking-tight">
                    Selamat datang, <?= h(explode(' ', $user['nama_lengkap'])[0]) ?>!
                </h2>
            </div>
        </div>

        <!-- Layout 2 Kartu Profil -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Kartu Kiri: Foto, Nama, NIP, Guru Mapel -->
            <div class="lg:col-span-4 bg-white/80 backdrop-blur-md rounded-2xl p-8 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-slate-200/60 text-center flex flex-col items-center">
                <div class="relative w-32 h-32 mb-5">
                    <div class="absolute inset-0 bg-gradient-to-tr from-blue-500 to-indigo-500 rounded-full blur-md opacity-40"></div>
                    <div class="relative w-full h-full rounded-full overflow-hidden border-4 border-white shadow-sm bg-slate-100 flex items-center justify-center">
                        <?php if (!empty($user['foto'])): ?>
                            <img src="<?= APP_URL ?>/uploads/avatar/<?= h($user['foto']) ?>" alt="<?= h($user['nama_lengkap']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="material-symbols-outlined text-[64px] text-slate-300">account_circle</span>
                        <?php endif; ?>
                    </div>
                </div>

                <h3 class="text-xl font-bold text-slate-800 mb-6 tracking-tight">
                    <?= h($user['nama_lengkap']) ?>
                </h3>

                <div class="w-full space-y-4 text-left border-t border-slate-100 pt-5">
                    <div>
                        <div class="flex items-center gap-1.5 text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1">
                            <span class="material-symbols-outlined text-[16px]">sell</span> Nama Pengguna
                        </div>
                        <p class="text-slate-800 text-sm font-medium pl-5"><?= h($user['nama_lengkap']) ?></p>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5 text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1">
                            <span class="material-symbols-outlined text-[16px]">badge</span> NIP
                        </div>
                        <p class="text-slate-800 text-sm font-medium pl-5"><?= h($user['nip'] ?: '-') ?></p>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5 text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1">
                            <span class="material-symbols-outlined text-[16px]">menu_book</span> Guru Mapel
                        </div>
                        <p class="text-slate-800 text-sm font-medium pl-5"><?= h($user['mapel_keahlian'] ?: ($mapelDiajar ?: 'Guru SMA Negeri 1 Bumiayu')) ?></p>
                    </div>
                </div>
            </div>

            <!-- Kartu Kanan: Wali Kelas, Status, Agama, Email, No Telp & Tombol Aksi -->
            <div class="lg:col-span-8 bg-white/80 backdrop-blur-md rounded-2xl p-8 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-slate-200/60 flex flex-col justify-between min-h-[400px]">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100">
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1.5">
                            <span class="material-symbols-outlined text-[18px]">sentiment_satisfied</span> Wali Kelas
                        </div>
                        <p class="text-blue-600 text-base font-bold">
                            <?= $isWaliKelas ? h($kelasDiwali[0]) : 'Bukan Wali Kelas' ?>
                        </p>
                    </div>

                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100">
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1.5">
                            <span class="material-symbols-outlined text-[18px]">school</span> Status
                        </div>
                        <p class="text-slate-800 text-base font-semibold">
                            Aktif (Pegawai Portal)
                        </p>
                    </div>

                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100">
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1.5">
                            <span class="material-symbols-outlined text-[18px]">star</span> Agama
                        </div>
                        <p class="text-slate-800 text-base font-semibold">
                            <?= h($user['agama'] ?: 'Islam') ?>
                        </p>
                    </div>

                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100">
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1.5">
                            <span class="material-symbols-outlined text-[18px]">mail</span> Email
                        </div>
                        <p class="text-slate-800 text-base font-semibold truncate" title="<?= h($user['email']) ?>">
                            <?= h($user['email']) ?>
                        </p>
                    </div>

                    <div class="bg-slate-50/50 rounded-xl p-4 border border-slate-100 md:col-span-2">
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1.5">
                            <span class="material-symbols-outlined text-[18px]">call</span> No. Telp / WhatsApp
                        </div>
                        <p class="text-slate-800 text-base font-semibold">
                            <?= h($user['no_telp'] ?: '0812-3456-7890') ?>
                        </p>
                    </div>
                </div>

                <div class="pt-6 mt-6 border-t border-slate-100 flex flex-wrap items-center gap-3">
                    <button type="button" onclick="window.location.reload()" 
                            class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">refresh</span> Reload
                    </button>

                    <a href="pages/materi.php" 
                       class="bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 transition-all shadow-[0_4px_12px_-2px_rgba(16,185,129,0.3)] hover:-translate-y-0.5">
                        <span class="material-symbols-outlined text-[18px]">school</span> Materi Belajar
                    </a>

                    <a href="pages/data_siswa.php" 
                       class="bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 transition-all shadow-[0_4px_12px_-2px_rgba(59,130,246,0.3)] hover:-translate-y-0.5">
                        <span class="material-symbols-outlined text-[18px]">group</span> Data Siswa &amp; Kelas
                    </a>
                </div>
            </div>
        </div>

        <!-- Bagian Jadwal & Pengumuman Sekolah -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pt-2">
            
            <!-- Jadwal Mengajar Hari Ini -->
            <div class="lg:col-span-2 bg-white/80 backdrop-blur-md rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-slate-200/60 overflow-hidden flex flex-col">
                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white/50">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">calendar_month</span> Jadwal Mengajar Hari Ini
                    </h3>
                    <span class="text-sm font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded-full"><?= h($hariIni) ?>, <?= formatTanggalIndo(date('Y-m-d')) ?></span>
                </div>
                <div class="p-6 flex-1 bg-slate-50/30">
                    <?php if (empty($jadwalHariIni)): ?>
                        <div class="text-center py-10 text-slate-400">
                            <span class="material-symbols-outlined text-[48px] mb-3 block text-slate-200">event_available</span>
                            <p class="font-medium text-slate-500">Tidak ada jadwal mengajar hari ini.</p>
                            <p class="text-sm">Selamat beristirahat!</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($jadwalHariIni as $j): ?>
                                <?php
                                    $sudahLewat = $j['jam_selesai'] < $sekarang;
                                    $sedangBerlangsung = $j['jam_mulai'] <= $sekarang && $sekarang <= $j['jam_selesai'];
                                ?>
                                <div class="group flex flex-col md:flex-row md:items-center justify-between p-5 bg-white rounded-xl border border-slate-200 hover:border-blue-300 hover:shadow-md transition-all gap-4">
                                    <div class="flex items-start gap-4">
                                        <div class="w-12 h-12 rounded-lg bg-blue-50 flex flex-col items-center justify-center border border-blue-100 flex-shrink-0">
                                            <span class="text-xs font-bold text-blue-600"><?= substr($j['jam_mulai'], 0, 5) ?></span>
                                            <span class="text-[10px] text-blue-400 font-medium">s/d</span>
                                        </div>
                                        <div>
                                            <h4 class="text-base font-bold text-slate-800 mb-0.5 group-hover:text-blue-700 transition-colors"><?= h($j['nama_mapel']) ?> <span class="text-slate-400 font-normal ml-1">— <?= h($j['nama_kelas']) ?></span></h4>
                                            <p class="text-sm text-slate-500 font-medium flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-[16px] text-slate-400">meeting_room</span> 
                                                <?= $j['keterangan'] ? h($j['keterangan']) . ' • ' : '' ?><?= h($j['ruang'] ?? 'Ruang Kelas') ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if ($sudahLewat): ?>
                                        <span class="text-xs font-bold bg-slate-100 px-4 py-1.5 rounded-full text-slate-500 self-start md:self-center border border-slate-200">Selesai</span>
                                    <?php elseif ($j['jenis'] === 'reguler'): ?>
                                        <a href="pages/materi_detail.php?kelas_id=<?= (int)$j['kelas_id'] ?>&mapel_id=<?= (int)$j['mapel_id'] ?>" class="px-5 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm font-bold rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all text-center shadow-sm hover:shadow self-start md:self-center whitespace-nowrap">Buka Kelas</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pengumuman Sekolah -->
            <div class="bg-white/80 backdrop-blur-md rounded-2xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-slate-200/60 overflow-hidden flex flex-col">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3 bg-white/50">
                    <span class="material-symbols-outlined text-indigo-600">campaign</span>
                    <h3 class="text-lg font-bold text-slate-800">Pengumuman Sekolah</h3>
                </div>
                <div class="p-6 space-y-4 flex-1 bg-slate-50/30">
                    <?php if (empty($pengumuman)): ?>
                        <p class="text-sm text-slate-500 font-medium text-center py-4">Belum ada pengumuman.</p>
                    <?php endif; ?>
                    <?php foreach ($pengumuman as $p): ?>
                        <?php $penting = $p['kategori'] === 'penting'; ?>
                        <div class="bg-white border <?= $penting ? 'border-red-200 shadow-[0_2px_8px_-2px_rgba(239,68,68,0.1)]' : 'border-slate-200 shadow-sm' ?> p-4 rounded-xl relative overflow-hidden group hover:border-indigo-300 transition-colors">
                            <?php if($penting): ?>
                                <div class="absolute top-0 left-0 w-1 h-full bg-red-500"></div>
                            <?php else: ?>
                                <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between items-start mb-2 pl-1">
                                <span class="text-[11px] font-bold tracking-wider uppercase <?= $penting ? 'text-red-600 bg-red-50 px-2 py-0.5 rounded' : 'text-indigo-600' ?>"><?= $penting ? 'PENTING' : 'INFORMASI' ?></span>
                                <span class="text-xs font-medium text-slate-400"><?= h(waktuRelatif($p['created_at'])) ?></span>
                            </div>
                            <h4 class="text-sm font-bold text-slate-800 mb-1.5 pl-1"><?= h($p['judul']) ?></h4>
                            <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed pl-1"><?= strip_tags($p['isi']) ?></p>
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
