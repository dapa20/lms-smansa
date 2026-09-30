<?php
/**
 * Partial: Tab "Analisis Soal" di halaman rekap_nilai.php
 * Menampilkan analisis sebaran nilai dan statistik per komponen.
 */

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$mapelId = (int)($_GET['mapel_id'] ?? 0);
$semester = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? '2024/2025';

if ($kelasId === 0 && !empty($daftarKelas)) $kelasId = (int)$daftarKelas[0]['id'];
if ($mapelId === 0 && !empty($daftarMapel)) $mapelId = (int)$daftarMapel[0]['id'];

// Hitung statistik per komponen
$statistik = [
    'tugas_1' => ['data' => [], 'min' => null, 'max' => null, 'rata' => null, 'lulus' => 0, 'total' => 0],
    'tugas_2' => ['data' => [], 'min' => null, 'max' => null, 'rata' => null, 'lulus' => 0, 'total' => 0],
    'tugas_3' => ['data' => [], 'min' => null, 'max' => null, 'rata' => null, 'lulus' => 0, 'total' => 0],
    'uts'     => ['data' => [], 'min' => null, 'max' => null, 'rata' => null, 'lulus' => 0, 'total' => 0],
    'uas'     => ['data' => [], 'min' => null, 'max' => null, 'rata' => null, 'lulus' => 0, 'total' => 0],
];
$kkm = 75;

