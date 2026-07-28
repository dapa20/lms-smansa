<?php
/**
 * Partial Top Navigation Bar.
 * Opsional set $pageHeading (judul kecil di kiri, untuk mobile) sebelum require.
 */
$user = currentUser();

// Badge notifikasi: jumlah pengumuman yang dibuat dalam 3 hari terakhir.
$jumlahNotifikasi = 0;
try {
    $stmtNotif = $pdo->query("SELECT COUNT(*) AS total FROM pengumuman WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)");
    $jumlahNotifikasi = (int)($stmtNotif->fetch()['total'] ?? 0);
} catch (Throwable $e) {
    $jumlahNotifikasi = 0;
}
?>
<header class="fixed top-0 right-0 left-0 md:left-[280px] h-16 bg-surface-white flex items-center justify-between px-md md:px-lg z-30 shadow-sm">
    <div class="flex items-center gap-md flex-1 min-w-0">
        <button onclick="toggleSidebar()" class="md:hidden text-text-muted hover:text-primary p-1 -ml-1">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <form action="pencarian.php" method="get" class="relative w-full max-w-md hidden sm:block">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
            <input name="q" class="w-full pl-10 pr-4 py-2 bg-surface-container-low border-none rounded-lg text-body-md focus:ring-2 focus:ring-primary/20 transition-all" placeholder="Cari data siswa, materi, atau jadwal..." type="text">
        </form>
    </div>
    <div class="flex items-center gap-sm md:gap-md flex-shrink-0">
        <div class="hidden lg:block text-right mr-sm">
            <p class="text-label-lg font-label-lg text-text-main truncate max-w-[220px]">Selamat Datang, <?= h($user['nama_lengkap'] ?? '') ?></p>
            <p class="text-label-md font-label-md text-text-muted"><?= h($user['mapel_keahlian'] ?? ucfirst($user['role'] ?? '')) ?></p>
        </div>
        <button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-surface-container transition-colors relative" title="Notifikasi">
            <span class="material-symbols-outlined text-primary">notifications</span>
            <?php if ($jumlahNotifikasi > 0): ?>
                <span class="absolute top-2 right-2 w-2 h-2 bg-tertiary rounded-full"></span>
            <?php endif; ?>
        </button>
        <div class="h-8 w-[1px] bg-outline-variant mx-1 hidden sm:block"></div>
        <a href="<?= APP_URL ?>/pages/pengaturan.php" class="w-10 h-10 rounded-full overflow-hidden border border-outline-variant flex items-center justify-center bg-primary-container flex-shrink-0" title="Pengaturan Akun">
            <?php if (!empty($user['foto'])): ?>
                <img class="w-full h-full object-cover" src="<?= APP_URL ?>/uploads/avatar/<?= h($user['foto']) ?>" alt="Foto profil">
            <?php else: ?>
                <span class="text-on-primary text-label-md font-bold"><?= h(inisialNama($user['nama_lengkap'] ?? '?')) ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>
