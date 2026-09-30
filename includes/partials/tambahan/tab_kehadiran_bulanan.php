<?php
/**
 * Partial: Tab "Kehadiran Bulanan" di halaman data_siswa.php
 * Menampilkan rekap kehadiran bulanan per siswa dalam satu bulan.
 */

$kelasAktif = (int)($_GET['kelas_id'] ?? 0);
if ($kelasAktif === 0) {
    if (!$isAdmin && !empty($kelasDiajarGuru)) $kelasAktif = (int)$kelasDiajarGuru[0]['id'];
    elseif (!empty($daftarKelas)) $kelasAktif = (int)$daftarKelas[0]['id'];
}

$namaKelas = '';
foreach ($daftarKelas as $k) {
    if ((int)$k['id'] === $kelasAktif) { $namaKelas = $k['nama_kelas']; break; }
}

$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');

// Hitung jumlah hari di bulan ini
$jumlahHari = (int)date('t', mktime(0, 0, 0, $bulan, 1, $tahun));
$tanggalAwal = sprintf('%04d-%02d-01', $tahun, $bulan);
$tanggalAkhir = sprintf('%04d-%02d-%02d', $tahun, $bulan, $jumlahHari);

// Daftar siswa + rekap
$rekap = [];
if ($kelasAktif > 0) {
    $stmt = $pdo->prepare("SELECT s.id, s.nis, s.nama_lengkap,
            COALESCE(SUM(CASE WHEN kh.status='hadir' THEN 1 END), 0) AS jml_hadir,
            COALESCE(SUM(CASE WHEN kh.status='izin'  THEN 1 END), 0) AS jml_izin,
            COALESCE(SUM(CASE WHEN kh.status='sakit' THEN 1 END), 0) AS jml_sakit,
            COALESCE(SUM(CASE WHEN kh.status='alpa'  THEN 1 END), 0) AS jml_alpa,
            COUNT(kh.id) AS total_tercatat
        FROM siswa s
        LEFT JOIN kehadiran kh ON kh.siswa_id = s.id
            AND kh.kelas_id = ?
            AND kh.tanggal BETWEEN ? AND ?
        WHERE s.kelas_id = ? AND s.status='aktif'
        GROUP BY s.id
        ORDER BY s.nama_lengkap");
    $stmt->execute([$kelasAktif, $tanggalAwal, $tanggalAkhir, $kelasAktif]);
    $rekap = $stmt->fetchAll();
}

$namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="kehadiran_bulanan">
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap">Kelas:</label>
        <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary min-w-[200px]">
            <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasAktif ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-lg font-label-lg text-text-main whitespace-nowrap ml-2">Bulan:</label>
        <select name="bulan" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $bulan === $m ? 'selected' : '' ?>><?= $namaBulan[$m] ?></option>
            <?php endfor; ?>
        </select>
        <select name="tahun" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $tahun === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<!-- Rekap -->
<div class="bg-surface-white rounded-xl shadow-sm border border-outline-variant/40 overflow-hidden">
    <div class="px-lg py-md">
        <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">calendar_month</span>
            Rekap Kehadiran Bulanan — <?= h($namaKelas) ?> (<?= $namaBulan[$bulan] ?> <?= $tahun ?>)
        </h3>
    </div>

    <?php if (empty($rekap)): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <span class="material-symbols-outlined text-[56px] text-outline-variant mb-2">person_off</span>
            <p class="text-body-md text-text-muted">Tidak ada siswa aktif di kelas ini.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                        <th class="p-4 w-12 text-center">No</th>
                        <th class="p-4">Nama Siswa</th>
                        <th class="p-4 w-24">NIS</th>
                        <th class="p-4 text-center w-20">Hadir</th>
                        <th class="p-4 text-center w-20">Izin</th>
                        <th class="p-4 text-center w-20">Sakit</th>
                        <th class="p-4 text-center w-20">Alpa</th>
                        <th class="p-4 text-center w-28">% Hadir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-sm">
                    <?php foreach ($rekap as $no => $r):
                        $totalHari = (int)$r['total_tercatat'];
                        $pct = $totalHari > 0 ? round(((int)$r['jml_hadir'] / $totalHari) * 100, 1) : null;
                        $pctClass = $pct === null ? 'text-text-muted' : ($pct >= 90 ? 'text-emerald-600' : ($pct >= 75 ? 'text-amber-600' : 'text-error'));
                    ?>
                        <tr class="hover:bg-surface-bright transition-colors">
                            <td class="p-4 text-center text-text-muted"><?= $no + 1 ?></td>
                            <td class="p-4 font-semibold text-text-main"><?= h($r['nama_lengkap']) ?></td>
                            <td class="p-4 text-text-muted font-mono text-xs"><?= h($r['nis']) ?></td>
                            <td class="p-4 text-center font-bold text-emerald-600"><?= (int)$r['jml_hadir'] ?></td>
                            <td class="p-4 text-center font-bold text-amber-600"><?= (int)$r['jml_izin'] ?></td>
                            <td class="p-4 text-center font-bold text-blue-600"><?= (int)$r['jml_sakit'] ?></td>
                            <td class="p-4 text-center font-bold text-rose-600"><?= (int)$r['jml_alpa'] ?></td>
                            <td class="p-4 text-center">
                                <span class="px-3 py-1 rounded-full <?= $pctClass ?> bg-surface-container-lowest font-bold text-label-md">
                                    <?= $pct === null ? '-' : $pct.'%' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>