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
<header class="fixed top-0 right-0 left-0 md:left-[280px] h-14 bg-surface-white flex items-center justify-between px-4 md:px-6 z-30 shadow-xs border-b border-outline-variant/50">
    <div class="flex items-center gap-3 min-w-0">
        <button onclick="toggleSidebar()" class="text-text-muted hover:text-primary p-1 -ml-1">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <span class="text-body-md font-medium text-text-main hidden sm:inline-block">
            TP: 2026/2027 Smt: I (satu)
        </span>
    </div>
    <div class="flex items-center gap-4 flex-shrink-0">
        <!-- Live Real-time Clock (Matches reference screenshot 22:50:37) -->
        <div id="liveClock" class="font-mono text-body-lg font-bold text-gray-800 tracking-wider">
            <?= date('H:i:s') ?>
        </div>
        <script>
            function updateClock() {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                const clockEl = document.getElementById('liveClock');
                if (clockEl) clockEl.textContent = `${h}:${m}:${s}`;
            }
            setInterval(updateClock, 1000);
        </script>
        
        <div class="hidden lg:block text-right">
            <p class="text-label-lg font-label-lg text-text-main truncate max-w-[200px]"><?= h($user['nama_lengkap'] ?? '') ?></p>
            <p class="text-label-md font-label-md text-text-muted"><?= h(ucfirst($user['role'] ?? '')) ?></p>
        </div>
        <a href="<?= APP_URL ?>/pages/pengaturan.php" class="w-9 h-9 rounded-full overflow-hidden border border-outline-variant flex items-center justify-center bg-primary-container flex-shrink-0" title="Pengaturan Akun">
            <?php if (!empty($user['foto'])): ?>
                <img class="w-full h-full object-cover" src="<?= APP_URL ?>/uploads/avatar/<?= h($user['foto']) ?>" alt="Foto profil">
            <?php else: ?>
                <span class="text-on-primary text-label-md font-bold"><?= h(inisialNama($user['nama_lengkap'] ?? '?')) ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>
