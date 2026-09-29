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
<header class="fixed top-0 right-0 left-0 md:left-[280px] h-16 bg-white/80 backdrop-blur-md flex items-center justify-between px-4 md:px-8 z-30 shadow-[0_4px_20px_-10px_rgba(0,0,0,0.1)] border-b border-slate-200/60 transition-all">
    <div class="flex items-center gap-4 min-w-0">
        <button onclick="toggleSidebar()" class="text-slate-500 hover:text-blue-600 hover:bg-blue-50 p-2 rounded-lg transition-colors -ml-2">
            <span class="material-symbols-outlined text-[24px]">menu</span>
        </button>
        <span class="text-sm font-semibold text-slate-600 hidden sm:inline-block bg-slate-100/80 px-3 py-1.5 rounded-lg border border-slate-200">
            TP: 2026/2027 Smt: I (satu)
        </span>
    </div>
    <div class="flex items-center gap-5 flex-shrink-0">
        <!-- Live Real-time Clock -->
        <div class="bg-blue-50 border border-blue-100 px-4 py-1.5 rounded-lg hidden sm:flex items-center gap-2">
            <span class="material-symbols-outlined text-blue-500 text-[18px]">schedule</span>
            <div id="liveClock" class="font-mono text-sm font-bold text-blue-700 tracking-wider">
                <?= date('H:i:s') ?>
            </div>
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
        
        <div class="h-8 w-px bg-slate-200 hidden md:block"></div>

        <div class="flex items-center gap-3">
            <div class="hidden lg:block text-right">
                <p class="text-sm font-bold text-slate-800 truncate max-w-[200px]"><?= h($user['nama_lengkap'] ?? '') ?></p>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide"><?= h(ucfirst($user['role'] ?? '')) ?></p>
            </div>
            <a href="<?= APP_URL ?>/pages/pengaturan.php" class="relative w-10 h-10 rounded-full overflow-hidden border-2 border-white shadow-md flex items-center justify-center bg-gradient-to-tr from-blue-500 to-indigo-500 flex-shrink-0 hover:scale-105 transition-transform" title="Pengaturan Akun">
                <?php if (!empty($user['foto'])): ?>
                    <img class="w-full h-full object-cover" src="<?= APP_URL ?>/uploads/avatar/<?= h($user['foto']) ?>" alt="Foto profil">
                <?php else: ?>
                    <span class="text-white text-sm font-bold"><?= h(inisialNama($user['nama_lengkap'] ?? '?')) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</header>
