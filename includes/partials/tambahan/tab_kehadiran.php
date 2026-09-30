<?php
/**
 * Partial: Tab "Kehadiran Harian" di halaman data_siswa.php
 * Memungkinkan guru/admin untuk input & lihat rekap absensi siswa kelas tertentu
 * (menggunakan tabel `kehadiran` yang sudah ada).
 */

$kelasAktif = (int)($_GET['kelas_id'] ?? 0);
if ($kelasAktif === 0) {
    if (!$isAdmin && !empty($kelasDiajarGuru)) $kelasAktif = (int)$kelasDiajarGuru[0]['id'];
    elseif (!empty($daftarKelas)) $kelasAktif = (int)$daftarKelas[0]['id'];
}

$namaKelasAktif = '';
foreach ($daftarKelas as $k) {
    if ((int)$k['id'] === $kelasAktif) { $namaKelasAktif = $k['nama_kelas']; break; }
}

$tanggalAktif = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAktif)) $tanggalAktif = date('Y-m-d');

// Daftar siswa kelas aktif
$daftarSiswaKelas = [];
if ($kelasAktif > 0) {
    $stmt = $pdo->prepare("SELECT s.id, s.nis, s.nisn, s.nama_lengkap, s.jenis_kelamin,
                                    kh.status AS status_hari_ini
                            FROM siswa s
                            LEFT JOIN kehadiran kh ON kh.siswa_id = s.id AND kh.kelas_id = ? AND kh.tanggal = ?
                            WHERE s.kelas_id = ? AND s.status = 'aktif'
                            ORDER BY s.nama_lengkap");
    $stmt->execute([$kelasAktif, $tanggalAktif, $kelasAktif]);
    $daftarSiswaKelas = $stmt->fetchAll();
}

// Statistik hari ini
$stats = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
foreach ($daftarSiswaKelas as $s) {
    $st = $s['status_hari_ini'] ?? 'hadir';
    if (isset($stats[$st])) $stats[$st]++;
}
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="kehadiran">
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap">Kelas:</label>
        <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[200px]">
            <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasAktif ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap ml-2">Tanggal:</label>
        <input type="date" name="tanggal" value="<?= h($tanggalAktif) ?>" onchange="this.form.submit()" max="<?= date('Y-m-d') ?>" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
    </form>
</div>

<!-- Statistik -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-md mb-lg">
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
            <span class="material-symbols-outlined text-emerald-600 text-[20px]">check_circle</span>
        </div>
        <div><p class="text-label-sm text-text-muted">Hadir</p><p class="text-headline-sm font-bold text-emerald-600"><?= $stats['hadir'] ?></p></div>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
            <span class="material-symbols-outlined text-amber-600 text-[20px]">event_busy</span>
        </div>
        <div><p class="text-label-sm text-text-muted">Izin</p><p class="text-headline-sm font-bold text-amber-600"><?= $stats['izin'] ?></p></div>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
            <span class="material-symbols-outlined text-blue-600 text-[20px]">medical_services</span>
        </div>
        <div><p class="text-label-sm text-text-muted">Sakit</p><p class="text-headline-sm font-bold text-blue-600"><?= $stats['sakit'] ?></p></div>
    </div>
    <div class="bg-surface-white p-md rounded-xl shadow-sm border border-outline-variant/40 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-rose-100 flex items-center justify-center">
            <span class="material-symbols-outlined text-rose-600 text-[20px]">cancel</span>
        </div>
        <div><p class="text-label-sm text-text-muted">Alpa</p><p class="text-headline-sm font-bold text-rose-600"><?= $stats['alpa'] ?></p></div>
    </div>
</div>

<!-- Form Absensi -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md border-b border-outline-variant/40 flex items-center justify-between flex-wrap gap-2">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">how_to_reg</span>
            Absensi Kelas <?= h($namaKelasAktif) ?> — <?= formatTanggalIndo($tanggalAktif) ?>
        </h3>
        <div class="flex items-center gap-2">
            <button type="button" onclick="setAllStatus('hadir')" class="px-3 py-1.5 rounded-lg text-label-sm font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">done_all</span> Semua Hadir
            </button>
            <button type="submit" form="form-kehadiran" class="px-5 py-1.5 rounded-lg text-label-sm font-bold bg-primary text-white hover:bg-primary/90 transition-colors flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">save</span> Simpan Absensi
            </button>
        </div>
    </div>

    <?php if (empty($daftarSiswaKelas)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">person_off</span>
            <p class="text-body-md text-text-muted">Tidak ada siswa aktif di kelas ini.</p>
        </div>
    <?php else: ?>
        <form id="form-kehadiran" action="../actions/profil/kehadiran_simpan.php" method="post" class="overflow-x-auto">
            <input type="hidden" name="kelas_id" value="<?= $kelasAktif ?>">
            <input type="hidden" name="tanggal" value="<?= h($tanggalAktif) ?>">
            <input type="hidden" name="redirect_to" value="data_siswa_kehadiran">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                        <th class="p-4 w-32">NIS</th>
                        <th class="p-4">Nama Siswa</th>
                        <th class="p-4 text-center w-24">Hadir</th>
                        <th class="p-4 text-center w-24">Izin</th>
                        <th class="p-4 text-center w-24">Sakit</th>
                        <th class="p-4 text-center w-24">Alpa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-sm">
                    <?php foreach ($daftarSiswaKelas as $no => $s):
                        $statusSekarang = $s['status_hari_ini'] ?: 'hadir';
                    ?>
                        <tr class="hover:bg-surface-bright transition-colors">
                            <td class="p-4 font-mono text-text-muted"><?= h($s['nis']) ?>
                                <input type="hidden" name="siswa_id[<?= $no ?>]" value="<?= (int)$s['id'] ?>">
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs flex-shrink-0">
                                        <?= h(mb_strtoupper(mb_substr($s['nama_lengkap'], 0, 1))) ?>
                                    </div>
                                    <span class="font-semibold text-text-main"><?= h($s['nama_lengkap']) ?></span>
                                </div>
                            </td>
                            <?php foreach (['hadir', 'izin', 'sakit', 'alpa'] as $st): ?>
                                <td class="p-4 text-center">
                                    <label class="inline-flex items-center justify-center cursor-pointer">
                                        <input type="radio" name="status[<?= $no ?>]" value="<?= $st ?>" <?= $statusSekarang === $st ? 'checked' : '' ?> class="w-4 h-4 text-primary focus:ring-primary">
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>

<script>
function setAllStatus(status) {
    document.querySelectorAll('#form-kehadiran input[type="radio"][value="' + status + '"]').forEach(r => r.checked = true);
}
</script>