<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/google_auth.php';

// Kalau sudah login, langsung lempar ke dashboard.
if (isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Masuk';
$errorMessage = null;

if (($_GET['error'] ?? '') === '1') {
    $errorMessage = 'Email atau kata sandi yang Anda masukkan salah. Silakan coba lagi.';
} elseif (($_GET['error'] ?? '') === '2') {
    $errorMessage = 'Akun Anda sedang tidak aktif. Silakan hubungi admin sekolah.';
} elseif (($_GET['error'] ?? '') === '3') {
    $errorMessage = 'Email akun Google (' . h($_GET['email'] ?? '') . ') tidak terdaftar di LMS. Silakan hubungi admin sekolah.';
} elseif (($_GET['error'] ?? '') === '4') {
    $errorMessage = 'Gagal melakukan verifikasi autentikasi akun Google. Silakan coba lagi.';
}
$oldEmail = h($_GET['email'] ?? '');

require_once __DIR__ . '/includes/head.php';
?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<style>

    .pattern-bg {
        background-image: radial-gradient(circle at 1px 1px, rgba(9, 76, 178, 0.08) 1px, transparent 0);
        background-size: 22px 22px;
    }
</style>

<div class="min-h-screen flex flex-col justify-between items-center relative overflow-hidden">
    <div class="absolute inset-0 pattern-bg z-0 pointer-events-none"></div>
    <div class="absolute inset-0 bg-gradient-to-br from-surface-white/90 via-surface-white/60 to-surface-white/90 z-0 pointer-events-none"></div>

    <main class="flex-grow flex items-center justify-center w-full px-4 py-8 z-10 relative">
        <div class="w-full max-w-[440px] bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] p-lg md:p-xl border border-surface-container-highest">

            <div class="text-center mb-lg">
                <?php 
                $logoPath = null;
                $logos = glob(__DIR__ . '/assets/img/logo*');
                if (!empty($logos)) {
                    $logoPath = 'assets/img/' . basename($logos[0]);
                }
                ?>
                <?php if ($logoPath): ?>
                    <div class="w-24 h-24 mx-auto mb-4 flex items-center justify-center">
                        <img src="<?= $logoPath ?>" alt="Logo Sekolah" class="max-h-full max-w-full object-contain">
                    </div>
                <?php else: ?>
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-primary flex items-center justify-center overflow-hidden border border-outline-variant shadow-sm">
                        <span class="material-symbols-outlined text-white text-[42px]">school</span>
                    </div>
                <?php endif; ?>
                <h1 class="font-headline-md text-headline-md text-on-surface mb-sm">Masuk ke Portal Guru &amp; Admin</h1>
                <p class="font-body-sm text-body-sm text-text-muted">Silakan masukkan email dan kata sandi Anda untuk melanjutkan</p>
            </div>

            <?php if ($errorMessage): ?>
                <div class="flex items-center gap-3 border border-error/30 bg-error-container text-error rounded-lg px-4 py-3 mb-md">
                    <span class="material-symbols-outlined">error</span>
                    <span class="text-body-sm font-body-sm"><?= h($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <!-- Google Sign-In Button Container -->
            <div class="mb-md">
                <?php if (isGoogleAuthConfigured()): ?>
                    <div id="g_id_onload"
                         data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                         data-context="signin"
                         data-ux_mode="popup"
                         data-login_uri="actions/auth/google_login_action.php"
                         data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin w-full flex justify-center mb-sm"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="outline"
                         data-text="signin_with"
                         data-size="large"
                         data-logo_alignment="left"
                         data-width="100%">
                    </div>
                <?php else: ?>
                    <div class="w-full flex items-center justify-center gap-3 py-2.5 px-4 border border-outline-variant rounded-lg bg-surface-container-low text-text-muted text-sm">
                        <span class="material-symbols-outlined text-[18px]">info</span>
                        <span>Google Sign-In belum dikonfigurasi. Hubungi admin.</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Divider Line -->
            <div class="relative flex py-2 items-center mb-md">
                <div class="flex-grow border-t border-outline-variant/60"></div>
                <span class="flex-shrink mx-3 text-body-xs font-body-sm text-text-muted">atau masuk dengan email</span>
                <div class="flex-grow border-t border-outline-variant/60"></div>
            </div>

            <form class="space-y-md" action="actions/auth/login_action.php" method="post">
                <div>
                    <label class="block font-label-lg text-label-lg text-on-surface mb-xs" for="email">Alamat Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                            <span class="material-symbols-outlined">person</span>
                        </div>
                        <input class="block w-full pl-10 pr-3 py-2 border border-outline rounded-lg bg-surface-white text-on-surface font-body-md text-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary transition-shadow placeholder:text-outline-variant"
                               id="email" name="email" placeholder="nama@sman1bumiayu.sch.id" type="email" value="<?= $oldEmail ?>" required autofocus>
                    </div>
                </div>
                <div>
                    <label class="block font-label-lg text-label-lg text-on-surface mb-xs" for="password">Kata Sandi</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                            <span class="material-symbols-outlined">lock</span>
                        </div>
                        <input class="block w-full pl-10 pr-10 py-2 border border-outline rounded-lg bg-surface-white text-on-surface font-body-md text-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary transition-shadow placeholder:text-outline-variant"
                               id="password" name="password" placeholder="••••••••" type="password" required>
                        <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-text-muted hover:text-primary transition-colors focus:outline-none" onclick="togglePassword()" type="button">
                            <span class="material-symbols-outlined" id="visibility-icon">visibility</span>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-sm">
                    <div class="flex items-center">
                        <input class="h-4 w-4 text-primary focus:ring-primary border-outline rounded" id="remember-me" name="remember_me" type="checkbox">
                        <label class="ml-2 block font-body-sm text-body-sm text-on-surface" for="remember-me">Ingat Saya</label>
                    </div>
                    <span class="font-label-lg text-label-lg text-text-muted" title="Hubungi admin/TU sekolah untuk reset kata sandi">Lupa Kata Sandi?</span>
                </div>
                <div class="pt-sm">
                    <button class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm font-label-lg text-label-lg font-bold text-on-primary bg-primary hover:bg-primary-fixed-dim hover:text-on-primary-fixed transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" type="submit">
                        Masuk Sekarang
                    </button>
                </div>
            </form>

            <div class="mt-lg text-center">
                <p class="font-body-sm text-body-sm text-text-muted inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">help</span>
                    Butuh bantuan masuk? Hubungi admin/TU sekolah.
                </p>
            </div>
        </div>
    </main>

    <footer class="w-full py-md px-lg text-center z-10 relative bg-surface-white/80 backdrop-blur-sm border-t border-surface-container-highest">
        <p class="font-label-md text-label-md text-text-muted">Hak Cipta &copy; <?= date('Y') ?> SMA Negeri 1 Bumiayu - Portal Guru &amp; Admin</p>
    </footer>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const visibilityIcon = document.getElementById('visibility-icon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            visibilityIcon.textContent = 'visibility_off';
        } else {
            passwordInput.type = 'password';
            visibilityIcon.textContent = 'visibility';
        }
    }


</script>
</body>
</html>