if ($kelasId && $mapelId) {
    // Ambil KKM
    $stmtK = $pdo->prepare('SELECT kkm FROM kkm_mapel WHERE mapel_id=? AND kelas_id=?');
    $stmtK->execute([$mapelId, $kelasId]);
    $kkmRow = $stmtK->fetch();
    if ($kkmRow) $kkm = (float)$kkmRow['kkm'];

    // Ambil data nilai
    $stmt = $pdo->prepare("SELECT tugas_1, tugas_2, tugas_3, uts, uas FROM siswa s
                           LEFT JOIN nilai n ON n.siswa_id=s.id AND n.mapel_id=? AND n.semester=? AND n.tahun_ajaran=?
                           WHERE s.kelas_id=? AND s.status='aktif'");
    $stmt->execute([$mapelId, $semester, $tahunAjaran, $kelasId]);
    $dataNilai = $stmt->fetchAll();

    foreach (['tugas_1', 'tugas_2', 'tugas_3', 'uts', 'uas'] as $key) {
        $arr = [];
        foreach ($dataNilai as $r) {
            if ($r[$key] !== null && $r[$key] !== '') {
                $val = (float)$r[$key];
                $arr[] = $val;
                if ($val >= $kkm) $statistik[$key]['lulus']++;
            }
        }
        $statistik[$key]['total'] = count($arr);
        if (count($arr) > 0) {
            $statistik[$key]['min'] = min($arr);
            $statistik[$key]['max'] = max($arr);
            $statistik[$key]['rata'] = round(array_sum($arr) / count($arr), 2);
        }
    }
}

// Distribusi rentang nilai (berdasarkan nilai akhir)
$distribusi = [
    '90-100' => 0, '80-89' => 0, '70-79' => 0, '60-69' => 0, '<60' => 0,
];
if ($kelasId && $mapelId) {
    $stmt = $pdo->prepare("SELECT tugas_1, tugas_2, tugas_3, uts, uas FROM siswa s
                           LEFT JOIN nilai n ON n.siswa_id=s.id AND n.mapel_id=? AND n.semester=? AND n.tahun_ajaran=?
                           WHERE s.kelas_id=? AND s.status='aktif'");
    $stmt->execute([$mapelId, $semester, $tahunAjaran, $kelasId]);
    foreach ($stmt->fetchAll() as $r) {
        $na = hitungNilaiAkhir($r['tugas_1'], $r['tugas_2'], $r['tugas_3'], $r['uts'], $r['uas']);
        if ($na === null) continue;
        if ($na >= 90) $distribusi['90-100']++;
        elseif ($na >= 80) $distribusi['80-89']++;
        elseif ($na >= 70) $distribusi['70-79']++;
        elseif ($na >= 60) $distribusi['60-69']++;
        else $distribusi['<60']++;
    }
}
$totalDistribusi = array_sum($distribusi);
?>

<!-- Filter -->
<div class="bg-surface-white rounded-xl p-md mb-lg shadow-sm border border-outline-variant/40">
    <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="tab" value="analisis">
        <label class="text-label-md text-text-main">Kelas:</label>
        <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php foreach ($daftarKelas as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kelasId ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-md text-text-main">Mapel:</label>
        <select name="mapel_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php foreach ($daftarMapel as $mp): ?>
                <option value="<?= (int)$mp['id'] ?>" <?= (int)$mp['id'] === $mapelId ? 'selected' : '' ?>><?= h($mp['nama_mapel']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="text-label-md text-text-main">Semester:</label>
        <select name="semester" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
            <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
        </select>
        <label class="text-label-md text-text-main">Tahun:</label>
        <select name="tahun_ajaran" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
            <?php foreach (['2023/2024', '2024/2025', '2025/2026'] as $ta): ?>
                <option value="<?= $ta ?>" <?= $tahunAjaran === $ta ? 'selected' : '' ?>><?= $ta ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="bg-surface-white rounded-xl p-lg mb-lg shadow-sm border border-outline-variant/40">
    <h3 class="text-headline-sm font-bold text-text-main flex items-center gap-2 mb-3">
        <span class="material-symbols-outlined text-primary">analytics</span>
        Analisis Sebaran Nilai — KKM: <?= $kkm ?>
    </h3>
    <p class="text-body-sm text-text-muted mb-6">Statistik nilai per komponen dan distribusi kelulusan.</p>

    <!-- Tabel statistik per komponen -->
    <div class="overflow-x-auto mb-6">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                    <th class="p-3">Komponen</th>
                    <th class="p-3 text-center">Jumlah Dinilai</th>
                    <th class="p-3 text-center">Tertinggi</th>
                    <th class="p-3 text-center">Terendah</th>
                    <th class="p-3 text-center">Rata-rata</th>
                    <th class="p-3 text-center">Tuntas (≥KKM)</th>
                    <th class="p-3 text-center">% Tuntas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant text-body-sm">
                <?php
                $labelKomponen = ['tugas_1' => 'Tugas 1', 'tugas_2' => 'Tugas 2', 'tugas_3' => 'Tugas 3', 'uts' => 'UTS', 'uas' => 'UAS'];
                foreach ($labelKomponen as $key => $label):
                    $st = $statistik[$key];
                    $pct = $st['total'] > 0 ? round(($st['lulus'] / $st['total']) * 100, 1) : 0;
                    $pctClass = $pct >= 80 ? 'text-emerald-600' : ($pct >= 60 ? 'text-amber-600' : 'text-error');
                ?>
                    <tr class="hover:bg-surface-bright transition-colors">
                        <td class="p-3 font-semibold text-text-main"><?= $label ?></td>
                        <td class="p-3 text-center text-text-muted"><?= $st['total'] ?></td>
                        <td class="p-3 text-center font-bold text-emerald-600"><?= $st['max'] ?? '-' ?></td>
                        <td class="p-3 text-center font-bold text-error"><?= $st['min'] ?? '-' ?></td>
                        <td class="p-3 text-center font-bold text-primary"><?= $st['rata'] ?? '-' ?></td>
                        <td class="p-3 text-center font-bold <?= $pctClass ?>"><?= $st['lulus'] ?></td>
                        <td class="p-3 text-center font-bold <?= $pctClass ?>"><?= $st['total'] > 0 ? $pct.'%' : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Distribusi nilai akhir -->
    <h4 class="text-label-lg font-bold text-text-main mb-3 mt-4">Distribusi Nilai Akhir</h4>
    <div class="space-y-2">
        <?php
        $warnaBar = ['90-100' => 'bg-emerald-500', '80-89' => 'bg-primary', '70-79' => 'bg-blue-500', '60-69' => 'bg-amber-500', '<60' => 'bg-error'];
        foreach ($distribusi as $range => $jumlah):
            $persen = $totalDistribusi > 0 ? ($jumlah / $totalDistribusi) * 100 : 0;
        ?>
            <div>
                <div class="flex items-center justify-between text-label-sm mb-1">
                    <span class="font-semibold text-text-main"><?= $range ?></span>
                    <span class="text-text-muted"><?= $jumlah ?> siswa (<?= round($persen, 1) ?>%)</span>
                </div>
                <div class="h-2 bg-surface-container-low rounded-full overflow-hidden">
                    <div class="h-full <?= $warnaBar[$range] ?> rounded-full transition-all" style="width: <?= $persen ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>