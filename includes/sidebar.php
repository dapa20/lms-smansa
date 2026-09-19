<?php
/**
 * Partial Sidebar Navigasi - SMA N 1 Bumiayu
 * Menampilkan hirarki navigasi sesuai sistem E-Learning & Portal Sekolah.
 */
$currentPage = $currentPage ?? 'dashboard';
$user = currentUser();

$sidebarLogo = null;
$logos = glob(__DIR__ . '/../assets/img/logo*');
if (!empty($logos)) {
    $sidebarLogo = APP_URL . '/assets/img/' . basename($logos[0]);
}
?>
<!-- Overlay untuk tampilan mobile (klik di luar sidebar untuk menutup) -->
<div id="sidebar-overlay" class="hidden fixed inset-0 bg-black/40 z-40 md:hidden" onclick="toggleSidebar()"></div>

<aside id="sidebar" class="fixed left-0 top-0 h-screen w-[280px] bg-white border-r border-gray-200/80 flex flex-col z-50 -translate-x-full md:translate-x-0 transition-transform duration-200 shadow-sm text-gray-800">
    
    <!-- Header Logo & Nama Sekolah (Persis Tangkapan Layar) -->
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

    <!-- Header Profil Pengguna (Foto Circular & Nama Guru/Admin) -->
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
            
            <!-- Beranda -->
            <?php $isBeranda = $currentPage === 'dashboard'; ?>
            <a href="<?= APP_URL ?>/index.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg transition-all <?= $isBeranda ? 'bg-[#10b981] text-white font-bold shadow-xs' : 'text-gray-700 hover:bg-gray-100' ?>">
                <span class="material-symbols-outlined text-[20px]">desktop_windows</span>
                <span>Beranda</span>
            </a>

            <!-- Profile -->
            <?php $isProfile = $currentPage === 'pengaturan'; ?>
            <a href="<?= APP_URL ?>/pages/pengaturan.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg transition-all <?= $isProfile ? 'bg-[#10b981] text-white font-bold shadow-xs' : 'text-gray-700 hover:bg-gray-100' ?>">
                <span class="material-symbols-outlined text-[20px]">person</span>
                <span>Profile</span>
            </a>

            <!-- Pengumuman -->
            <?php $isPengumuman = $currentPage === 'pengumuman'; ?>
            <a href="<?= APP_URL ?>/pages/pengumuman.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg transition-all <?= $isPengumuman ? 'bg-[#10b981] text-white font-bold shadow-xs' : 'text-gray-700 hover:bg-gray-100' ?>">
                <span class="material-symbols-outlined text-[20px]">campaign</span>
                <span>Pengumuman</span>
            </a>

            <!-- Dropdown Wali Kelas -->
            <div class="pt-1">
                <button type="button" onclick="toggleDropdown('dropdownWaliKelas')" 
                        class="w-full flex items-center justify-between px-3.5 py-2.5 bg-[#e9ecef] hover:bg-gray-300/80 rounded-lg text-gray-800 font-semibold transition-all">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">pie_chart</span>
                        <span>Wali Kelas</span>
                    </div>
                    <span id="icon-dropdownWaliKelas" class="material-symbols-outlined text-[20px] transition-transform duration-200">keyboard_arrow_down</span>
                </button>
                <div id="dropdownWaliKelas" class="hidden pl-4 pt-1 space-y-0.5">
                    <a href="<?= APP_URL ?>/pages/kelas_jadwal.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">calendar_month</span> Jadwal Kelas Saya
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php?tab=absen_qr" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">qr_code_2</span> Absen QR Code
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">fact_check</span> Rekap Presensi
                    </a>
                    <a href="<?= APP_URL ?>/pages/kelas_jadwal.php?tab=jurnal" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">auto_stories</span> Jurnal Kelas
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">group</span> Siswa
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php?tab=struktur" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">radio_button_unchecked</span> Struktur
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php?tab=catatan" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">edit_note</span> Catatan
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php?tab=poin" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">star</span> Poin Kelas
                    </a>
                </div>
            </div>

            <!-- Dropdown Belajar Mengajar -->
            <div class="pt-1">
                <button type="button" onclick="toggleDropdown('dropdownBelajarMengajar')" 
                        class="w-full flex items-center justify-between px-3.5 py-2.5 bg-[#e9ecef] hover:bg-gray-300/80 rounded-lg text-gray-800 font-semibold transition-all">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">domain</span>
                        <span>Belajar Mengajar</span>
                    </div>
                    <span id="icon-dropdownBelajarMengajar" class="material-symbols-outlined text-[20px] transition-transform duration-200">keyboard_arrow_down</span>
                </button>
                <div id="dropdownBelajarMengajar" class="hidden pl-4 pt-1 space-y-0.5">
                    <a href="<?= APP_URL ?>/pages/kelas_jadwal.php?tab=jurnal" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">auto_stories</span> Jurnal
                    </a>
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php?tab=laporan" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">description</span> Laporan Kinerja Harian
                    </a>
                    <a href="<?= APP_URL ?>/pages/kelas_jadwal.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">calendar_today</span> Jadwal Mengajar
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php?tab=poin" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">star</span> Input Poin Siswa
                    </a>
                </div>
            </div>

            <!-- Dropdown E-Learning -->
            <div class="pt-1">
                <button type="button" onclick="toggleDropdown('dropdownELearning')" 
                        class="w-full flex items-center justify-between px-3.5 py-2.5 bg-[#e9ecef] hover:bg-gray-300/80 rounded-lg text-gray-800 font-semibold transition-all">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">computer</span>
                        <span>E-Learning</span>
                    </div>
                    <span id="icon-dropdownELearning" class="material-symbols-outlined text-[20px] transition-transform duration-200">keyboard_arrow_down</span>
                </button>
                <div id="dropdownELearning" class="hidden pl-4 pt-1 space-y-0.5">
                    <a href="<?= APP_URL ?>/pages/materi.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">build</span> Materi
                    </a>
                    <a href="<?= APP_URL ?>/pages/tugas_ujian.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">assignment</span> Tugas
                    </a>
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">assignment_turned_in</span> Nilai Harian
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php?tab=kehadiran" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">how_to_reg</span> Kehadiran Harian
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php?tab=kehadiran_bulanan" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span> Kehadiran Bulanan
                    </a>
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">emoji_events</span> Rekap Nilai
                    </a>
                    <a href="<?= APP_URL ?>/pages/pengaturan.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">edit_note</span> Catatan Guru
                    </a>
                </div>
            </div>

            <!-- Dropdown Ulangan / Ujian -->
            <div class="pt-1">
                <button type="button" onclick="toggleDropdown('dropdownUjian')" 
                        class="w-full flex items-center justify-between px-3.5 py-2.5 bg-[#e9ecef] hover:bg-gray-300/80 rounded-lg text-gray-800 font-semibold transition-all">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">school</span>
                        <span>Ulangan / Ujian</span>
                    </div>
                    <span id="icon-dropdownUjian" class="material-symbols-outlined text-[20px] transition-transform duration-200">keyboard_arrow_down</span>
                </button>
                <div id="dropdownUjian" class="hidden pl-4 pt-1 space-y-0.5">
                    <a href="<?= APP_URL ?>/pages/export_nilai.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">print</span> Cetak
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">person_search</span> Status Siswa
                    </a>
                    <a href="<?= APP_URL ?>/pages/tugas_ujian.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">article</span> Hasil Ujian
                    </a>
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php?tab=analisis" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">analytics</span> Analisis Soal
                    </a>
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">emoji_events</span> Rekap Nilai
                    </a>
                </div>
            </div>
        </div>

        <!-- KELOMPOK: PENILAIAN -->
        <div class="space-y-1 pt-2">
            <div class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">PENILAIAN</div>

            <!-- Dropdown Data Rapor -->
            <div>
                <button type="button" onclick="toggleDropdown('dropdownDataRapor')" 
                        class="w-full flex items-center justify-between px-3.5 py-2.5 bg-[#e9ecef] hover:bg-gray-300/80 rounded-lg text-gray-800 font-semibold transition-all">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">pie_chart</span>
                        <span>Data Rapor</span>
                    </div>
                    <span id="icon-dropdownDataRapor" class="material-symbols-outlined text-[20px] transition-transform duration-200">keyboard_arrow_down</span>
                </button>
                <div id="dropdownDataRapor" class="hidden pl-4 pt-1 space-y-0.5">
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php?tab=kkm" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">balance</span> KKM dan Bobot
                    </a>
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php?tab=indikator" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">menu_book</span> Indikator Nilai
                    </a>
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">group_add</span> Input Nilai
                    </a>
                </div>
            </div>

            <!-- Dropdown Input Wali Kelas -->
            <div class="pt-1">
                <button type="button" onclick="toggleDropdown('dropdownInputWaliKelas')" 
                        class="w-full flex items-center justify-between px-3.5 py-2.5 bg-[#e9ecef] hover:bg-gray-300/80 rounded-lg text-gray-800 font-semibold transition-all">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[20px]">pie_chart</span>
                        <span>Input Wali Kelas</span>
                    </div>
                    <span id="icon-dropdownInputWaliKelas" class="material-symbols-outlined text-[20px] transition-transform duration-200">keyboard_arrow_down</span>
                </button>
                <div id="dropdownInputWaliKelas" class="hidden pl-4 pt-1 space-y-0.5">
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php?tab=spiritual" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">menu_book</span> Sikap Spiritual
                    </a>
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php?tab=sosial" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">menu_book</span> Sikap Sosial
                    </a>
                    <a href="<?= APP_URL ?>/pages/rekap_nilai.php?tab=prestasi" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">groups</span> Prestasi
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">groups</span> Kehadiran
                    </a>
                    <a href="<?= APP_URL ?>/pages/data_siswa.php?tab=kenaikan" class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-emerald-600 hover:bg-gray-50 rounded-md transition-colors text-[13px]">
                        <span class="material-symbols-outlined text-[18px]">groups</span> Kenaikan
                    </a>
                </div>
            </div>
        </div>

        <!-- KELOMPOK: CETAK -->
        <div class="space-y-1 pt-2">
            <div class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">CETAK</div>

            <a href="<?= APP_URL ?>/pages/export_nilai.php?tipe=pts" 
               class="flex items-center gap-3 px-3.5 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-all text-body-md">
                <span class="material-symbols-outlined text-[20px]">menu_book</span>
                <span>Rapor PTS</span>
            </a>
            <a href="<?= APP_URL ?>/pages/export_nilai.php?tipe=pas" 
               class="flex items-center gap-3 px-3.5 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-all text-body-md">
                <span class="material-symbols-outlined text-[20px]">menu_book</span>
                <span>Rapor Akhir</span>
            </a>
            <a href="<?= APP_URL ?>/pages/export_nilai.php?tipe=ledger" 
               class="flex items-center gap-3 px-3.5 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-all text-body-md">
                <span class="material-symbols-outlined text-[20px]">groups</span>
                <span>Ledger</span>
            </a>
            <a href="<?= APP_URL ?>/pages/export_nilai.php?tipe=dkn" 
               class="flex items-center gap-3 px-3.5 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-all text-body-md">
                <span class="material-symbols-outlined text-[20px]">groups</span>
                <span>DKN</span>
            </a>
        </div>

        <!-- KELOMPOK: ARSIP -->
        <div class="space-y-1 pt-2 pb-6">
            <div class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">ARSIP</div>

            <a href="<?= APP_URL ?>/pages/export_nilai.php?tab=arsip" 
               class="flex items-center gap-3 px-3.5 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-all text-body-md">
                <span class="material-symbols-outlined text-[20px]">account_balance</span>
                <span>Arsip Rapor</span>
            </a>
        </div>

    </nav>
</aside>

<script>
    function toggleDropdown(id) {
        const el = document.getElementById(id);
        const icon = document.getElementById('icon-' + id);
        if (el) {
            el.classList.toggle('hidden');
            if (icon) {
                icon.classList.toggle('rotate-180');
            }
        }
    }
</script>
