<?php
/**
 * Partial Sidebar Navigasi.
 * Wajib set $currentPage sebelum me-require file ini, salah satu dari:
 * 'dashboard' | 'materi' | 'tugas' | 'nilai' | 'siswa' | 'jadwal' | 'pengaturan'
 */
$currentPage = $currentPage ?? '';
$user = currentUser();

// Gunakan APP_URL (absolute) agar link benar dari konteks root maupun pages/
$menuItems = [
    ['key' => 'dashboard',  'href' => APP_URL . '/index.php',              'icon' => 'dashboard',      'label' => 'Dashboard'],
    ['key' => 'materi',     'href' => APP_URL . '/pages/materi.php',       'icon' => 'book',           'label' => 'Materi Pembelajaran'],
    ['key' => 'tugas',      'href' => APP_URL . '/pages/tugas_ujian.php',  'icon' => 'event_note',     'label' => 'Tugas & Ujian'],
    ['key' => 'nilai',      'href' => APP_URL . '/pages/rekap_nilai.php',  'icon' => 'analytics',      'label' => 'Rekap Nilai'],
    ['key' => 'siswa',      'href' => APP_URL . '/pages/data_siswa.php',   'icon' => 'group',          'label' => 'Data Siswa'],
    ['key' => 'jadwal',     'href' => APP_URL . '/pages/kelas_jadwal.php', 'icon' => 'calendar_today', 'label' => 'Kelas & Jadwal'],
];
?>
<!-- Overlay untuk tampilan mobile (klik di luar sidebar untuk menutup) -->
<div id="sidebar-overlay" class="hidden fixed inset-0 bg-black/40 z-40 md:hidden" onclick="toggleSidebar()"></div>

<aside id="sidebar" class="fixed left-0 top-0 h-screen w-[280px] bg-sidebar-bg flex flex-col py-lg z-50 -translate-x-full md:translate-x-0 transition-transform duration-200">
    <div class="px-lg mb-xl flex items-center justify-between">
        <div class="flex items-center gap-md">
            <div class="w-11 h-11 bg-white rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden p-0.5">
                <?php 
                $sidebarLogo = null;
                $logos = glob(__DIR__ . '/../assets/img/logo*');
                if (!empty($logos)) {
                    $sidebarLogo = APP_URL . '/assets/img/' . basename($logos[0]);
                }
                ?>
                <?php if ($sidebarLogo): ?>
                    <img src="<?= $sidebarLogo ?>" alt="Logo Sekolah" class="w-full h-full object-contain">
                <?php else: ?>
                    <span class="material-symbols-outlined text-primary text-[26px]">school</span>
                <?php endif; ?>
            </div>
            <div>
                <h1 class="text-headline-sm font-headline-sm font-bold text-on-primary leading-tight">SMAN 1 Bumiayu</h1>
                <p class="text-label-md font-label-md text-on-primary/70">Portal Guru &amp; Admin</p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="md:hidden text-white/80 hover:text-white">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>

    <nav class="flex-1 space-y-1 px-md overflow-y-auto">
        <?php foreach ($menuItems as $item): ?>
            <?php $active = $currentPage === $item['key']; ?>
            <a href="<?= h($item['href']) ?>"
               class="<?= $active ? 'sidebar-active' : 'text-on-primary/80 hover:bg-white/5 hover:text-white' ?> flex items-center gap-3 px-4 py-3 rounded-DEFAULT transition-colors">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' <?= $active ? 1 : 0 ?>;"> <?= $item['icon'] ?></span>
                <span class="text-label-lg font-label-lg"><?= h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>

        <div class="pt-2 mt-2 border-t border-white/10">
            <a href="<?= APP_URL ?>/pages/pengaturan.php"
               class="<?= $currentPage === 'pengaturan' ? 'sidebar-active' : 'text-on-primary/80 hover:bg-white/5 hover:text-white' ?> flex items-center gap-3 px-4 py-3 rounded-DEFAULT transition-colors">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' <?= $currentPage === 'pengaturan' ? 1 : 0 ?>;">settings</span>
                <span class="text-label-lg font-label-lg">Pengaturan</span>
            </a>
        </div>
    </nav>

    <div class="px-lg mt-auto pt-md">
        <a href="<?= APP_URL ?>/pages/pengaturan.php" class="bg-white/10 hover:bg-white/15 transition-colors rounded-xl p-md flex items-center gap-sm">
            <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-secondary-fixed bg-primary-container flex items-center justify-center flex-shrink-0">
                <?php if (!empty($user['foto'])): ?>
                    <img class="w-full h-full object-cover" src="<?= APP_URL ?>/uploads/avatar/<?= h($user['foto']) ?>" alt="Foto profil">
                <?php else: ?>
                    <span class="text-on-primary text-label-md font-bold"><?= h(inisialNama($user['nama_lengkap'] ?? '?')) ?></span>
                <?php endif; ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-label-lg font-label-lg text-white truncate"><?= h($user['nama_lengkap'] ?? '') ?></p>
                <p class="text-label-md font-label-md text-on-primary/60 truncate"><?= h($user['mapel_keahlian'] ?? ucfirst($user['role'] ?? '')) ?></p>
            </div>
        </a>
    </div>
</aside>
