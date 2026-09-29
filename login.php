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
    .mesh-bg {
        background-color: #f8fafc;
        background-image: 
            radial-gradient(at 40% 20%, hsla(228,100%,74%,0.15) 0px, transparent 50%),
            radial-gradient(at 80% 0%, hsla(189,100%,56%,0.15) 0px, transparent 50%),
            radial-gradient(at 0% 50%, hsla(355,100%,93%,0.2) 0px, transparent 50%),
            radial-gradient(at 80% 50%, hsla(340,100%,76%,0.15) 0px, transparent 50%),
            radial-gradient(at 0% 100%, hsla(22,100%,77%,0.15) 0px, transparent 50%),
            radial-gradient(at 80% 100%, hsla(242,100%,70%,0.15) 0px, transparent 50%),
            radial-gradient(at 0% 0%, hsla(343,100%,76%,0.1) 0px, transparent 50%);
    }
    
    .glass-card {
        background: rgba(255, 255, 255, 0.65);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1), 0 0 20px -5px rgba(0,0,0,0.03);
    }
    
    .custom-input {
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(203, 213, 225, 0.6);
        box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.02);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .custom-input:focus {
        background: #ffffff;
        border-color: #3b82f6;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.02), 0 0 0 4px rgba(59, 130, 246, 0.15);
        transform: translateY(-1px);
        outline: none;
    }
    
    .btn-premium {
        background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.3), inset 0 1px 1px rgba(255, 255, 255, 0.2);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(29, 78, 216, 0.2);
    }
    .btn-premium:hover {
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
        box-shadow: 0 6px 16px -2px rgba(37, 99, 235, 0.4), inset 0 1px 1px rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }
    .btn-premium:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px -2px rgba(37, 99, 235, 0.3);
    }
    
    .logo-glow {
        position: relative;
    }
    .logo-glow::after {
        content: '';
        position: absolute;
        inset: -15px;
        background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, transparent 60%);
        z-index: -1;
        border-radius: 50%;
    }
</style>

<div class="min-h-screen flex flex-col justify-between items-center relative overflow-hidden mesh-bg">

    <main class="flex-grow flex items-center justify-center w-full px-4 py-8 z-10 relative">
        <div class="w-full max-w-[440px] glass-card rounded-2xl p-8 md:p-10">

            <div class="text-center mb-8">
                <?php 
                $logoPath = null;
                $logos = glob(__DIR__ . '/assets/img/logo*');
                if (!empty($logos)) {
                    $logoPath = 'assets/img/' . basename($logos[0]);
                }
                ?>
                <?php if ($logoPath): ?>
                    <div class="w-24 h-24 mx-auto mb-5 flex items-center justify-center logo-glow">
                        <img src="<?= $logoPath ?>" alt="Logo Sekolah" class="max-h-full max-w-full object-contain drop-shadow-md">
                    </div>
                <?php else: ?>
                    <div class="w-20 h-20 mx-auto mb-5 rounded-full bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center overflow-hidden shadow-lg logo-glow">
                        <span class="material-symbols-outlined text-white text-[42px]">school</span>
                    </div>
                <?php endif; ?>
                <h1 class="text-2xl font-bold text-slate-800 mb-2 tracking-tight">Portal Guru &amp; Admin</h1>
                <p class="text-sm text-slate-500 font-medium">Masuk untuk mengelola data akademik</p>
            </div>

            <?php if ($errorMessage): ?>
                <div class="flex items-start gap-3 border border-red-200/60 bg-red-50/80 text-red-600 rounded-xl px-4 py-3 mb-6 shadow-sm backdrop-blur-sm">
                    <span class="material-symbols-outlined text-[20px] mt-0.5">error</span>
                    <span class="text-sm font-medium leading-tight"><?= h($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <!-- Google Sign-In Button Container -->
            <div class="mb-6">
                <?php if (isGoogleAuthConfigured()): ?>
                    <div id="g_id_onload"
                         data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                         data-context="signin"
                         data-ux_mode="popup"
                         data-login_uri="actions/auth/google_login_action.php"
                         data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin w-full flex justify-center mb-4 transition-transform hover:-translate-y-0.5"
                         data-type="standard"
                         data-shape="pill"
                         data-theme="outline"
                         data-text="signin_with"
                         data-size="large"
                         data-logo_alignment="left"
                         data-width="100%">
                    </div>
                <?php else: ?>
                    <div class="w-full flex items-center justify-center gap-3 py-3 px-4 border border-slate-200 rounded-xl bg-slate-50/50 text-slate-500 text-sm shadow-inner">
                        <span class="material-symbols-outlined text-[18px]">info</span>
                        <span class="font-medium">Google Sign-In belum dikonfigurasi</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Divider Line -->
            <div class="relative flex py-2 items-center mb-6 opacity-70">
                <div class="flex-grow border-t border-slate-300"></div>
                <span class="flex-shrink mx-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">atau dengan email</span>
                <div class="flex-grow border-t border-slate-300"></div>
            </div>

            <form class="space-y-5" action="actions/auth/login_action.php" method="post">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5 ml-1" for="email">Alamat Email</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">mail</span>
                        </div>
                        <input class="block w-full pl-11 pr-4 py-2.5 rounded-xl text-slate-800 text-sm custom-input placeholder:text-slate-400"
                               id="email" name="email" placeholder="nama@sman1bumiayu.sch.id" type="email" value="<?= $oldEmail ?>" required autofocus>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1.5 ml-1" for="password">Kata Sandi</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                        </div>
                        <input class="block w-full pl-11 pr-11 py-2.5 rounded-xl text-slate-800 text-sm custom-input placeholder:text-slate-400"
                               id="password" name="password" placeholder="••••••••" type="password" required>
                        <button class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" onclick="togglePassword()" type="button">
                            <span class="material-symbols-outlined text-[20px]" id="visibility-icon">visibility</span>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-4 px-1">
                    <label class="flex items-center cursor-pointer group">
                        <div class="relative flex items-center justify-center">
                            <input class="peer sr-only" id="remember-me" name="remember_me" type="checkbox">
                            <div class="w-4 h-4 border-2 border-slate-300 rounded peer-checked:bg-blue-600 peer-checked:border-blue-600 transition-colors"></div>
                            <span class="material-symbols-outlined absolute text-white text-[12px] opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none">check</span>
                        </div>
                        <span class="ml-2 text-sm font-medium text-slate-600 group-hover:text-slate-800 transition-colors">Ingat Saya</span>
                    </label>
                    <span class="text-sm font-semibold text-blue-600 hover:text-blue-700 hover:underline cursor-pointer transition-colors" title="Hubungi admin/TU sekolah untuk reset kata sandi">Lupa Sandi?</span>
                </div>
                <div class="pt-3">
                    <button class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl text-white font-bold text-sm btn-premium focus:outline-none focus:ring-4 focus:ring-blue-500/30" type="submit">
                        Masuk Sekarang
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                </div>
            </form>

            <div class="mt-8 text-center">
                <p class="text-sm font-medium text-slate-500 inline-flex items-center gap-1.5 hover:text-slate-800 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">help</span>
                    Butuh bantuan? Hubungi admin sekolah
                </p>
            </div>
        </div>
    </main>

    <footer class="w-full py-5 px-6 text-center z-10 relative">
        <p class="text-xs font-medium text-slate-400">Hak Cipta &copy; <?= date('Y') ?> SMA Negeri 1 Bumiayu. Didesain dengan <span class="text-red-400">&hearts;</span></p>
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

