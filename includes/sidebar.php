<?php
/**
 * Partial Sidebar Navigasi - SMA N 1 Bumiayu
 * Menampilkan hirarki navigasi sesuai sistem E-Learning & Portal Sekolah.
 *
 * - Sidebar auto-open dropdown parent dari halaman/tab yang sedang aktif
 * - Highlight link aktif (page + ?tab=) dengan background biru
 * - Tombol parent dropdown juga di-highlight subtle (text-blue-600) saat anaknya aktif
 * - Icon rotate-180 otomatis saat dropdown terbuka
 */
$currentPage = $currentPage ?? 'dashboard';
$currentTab  = $_GET['tab']  ?? '';
$currentTipe = $_GET['tipe'] ?? '';
$user = currentUser();

$sidebarLogo = null;
$logos = glob(__DIR__ . '/../assets/img/logo*');
if (!empty($logos)) {
    $sidebarLogo = APP_URL . '/assets/img/' . basename($logos[0]);
}

/* =====================================================================
   PEMETAAN: setiap link di sidebar → ID dropdown parent-nya (null = top-level)
   Dipakai untuk (a) auto-open dropdown, (b) highlight tombol parent.
   ===================================================================== */
$sidebarLinks = [
    // HOME
    ['url' => APP_URL . '/index.php',                                  'page' => 'dashboard', 'tab' => '',        'parent' => null,                 'icon' => 'desktop_windows',  'label' => 'Beranda'],
    ['url' => APP_URL . '/pages/pengaturan.php',                        'page' => 'pengaturan', 'tab' => '',        'parent' => null,                 'icon' => 'person',            'label' => 'Profile'],
    ['url' => APP_URL . '/pages/pengumuman.php',                        'page' => 'pengumuman', 'tab' => '',        'parent' => null,                 'icon' => 'campaign',          'label' => 'Pengumuman'],

    // WALI KELAS dropdown
    ['url' => APP_URL . '/pages/kelas_jadwal.php',                      'page' => 'jadwal',    'tab' => '',        'parent' => 'dropdownWaliKelas',   'icon' => 'calendar_month',    'label' => 'Jadwal Kelas Saya'],
    ['url' => APP_URL . '/pages/absen_qr.php',                          'page' => 'absen_qr',  'tab' => '',        'parent' => 'dropdownWaliKelas',   'icon' => 'qr_code_2',         'label' => 'Absen QR Code'],
    ['url' => APP_URL . '/pages/rekap_presensi.php',                    'page' => 'rekap_presensi', 'tab' => '',   'parent' => 'dropdownWaliKelas',   'icon' => 'fact_check',        'label' => 'Rekap Presensi'],
    ['url' => APP_URL . '/pages/kelas_jadwal.php?tab=jurnal',           'page' => 'jadwal',    'tab' => 'jurnal',  'parent' => 'dropdownWaliKelas',   'icon' => 'auto_stories',      'label' => 'Jurnal Kelas'],
    ['url' => APP_URL . '/pages/data_siswa.php',                        'page' => 'siswa',     'tab' => '',        'parent' => 'dropdownWaliKelas',   'icon' => 'group',             'label' => 'Siswa'],
    ['url' => APP_URL . '/pages/data_siswa.php?tab=struktur',           'page' => 'siswa',     'tab' => 'struktur','parent' => 'dropdownWaliKelas',   'icon' => 'radio_button_unchecked', 'label' => 'Struktur'],
    ['url' => APP_URL . '/pages/data_siswa.php?tab=catatan',            'page' => 'siswa',     'tab' => 'catatan', 'parent' => 'dropdownWaliKelas',   'icon' => 'edit_note',         'label' => 'Catatan'],
    ['url' => APP_URL . '/pages/data_siswa.php?tab=poin',               'page' => 'siswa',     'tab' => 'poin',    'parent' => 'dropdownWaliKelas',   'icon' => 'star',              'label' => 'Poin Kelas'],

    // BELAJAR MENGAJAR dropdown
    ['url' => APP_URL . '/pages/kinerja_harian.php',                    'page' => 'kinerja_harian', 'tab' => '',    'parent' => 'dropdownBelajarMengajar','icon' => 'description',    'label' => 'Laporan Kinerja Harian'],
    ['url' => APP_URL . '/pages/kelas_jadwal.php',                      'page' => 'jadwal',    'tab' => '',        'parent' => 'dropdownBelajarMengajar','icon' => 'calendar_today','label' => 'Jadwal Mengajar'],

    // E-LEARNING dropdown
    ['url' => APP_URL . '/pages/materi.php',                            'page' => 'materi',    'tab' => '',        'parent' => 'dropdownELearning',  'icon' => 'build',              'label' => 'Materi'],
    ['url' => APP_URL . '/pages/tugas_ujian.php',                       'page' => 'tugas',     'tab' => '',        'parent' => 'dropdownELearning',  'icon' => 'assignment',        'label' => 'Tugas'],
    ['url' => APP_URL . '/pages/rekap_nilai.php',                       'page' => 'nilai',     'tab' => '',        'parent' => 'dropdownELearning',  'icon' => 'assignment_turned_in', 'label' => 'Nilai Harian'],
    ['url' => APP_URL . '/pages/data_siswa.php?tab=kehadiran',          'page' => 'siswa',     'tab' => 'kehadiran','parent' => 'dropdownELearning', 'icon' => 'how_to_reg',         'label' => 'Kehadiran Harian'],
    ['url' => APP_URL . '/pages/data_siswa.php?tab=kehadiran_bulanan',  'page' => 'siswa',     'tab' => 'kehadiran_bulanan','parent' => 'dropdownELearning','icon' => 'format_list_bulleted','label' => 'Kehadiran Bulanan'],

    // ULANGAN / UJIAN dropdown
    ['url' => APP_URL . '/pages/export_nilai.php',                      'page' => 'arsip',     'tab' => '',        'parent' => 'dropdownUjian',      'icon' => 'print',             'label' => 'Cetak'],
    ['url' => APP_URL . '/pages/data_siswa.php',                        'page' => 'siswa',     'tab' => '',        'parent' => 'dropdownUjian',      'icon' => 'person_search',     'label' => 'Status Siswa'],
    ['url' => APP_URL . '/pages/tugas_ujian.php',                       'page' => 'tugas',     'tab' => '',        'parent' => 'dropdownUjian',      'icon' => 'article',           'label' => 'Hasil Ujian'],
    ['url' => APP_URL . '/pages/rekap_nilai.php?tab=analisis',          'page' => 'nilai',     'tab' => 'analisis','parent' => 'dropdownUjian',      'icon' => 'analytics',         'label' => 'Analisis Soal'],

    // PENILAIAN › DATA RAPOR dropdown
    ['url' => APP_URL . '/pages/rekap_nilai.php?tab=kkm',               'page' => 'nilai',     'tab' => 'kkm',     'parent' => 'dropdownDataRapor',  'icon' => 'balance',           'label' => 'KKM dan Bobot'],
    ['url' => APP_URL . '/pages/rekap_nilai.php?tab=indikator',         'page' => 'nilai',     'tab' => 'indikator','parent' => 'dropdownDataRapor', 'icon' => 'menu_book',         'label' => 'Indikator Nilai'],
    ['url' => APP_URL . '/pages/rekap_nilai.php',                       'page' => 'nilai',     'tab' => '',        'parent' => 'dropdownDataRapor',  'icon' => 'group_add',         'label' => 'Input Nilai'],

    // PENILAIAN › INPUT WALI KELAS dropdown
    ['url' => APP_URL . '/pages/rekap_nilai.php?tab=spiritual',         'page' => 'nilai',     'tab' => 'spiritual','parent' => 'dropdownInputWaliKelas','icon' => 'menu_book',    'label' => 'Sikap Spiritual'],
    ['url' => APP_URL . '/pages/rekap_nilai.php?tab=sosial',            'page' => 'nilai',     'tab' => 'sosial',  'parent' => 'dropdownInputWaliKelas','icon' => 'menu_book',      'label' => 'Sikap Sosial'],
    ['url' => APP_URL . '/pages/rekap_nilai.php?tab=prestasi',         'page' => 'nilai',     'tab' => 'prestasi','parent' => 'dropdownInputWaliKelas','icon' => 'groups',       'label' => 'Prestasi'],
    ['url' => APP_URL . '/pages/data_siswa.php?tab=kenaikan',           'page' => 'siswa',     'tab' => 'kenaikan','parent' => 'dropdownInputWaliKelas','icon' => 'groups',       'label' => 'Kenaikan'],

    // CETAK (top-level)
    ['url' => APP_URL . '/pages/export_nilai.php?tipe=pts',             'page' => 'arsip',     'tab' => '', 'tipe' => 'pts',    'parent' => null,                 'icon' => 'menu_book',         'label' => 'Rapor PTS'],
    ['url' => APP_URL . '/pages/export_nilai.php?tipe=pas',             'page' => 'arsip',     'tab' => '', 'tipe' => 'pas',    'parent' => null,                 'icon' => 'menu_book',         'label' => 'Rapor Akhir'],
    ['url' => APP_URL . '/pages/export_nilai.php?tipe=ledger',          'page' => 'arsip',     'tab' => '', 'tipe' => 'ledger', 'parent' => null,                 'icon' => 'groups',            'label' => 'Ledger'],
    ['url' => APP_URL . '/pages/export_nilai.php?tipe=dkn',             'page' => 'arsip',     'tab' => '', 'tipe' => 'dkn',    'parent' => null,                 'icon' => 'groups',            'label' => 'DKN'],

    // ARSIP (top-level)
    ['url' => APP_URL . '/pages/export_nilai.php?tab=arsip',            'page' => 'arsip',     'tab' => 'arsip',   'parent' => null,                 'icon' => 'account_balance',   'label' => 'Arsip Rapor'],
];

