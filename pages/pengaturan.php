<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'Pengaturan';
$currentPage = 'pengaturan';
$user        = currentUser();
$isAdmin     = isAdmin();

// Ambil info kelas yang diwalikan guru (jika ada)
$namaWaliKelas = '-';
if (!$isAdmin && !empty($user['wali_kelas_of'])) {
    $stmtW = $pdo->prepare('SELECT nama_kelas FROM kelas WHERE id = ? LIMIT 1');
    $stmtW->execute([$user['wali_kelas_of']]);
    $namaWaliKelas = $stmtW->fetch()['nama_kelas'] ?? '-';
} elseif ($isAdmin) {
    // Admin: cek di tabel kelas apakah ada yg wali_kelas_id = user ini
    $stmtW = $pdo->prepare('SELECT nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1');
    $stmtW->execute([$user['id']]);
    $namaWaliKelas = $stmtW->fetch()['nama_kelas'] ?? '-';
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-background">
    <div class="p-md md:p-gutter max-w-container-max mx-auto w-full">
        <?php renderFlash(); ?>

        <div class="mb-lg">
            <h2 class="text-headline-lg font-headline-lg font-semibold text-text-main mb-1">Pengaturan</h2>
            <p class="text-body-md font-body-md text-text-muted">
                <?= $isAdmin
                    ? 'Kelola informasi profil, keamanan akun, dan preferensi notifikasi Anda.'
                    : 'Lihat data pribadi Anda dan kelola keamanan akun.'
                ?>
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">

            <!-- ====================================================
                 KOLOM KIRI: Data Pribadi / Profil
                 ==================================================== -->
            <div class="lg:col-span-2 space-y-lg">

                <?php if ($isAdmin): ?>
                <!-- ── Admin: Form edit profil lengkap ── -->
                <div class="bg-surface-white rounded-xl shadow-sm p-lg">
                    <h3 class="text-headline-sm font-headline-sm text-text-main mb-4 pb-4 border-b border-outline-variant">Informasi Profil</h3>
                    <form action="../actions/profil/profil_update.php" method="post" enctype="multipart/form-data" class="space-y-4">
                        <div class="flex items-center gap-4 mb-2">
                            <div class="w-16 h-16 rounded-full bg-primary-container flex items-center justify-center overflow-hidden border border-outline-variant flex-shrink-0">
                                <?php if (!empty($user['foto'])): ?>
                                    <img src="uploads/avatar/<?= h($user['foto']) ?>" class="w-full h-full object-cover" alt="Foto profil">
                                <?php else: ?>
                                    <span class="text-on-primary-container text-headline-sm font-bold"><?= h(inisialNama($user['nama_lengkap'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="inline-block cursor-pointer text-label-lg font-label-lg text-primary hover:underline">
                                    Ganti Foto Profil
                                    <input type="file" name="foto" accept="image/png,image/jpeg" class="hidden">
                                </label>
                                <p class="text-label-md text-text-muted">Format JPG/PNG, maks. 2MB.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-label-md font-label-md text-text-main block mb-1">Nama Lengkap</label>
                                <input required name="nama_lengkap" value="<?= h($user['nama_lengkap']) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            </div>
                            <div>
                                <label class="text-label-md font-label-md text-text-main block mb-1">Email</label>
                                <input required type="email" name="email" value="<?= h($user['email']) ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            </div>
                            <div>
                                <label class="text-label-md font-label-md text-text-main block mb-1">NIP</label>
                                <input name="nip" value="<?= h($user['nip'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            </div>
                            <div>
                                <label class="text-label-md font-label-md text-text-main block mb-1">Bidang Keahlian</label>
                                <input name="mapel_keahlian" value="<?= h($user['mapel_keahlian'] ?? '') ?>" placeholder="Contoh: Guru Matematika" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Bio Singkat</label>
                            <textarea name="bio" rows="3" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"><?= h($user['bio'] ?? '') ?></textarea>
                        </div>
                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-6 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan Profil</button>
                        </div>
                    </form>
                </div>

                <?php else: ?>
                <!-- ── Guru: Data Pribadi (read-only, hanya no. telp yg bisa diubah) ── -->
                <div class="bg-surface-white rounded-xl shadow-sm p-lg">
                    <!-- Avatar + info ringkas -->
                    <div class="flex items-center gap-4 mb-lg pb-4 border-b border-outline-variant">
                        <div class="w-16 h-16 rounded-full bg-primary-container flex items-center justify-center overflow-hidden border border-outline-variant flex-shrink-0 flex-shrink-0">
                            <?php if (!empty($user['foto'])): ?>
                                <img src="uploads/avatar/<?= h($user['foto']) ?>" class="w-full h-full object-cover" alt="Foto profil">
                            <?php else: ?>
                                <span class="text-on-primary-container text-headline-sm font-bold"><?= h(inisialNama($user['nama_lengkap'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-headline-sm font-bold text-text-main"><?= h($user['nama_lengkap']) ?></p>
                            <p class="text-body-sm text-text-muted"><?= h($user['mapel_keahlian'] ?? 'Guru') ?></p>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-primary/10 text-primary">
                                <?= $user['role'] === 'admin' ? 'Admin' : 'Guru' ?>
                            </span>
                        </div>
                    </div>

                    <h3 class="text-headline-sm font-headline-sm text-text-main mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">person</span>
                        Data Pribadi
                    </h3>
                    <p class="text-body-sm text-text-muted mb-5 flex items-center gap-1.5 bg-surface-container-low rounded-lg px-3 py-2">
                        <span class="material-symbols-outlined text-[16px] text-outline">info</span>
                        Data di bawah dikelola oleh Admin. Untuk perubahan, silakan hubungi Admin/TU sekolah.
                    </p>

                    <!-- Grid data pribadi read-only -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <?php
                        $jenisKelaminLabel = match($user['jenis_kelamin'] ?? null) {
                            'L'  => 'Laki-laki',
                            'P'  => 'Perempuan',
                            default => '-',
                        };
                        $dataItems = [
                            ['icon' => 'badge',            'label' => 'Nama Lengkap',  'value' => $user['nama_lengkap']],
                            ['icon' => 'alternate_email',  'label' => 'Email',          'value' => $user['email']],
                            ['icon' => 'id_card',          'label' => 'NIP',            'value' => $user['nip'] ?? '-'],
                            ['icon' => 'class',            'label' => 'Wali Kelas',     'value' => $namaWaliKelas],
                            ['icon' => 'wc',               'label' => 'Jenis Kelamin',  'value' => $jenisKelaminLabel],
                            ['icon' => 'mosque',           'label' => 'Agama',          'value' => $user['agama'] ?? '-'],
                            ['icon' => 'cake',             'label' => 'Tanggal Lahir',  'value' => $user['tanggal_lahir'] ? formatTanggalIndo($user['tanggal_lahir']) : '-'],
                            ['icon' => 'menu_book',        'label' => 'Bidang Keahlian','value' => $user['mapel_keahlian'] ?? '-'],
                        ];
                        ?>
                        <?php foreach ($dataItems as $item): ?>
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-surface-container-low border border-outline-variant/40">
                            <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-primary text-[16px]"><?= $item['icon'] ?></span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-label-sm text-text-muted mb-0.5"><?= $item['label'] ?></p>
                                <p class="text-body-sm font-semibold text-text-main truncate"><?= h($item['value']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Form edit no. telepon (satu-satunya yang boleh diubah sendiri) -->
                    <div class="border-t border-outline-variant pt-5">
                        <h4 class="text-label-lg font-semibold text-text-main mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-primary">phone</span>
                            Nomor Telepon
                            <span class="text-label-sm font-normal text-text-muted">(dapat diubah sendiri)</span>
                        </h4>
                        <form action="../actions/profil/profil_update.php" method="post" class="flex gap-3 items-end">
                            <div class="flex-1">
                                <label class="text-label-sm text-text-muted block mb-1">No. Telepon / WhatsApp</label>
                                <input name="no_telp" value="<?= h($user['no_telp'] ?? '') ?>"
                                       placeholder="Contoh: 0812-3456-7890"
                                       class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none text-body-sm">
                            </div>
                            <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-semibold bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm whitespace-nowrap flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">save</span> Simpan
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ====================================================
                 KOLOM KANAN: Keamanan + Notifikasi + Logout
                 ==================================================== -->
            <div class="flex flex-col gap-lg">
                <!-- Keamanan -->
                <div class="bg-surface-white rounded-xl shadow-sm p-lg">
                    <h3 class="text-headline-sm font-headline-sm text-text-main mb-4 pb-4 border-b border-outline-variant">Keamanan</h3>
                    <form action="../actions/profil/password_update.php" method="post" class="space-y-3">
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Kata Sandi Saat Ini</label>
                            <input required type="password" name="password_lama" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        </div>
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Kata Sandi Baru</label>
                            <input required type="password" name="password_baru" minlength="6" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        </div>
                        <div>
                            <label class="text-label-md font-label-md text-text-main block mb-1">Konfirmasi Kata Sandi Baru</label>
                            <input required type="password" name="password_konfirmasi" minlength="6" class="w-full px-4 py-2 rounded-lg border border-outline-variant focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        </div>
                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Ubah Kata Sandi</button>
                        </div>
                    </form>
                </div>

                <!-- Preferensi Notifikasi -->
                <div class="bg-surface-white rounded-xl shadow-sm p-lg">
                    <h3 class="text-headline-sm font-headline-sm text-text-main mb-4 pb-4 border-b border-outline-variant">Preferensi Notifikasi</h3>
                    <form action="../actions/profil/notifikasi_update.php" method="post" class="space-y-4">
                        <?php
                            $preferensi = [
                                'notif_email' => 'Notifikasi Email',
                                'notif_push'  => 'Notifikasi Push (Browser)',
                                'notif_sms'   => 'Notifikasi SMS',
                            ];
                        ?>
                        <?php foreach ($preferensi as $key => $label): ?>
                            <div class="flex items-center justify-between">
                                <span class="text-body-sm font-body-sm text-text-main"><?= $label ?></span>
                                <div class="relative">
                                    <input type="checkbox" name="<?= $key ?>" id="<?= $key ?>" class="toggle-checkbox absolute w-6 h-6 opacity-0 cursor-pointer" <?= $user[$key] ? 'checked' : '' ?>>
                                    <label for="<?= $key ?>" class="toggle-label block"></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan Preferensi</button>
                        </div>
                    </form>
                </div>

                <!-- Logout -->
                <div class="bg-surface-white rounded-xl shadow-sm p-lg">
                    <a href="../actions/auth/logout.php" class="w-full flex items-center justify-center gap-2 px-5 py-2 rounded-lg text-label-lg font-label-lg text-error border border-error/30 hover:bg-error-container transition-colors">
                        <span class="material-symbols-outlined">logout</span> Keluar dari Akun
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
