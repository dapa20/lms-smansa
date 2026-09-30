<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'Data Siswa';
$currentPage = 'siswa';
$user        = currentUser();
$isAdmin     = isAdmin();

// Auto-pastikan tabel fitur tambahan tersedia
ensureFiturTambahanTables($pdo);

// Jika parameter tab absen_qr diakses, redirect ke halaman baru
if (($_GET['tab'] ?? '') === 'absen_qr') {
    redirect('absen_qr.php');
}

// Tab "Data Guru" hanya untuk admin. Guru dipaksa selalu ke tab "siswa".
$tab = ($isAdmin && ($_GET['tab'] ?? '') === 'guru') ? 'guru' : 'siswa';

$daftarKelas = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

// Daftar kelas yang diajar guru yang sedang login (dipakai untuk membatasi
// data yang boleh dilihat guru, dan untuk pilihan kelas saat input kehadiran).
$kelasDiajarGuru = [];
if (!$isAdmin) {
    $stmt = $pdo->prepare("SELECT DISTINCT k.id, k.nama_kelas FROM kelas k
                            JOIN jadwal_mengajar j ON j.kelas_id = k.id
                            WHERE j.guru_id = ? ORDER BY k.nama_kelas");
    $stmt->execute([$user['id']]);
    $kelasDiajarGuru = $stmt->fetchAll();
}
$idKelasDiajarGuru = array_column($kelasDiajarGuru, 'id');

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-background">
    <div class="p-md md:p-lg max-w-[1440px] mx-auto">
        <?php 
        renderFlash(); 
        
        $currentTab = $_GET['tab'] ?? '';
        $unimplementedTabs = []; // semua tab sudah lama diisi
        
        if (array_key_exists('', $unimplementedTabs) === false) {
            // tidak ada tab yang belum diimplementasi
        }
        ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-md gap-4">
            <div>
                <h2 class="text-headline-lg font-headline-lg font-semibold text-text-main mb-1">Data Siswa <?= $tab === 'guru' ? '&amp; Guru' : '' ?></h2>
                <p class="text-body-md font-body-md text-text-muted">
                    <?= $isAdmin ? 'Kelola data siswa dan data guru di seluruh sekolah.' : 'Lihat data siswa di kelas yang Anda ajar, dan catat kehadiran harian.' ?>
                </p>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Tab Switcher (khusus Admin) -->
        <div class="flex gap-2 mb-lg border-b border-outline-variant overflow-x-auto">
            <a href="data_siswa.php" class="px-4 py-2 text-label-lg font-label-lg border-b-2 -mb-px transition-colors whitespace-nowrap <?= $tab === 'siswa' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary' ?>">Data Siswa</a>
            <a href="data_siswa.php?tab=struktur" class="px-4 py-2 text-label-lg font-label-lg border-b-2 -mb-px transition-colors whitespace-nowrap <?= $currentTab === 'struktur' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary' ?>">Struktur</a>
            <a href="data_siswa.php?tab=catatan" class="px-4 py-2 text-label-lg font-label-lg border-b-2 -mb-px transition-colors whitespace-nowrap <?= $currentTab === 'catatan' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary' ?>">Catatan</a>
            <a href="data_siswa.php?tab=poin" class="px-4 py-2 text-label-lg font-label-lg border-b-2 -mb-px transition-colors whitespace-nowrap <?= $currentTab === 'poin' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary' ?>">Poin</a>
            <a href="data_siswa.php?tab=kehadiran" class="px-4 py-2 text-label-lg font-label-lg border-b-2 -mb-px transition-colors whitespace-nowrap <?= $currentTab === 'kehadiran' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary' ?>">Kehadiran Harian</a>
            <a href="data_siswa.php?tab=kehadiran_bulanan" class="px-4 py-2 text-label-lg font-label-lg border-b-2 -mb-px transition-colors whitespace-nowrap <?= $currentTab === 'kehadiran_bulanan' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary' ?>">Kehadiran Bulanan</a>
            <a href="data_siswa.php?tab=guru" class="px-4 py-2 text-label-lg font-label-lg border-b-2 -mb-px transition-colors whitespace-nowrap <?= $tab === 'guru' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary' ?>">Data Guru</a>
            <a href="data_siswa.php?tab=kenaikan" class="px-4 py-2 text-label-lg font-label-lg border-b-2 -mb-px transition-colors whitespace-nowrap <?= $currentTab === 'kenaikan' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-primary' ?>">Kenaikan</a>
        </div>
        <?php endif; ?>

        <?php
        // Routing tab tambahan
        if ($currentTab === 'struktur') {
            require __DIR__ . '/../includes/partials/tambahan/tab_struktur.php';
            echo '</div></main>';
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }
        if ($currentTab === 'catatan') {
            require __DIR__ . '/../includes/partials/tambahan/tab_catatan.php';
            echo '</div></main>';
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }
        if ($currentTab === 'poin') {
            require __DIR__ . '/../includes/partials/tambahan/tab_poin.php';
            echo '</div></main>';
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }
        if ($currentTab === 'kehadiran') {
            require __DIR__ . '/../includes/partials/tambahan/tab_kehadiran.php';
            echo '</div></main>';
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }
        if ($currentTab === 'kehadiran_bulanan') {
            require __DIR__ . '/../includes/partials/tambahan/tab_kehadiran_bulanan.php';
            echo '</div></main>';
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }
        if ($currentTab === 'kenaikan') {
            require __DIR__ . '/../includes/partials/tambahan/tab_kenaikan.php';
            echo '</div></main>';
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }
        ?>

        <?php if ($tab === 'guru'): ?>
            <?php require __DIR__ . '/../includes/partials/tab_data_guru.php'; ?>
        <?php else: ?>
            <?php require __DIR__ . '/../includes/partials/tab_data_siswa.php'; ?>
        <?php endif; ?>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