/* Helper: apakah link L aktif (page sama, tab cocok, tipe cocok)?
   Catatan: hanya SATU link dengan (page,tab,tipe) yang boleh aktif per halaman.
   Link parent NULL (top-level) di-prefer-kan jika URL adalah tujuan generik. */
$isActive = function(array $L) use ($currentPage, $currentTab, $currentTipe) {
    if ($L['page'] !== $currentPage) return false;
    /* Link dengan tab spesifik di URL → cocokkan dengan tab */
    if (!empty($L['tab'])) {
        if ($L['tab'] !== $currentTab) return false;
        if (!empty($L['tipe']) && $L['tipe'] !== $currentTipe) return false;
        return true;
    }
    /* Link tanpa tab spesifik (default page) → hanya cocok jika URL TIDAK membawa tab/tipe lain */
    if ($currentTab !== '') return false;
    if (!empty($L['tipe'])) return $L['tipe'] === $currentTipe;
    return $currentTipe === '';
};

/* Kumpulan id dropdown yang harus auto-terbuka (berdasarkan halaman+tab aktif) */
$openDropdowns = [];
foreach ($sidebarLinks as $L) {
    if ($isActive($L) && !empty($L['parent'])) {
        $openDropdowns[$L['parent']] = true;
    }
}
/* Jika link top-level aktif, JANGAN auto-open dropdown lain */
$topLevelActive = false;
foreach ($sidebarLinks as $L) {
    if ($isActive($L) && empty($L['parent'])) {
        $topLevelActive = true;
    }
}

/* Helper kecil untuk grouping links per parent */
$groupedLinks = [];
foreach ($sidebarLinks as $L) {
    $key = $L['parent'] ?? '__top__';
    $groupedLinks[$key][] = $L;
}
?>
<!-- Overlay untuk tampilan mobile (klik di luar sidebar untuk menutup) -->
<div id="sidebar-overlay" class="hidden fixed inset-0 bg-black/40 z-40 md:hidden" onclick="toggleSidebar()"></div>

<aside id="sidebar" class="fixed left-0 top-0 h-screen w-[280px] bg-white border-r border-gray-200/80 flex flex-col z-50 -translate-x-full md:translate-x-0 transition-transform duration-200 shadow-sm text-gray-800">

    <!-- Header Logo & Nama Sekolah -->
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-white">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 flex-shrink-0 flex items-center justify-center">
                <?php if ($sidebarLogo): ?>
                    <img src="<?= $sidebarLogo ?>" alt="Logo Sekolah" class="w-full h-full object-contain">
                <?php else: ?>
                    <span class="material-symbols-outlined text-emerald-600 text-[26px]">school</span>
                <?php endif; ?>
            </div>
            <h1 class="font-bold text-gray-800 text-[14px] tracking-tight uppercase">SMA N 1 BUMIAYU</h1>
        </div>
        <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-800 p-1">
            <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
    </div>

    <!-- Header Profil Pengguna -->
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/40 flex items-center gap-3">
        <div class="w-11 h-11 rounded-full overflow-hidden border-2 border-gray-200 flex-shrink-0 bg-gray-200 flex items-center justify-center shadow-2xs">
            <?php if (!empty($user['foto'])): ?>
                <img src="<?= APP_URL ?>/uploads/avatar/<?= h($user['foto']) ?>" alt="Foto profil" class="w-full h-full object-cover">
            <?php else: ?>
                <span class="material-symbols-outlined text-gray-400 text-[32px]">account_circle</span>
            <?php endif; ?>
        </div>
        <div class="min-w-0">
            <p class="font-bold text-gray-800 text-sm truncate uppercase tracking-tight"><?= h($user['nama_lengkap'] ?? 'User') ?></p>
            <p class="text-xs text-gray-500 truncate font-medium"><?= h($user['nip'] ?: 'NIP. -') ?></p>
        </div>
    </div>

    <!-- Daftar Navigasi Utama -->
    <nav class="flex-1 overflow-y-auto p-3.5 space-y-4 text-sm font-medium">

        <!-- KELOMPOK: HOME -->
        <div class="space-y-1">
            <div class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">HOME</div>

            <?php foreach (($groupedLinks['__top__'] ?? []) as $L):
                /* Hanya tampilkan top-level HOME di sini */
                if (!in_array($L['label'], ['Beranda', 'Profile', 'Pengumuman'], true)) continue;
                $active = $isActive($L) && !$topLevelActive ? false : $isActive($L);
                $active = $isActive($L);
            ?>
                <a href="<?= $L['url'] ?>"
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg transition-all <?= $active ? 'bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-bold shadow-md' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                    <span class="material-symbols-outlined text-[20px]"><?= $L['icon'] ?></span>
                    <span><?= $L['label'] ?></span>
                </a>
            <?php endforeach; ?>

            <?php
            $dropdownDefs = [
                'dropdownWaliKelas'      => ['Wali Kelas',        'pie_chart'],
                'dropdownBelajarMengajar'=> ['Belajar Mengajar',  'domain'],
                'dropdownELearning'      => ['E-Learning',        'computer'],
                'dropdownUjian'          => ['Ulangan / Ujian',   'school'],
            ];
            foreach ($dropdownDefs as $ddId => [$ddLabel, $ddIcon]):
                $links = $groupedLinks[$ddId] ?? [];
                if (empty($links)) continue;
                $isOpen = !empty($openDropdowns[$ddId]);
                /* Parent aktif? → ada anak yg aktif */
                $parentActive = $isOpen;
            ?>
                <div class="pt-1">
                    <button type="button" onclick="toggleDropdown('<?= $ddId ?>')"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg transition-all
                                   <?= $parentActive ? 'bg-blue-50 text-blue-700 font-bold border border-blue-100' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 font-semibold shadow-sm' ?>">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]"><?= $ddIcon ?></span>
                            <span><?= $ddLabel ?></span>
                        </div>
                        <span id="icon-<?= $ddId ?>" class="material-symbols-outlined text-[20px] transition-transform duration-200 <?= $isOpen ? 'rotate-180' : '' ?>">keyboard_arrow_down</span>
                    </button>
                    <div id="<?= $ddId ?>" class="<?= $isOpen ? '' : 'hidden' ?> pl-4 pt-1 space-y-0.5">
                        <?php foreach ($links as $L):
                            $active = $isActive($L);
                            $cls = $active
                                ? 'text-blue-700 bg-blue-50 font-bold translate-x-1'
                                : 'text-slate-500 hover:text-blue-600 hover:translate-x-1';
                        ?>
                            <a href="<?= $L['url'] ?>"
                               class="flex items-center gap-3 px-3 py-2 <?= $cls ?> rounded-md transition-all text-[13px] font-medium">
                                <span class="material-symbols-outlined text-[18px]"><?= $L['icon'] ?></span> <?= $L['label'] ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- KELOMPOK: PENILAIAN -->
        <div class="space-y-1 pt-2">
            <div class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">PENILAIAN</div>

            <?php
            $dropdownDefs2 = [
                'dropdownDataRapor'      => ['Data Rapor',        'pie_chart'],
                'dropdownInputWaliKelas' => ['Input Wali Kelas',  'pie_chart'],
            ];
            foreach ($dropdownDefs2 as $ddId => [$ddLabel, $ddIcon]):
                $links = $groupedLinks[$ddId] ?? [];
                if (empty($links)) continue;
                $isOpen = !empty($openDropdowns[$ddId]);
                $parentActive = $isOpen;
            ?>
                <div>
                    <button type="button" onclick="toggleDropdown('<?= $ddId ?>')"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg transition-all
                                   <?= $parentActive ? 'bg-blue-50 text-blue-700 font-bold border border-blue-100' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 font-semibold shadow-sm' ?>">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[20px]"><?= $ddIcon ?></span>
                            <span><?= $ddLabel ?></span>
                        </div>
                        <span id="icon-<?= $ddId ?>" class="material-symbols-outlined text-[20px] transition-transform duration-200 <?= $isOpen ? 'rotate-180' : '' ?>">keyboard_arrow_down</span>
                    </button>
                    <div id="<?= $ddId ?>" class="<?= $isOpen ? '' : 'hidden' ?> pl-4 pt-1 space-y-0.5">
                        <?php foreach ($links as $L):
                            $active = $isActive($L);
                            $cls = $active
                                ? 'text-blue-700 bg-blue-50 font-bold translate-x-1'
                                : 'text-slate-500 hover:text-blue-600 hover:translate-x-1';
                        ?>
                            <a href="<?= $L['url'] ?>"
                               class="flex items-center gap-3 px-3 py-2 <?= $cls ?> rounded-md transition-all text-[13px] font-medium">
                                <span class="material-symbols-outlined text-[18px]"><?= $L['icon'] ?></span> <?= $L['label'] ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- KELOMPOK: CETAK -->
        <div class="space-y-1 pt-2">
            <div class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">CETAK</div>
            <?php
            $cetakLabels = ['Rapor PTS', 'Rapor Akhir', 'Ledger', 'DKN'];
            foreach (($groupedLinks['__top__'] ?? []) as $L):
                if (!in_array($L['label'], $cetakLabels, true)) continue;
                $active = $isActive($L);
                $cls = $active
                    ? 'text-blue-700 bg-blue-50 font-bold'
                    : 'text-slate-600 hover:text-blue-600 hover:bg-slate-50';
            ?>
                <a href="<?= $L['url'] ?>"
                   class="flex items-center gap-3 px-3.5 py-2 <?= $cls ?> rounded-lg transition-all text-sm font-medium">
                    <span class="material-symbols-outlined text-[20px]"><?= $L['icon'] ?></span>
                    <span><?= $L['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- KELOMPOK: ARSIP -->
        <div class="space-y-1 pt-2 pb-6">
            <div class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">ARSIP</div>
            <?php
            foreach (($groupedLinks['__top__'] ?? []) as $L):
                if ($L['label'] !== 'Arsip Rapor') continue;
                $active = $isActive($L);
                $cls = $active
                    ? 'text-blue-700 bg-blue-50 font-bold'
                    : 'text-slate-600 hover:text-blue-600 hover:bg-slate-50';
            ?>
                <a href="<?= $L['url'] ?>"
                   class="flex items-center gap-3 px-3.5 py-2 <?= $cls ?> rounded-lg transition-all text-sm font-medium">
                    <span class="material-symbols-outlined text-[20px]"><?= $L['icon'] ?></span>
                    <span><?= $L['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

    </nav>
</aside>

<script>
    /**
     * Buka/tutup dropdown sidebar.
     * - Auto-mutate tombol parent (warna biru muda + border) saat terbuka
     * - Auto-rotate icon keyboard_arrow_down
     * - Hanya 1 dropdown yang bisa auto-highlight parent (sesuai PHP pre-compute),
     *   tapi user boleh buka dropdown lain secara manual tanpa override.
     */
    function toggleDropdown(id) {
        const el = document.getElementById(id);
        const icon = document.getElementById('icon-' + id);
        if (!el) return;
        const willOpen = el.classList.contains('hidden');

        el.classList.toggle('hidden');
        if (icon) icon.classList.toggle('rotate-180', willOpen);

        // Toggle visual style tombol parent (highlight aktif/non-aktif)
        const btn = icon ? icon.closest('button') : null;
        if (btn) {
            if (willOpen) {
                btn.classList.add('bg-blue-50', 'text-blue-700', 'border', 'border-blue-100');
                btn.classList.remove('bg-slate-50', 'hover:bg-slate-100', 'shadow-sm');
            } else {
                btn.classList.remove('bg-blue-50', 'text-blue-700', 'border', 'border-blue-100');
                btn.classList.add('bg-slate-50', 'hover:bg-slate-100', 'shadow-sm');
            }
        }
    }
</script>