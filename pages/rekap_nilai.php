<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'Rekap Nilai';
$currentPage = 'nilai';
$user        = currentUser();
$isAdmin     = isAdmin();

if ($isAdmin) {
    $daftarKelas = $pdo->query('SELECT * FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();
    $daftarMapel = $pdo->query('SELECT * FROM mata_pelajaran ORDER BY nama_mapel')->fetchAll();
    $pasanganDiizinkan = null; // admin: semua kombinasi kelas+mapel diizinkan
} else {
    // Guru hanya boleh melihat/menilai kelas & mapel yang benar-benar ia ajar.
    $stmt = $pdo->prepare("SELECT DISTINCT k.id AS kelas_id, k.nama_kelas, k.tingkat, k.program,
                                   m.id AS mapel_id, m.nama_mapel
                            FROM jadwal_mengajar j
                            JOIN kelas k ON k.id = j.kelas_id
                            JOIN mata_pelajaran m ON m.id = j.mapel_id
                            WHERE j.guru_id = ?
                            ORDER BY k.nama_kelas, m.nama_mapel");
    $stmt->execute([$user['id']]);
    $pasanganDiizinkan = $stmt->fetchAll();

    $daftarKelas = [];
    $daftarMapel = [];
    $kelasSeen = []; $mapelSeen = [];
    foreach ($pasanganDiizinkan as $p) {
        if (!isset($kelasSeen[$p['kelas_id']])) {
            $daftarKelas[] = ['id' => $p['kelas_id'], 'nama_kelas' => $p['nama_kelas']];
            $kelasSeen[$p['kelas_id']] = true;
        }
        if (!isset($mapelSeen[$p['mapel_id']])) {
            $daftarMapel[] = ['id' => $p['mapel_id'], 'nama_mapel' => $p['nama_mapel']];
            $mapelSeen[$p['mapel_id']] = true;
        }
    }
}

// ---------------------------------------------------------------------
// FILTER (default ke kelas & mapel pertama supaya halaman tidak kosong)
// ---------------------------------------------------------------------
$kelasId     = $_GET['kelas_id'] ?? ($daftarKelas[0]['id'] ?? '');
$mapelId     = $_GET['mapel_id'] ?? ($daftarMapel[0]['id'] ?? '');
$semester    = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? '2024/2025';

// Guru tidak boleh mengintip kelas/mapel di luar yang ia ajar (proteksi dari manipulasi URL).
if (!$isAdmin && $pasanganDiizinkan !== null) {
    $kombinasiValid = false;
    foreach ($pasanganDiizinkan as $p) {
        if ((string)$p['kelas_id'] === (string)$kelasId && (string)$p['mapel_id'] === (string)$mapelId) {
            $kombinasiValid = true;
            break;
        }
    }
    if (!$kombinasiValid) {
        $kelasId = $pasanganDiizinkan[0]['kelas_id'] ?? '';
        $mapelId = $pasanganDiizinkan[0]['mapel_id'] ?? '';
    }
}

$daftarNilai = [];
if ($kelasId && $mapelId) {
    $stmt = $pdo->prepare("SELECT s.id AS siswa_id, s.nama_lengkap, s.nis,
                                   n.id AS nilai_id, n.tugas_1, n.tugas_2, n.tugas_3, n.uts, n.uas, n.catatan
                            FROM siswa s
                            LEFT JOIN nilai n ON n.siswa_id = s.id AND n.mapel_id = ? AND n.semester = ? AND n.tahun_ajaran = ?
                            WHERE s.kelas_id = ? AND s.status = 'aktif'
                            ORDER BY s.nama_lengkap");
    $stmt->execute([$mapelId, $semester, $tahunAjaran, $kelasId]);
    $daftarNilai = $stmt->fetchAll();
}

// Hitung nilai akhir tiap siswa + statistik kelas
$nilaiAkhirList = [];
foreach ($daftarNilai as &$row) {
    $row['nilai_akhir'] = hitungNilaiAkhir($row['tugas_1'], $row['tugas_2'], $row['tugas_3'], $row['uts'], $row['uas']);
    if ($row['nilai_akhir'] !== null) {
        $nilaiAkhirList[] = $row['nilai_akhir'];
    }
}
unset($row);


$rataKelas   = count($nilaiAkhirList) ? round(array_sum($nilaiAkhirList) / count($nilaiAkhirList), 1) : null;
$nilaiTinggi = count($nilaiAkhirList) ? max($nilaiAkhirList) : null;
$nilaiRendah = count($nilaiAkhirList) ? min($nilaiAkhirList) : null;
$jumlahDinilai = count($nilaiAkhirList);
$jumlahSiswaKelas = count($daftarNilai);

// Nama kelas & mapel aktif untuk header spreadsheet
$namaKelasAktif = '';
foreach ($daftarKelas as $k) {
    if ((string)$k['id'] === (string)$kelasId) {
        $namaKelasAktif = $k['nama_kelas'];
        break;
    }
}
$namaMapelAktif = '';
foreach ($daftarMapel as $mp) {
    if ((string)$mp['id'] === (string)$mapelId) {
        $namaMapelAktif = $mp['nama_mapel'];
        break;
    }
}

// Mode edit nilai
$editData = null;
if (!empty($_GET['edit_siswa'])) {
    foreach ($daftarNilai as $row) {
        if ((int)$row['siswa_id'] === (int)$_GET['edit_siswa']) {
            $editData = $row;
            break;
        }
    }
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>
<main class="pt-16 md:ml-[280px] min-h-screen bg-background">
    <div class="p-md md:p-lg max-w-[1440px] mx-auto">
        <?php renderFlash(); ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-lg gap-4">
            <div>
                <h2 class="text-headline-lg font-headline-lg font-semibold text-text-main mb-1">Rekap Nilai</h2>
                <p class="text-body-md font-body-md text-text-muted">Pantau dan kelola nilai akhir siswa per mata pelajaran.</p>
            </div>
            <?php if ($kelasId && $mapelId): ?>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="openSpreadsheetPreview()"
                            class="bg-[#107c41] hover:bg-[#0b5c30] text-white px-5 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined">table_view</span> Pratinjau Spreadsheet
                    </button>
                    <a href="export_nilai.php?<?= http_build_query(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran, 'format' => 'excel']) ?>"
                       class="bg-surface-white border border-outline-variant hover:border-primary hover:text-primary text-text-main px-5 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
                        <span class="material-symbols-outlined">download</span> Ekspor ke Excel (.xls)
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($daftarKelas)): ?>
            <div class="bg-surface-white rounded-xl p-xl text-center text-text-muted shadow-sm">
                <span class="material-symbols-outlined text-[48px] mb-2 block">school</span>
                Anda belum ditugaskan mengajar kelas manapun. Hubungi Admin untuk pengaturan jadwal mengajar.
            </div>
        <?php else: ?>

        <!-- Filter -->
        <form method="get" class="bg-surface-white rounded-xl p-md mb-lg flex flex-wrap gap-3 items-center shadow-sm">
            <select name="kelas_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <?php foreach ($daftarKelas as $k): ?>
                    <option value="<?= (int)$k['id'] ?>" <?= (string)$kelasId === (string)$k['id'] ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="mapel_id" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <?php foreach ($daftarMapel as $mp): ?>
                    <option value="<?= (int)$mp['id'] ?>" <?= (string)$mapelId === (string)$mp['id'] ? 'selected' : '' ?>><?= h($mp['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="semester" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Semester Ganjil</option>
                <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Semester Genap</option>
            </select>
            <select name="tahun_ajaran" onchange="this.form.submit()" class="border border-outline-variant rounded-lg px-3 py-2 text-body-sm focus:outline-none focus:border-primary">
                <?php foreach (['2023/2024', '2024/2025', '2025/2026'] as $ta): ?>
                    <option value="<?= $ta ?>" <?= $tahunAjaran === $ta ? 'selected' : '' ?>><?= $ta ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- Statistik -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-lg mb-lg">
            <div class="bg-surface-white p-lg rounded-xl shadow-sm">
                <p class="text-label-md font-label-md text-text-muted mb-1">Rata-rata Kelas</p>
                <h3 class="text-headline-md font-headline-md text-primary"><?= $rataKelas ?? '-' ?></h3>
            </div>
            <div class="bg-surface-white p-lg rounded-xl shadow-sm">
                <p class="text-label-md font-label-md text-text-muted mb-1">Nilai Tertinggi</p>
                <h3 class="text-headline-md font-headline-md text-text-main"><?= $nilaiTinggi ?? '-' ?></h3>
            </div>
            <div class="bg-surface-white p-lg rounded-xl shadow-sm">
                <p class="text-label-md font-label-md text-text-muted mb-1">Nilai Terendah</p>
                <h3 class="text-headline-md font-headline-md text-error"><?= $nilaiRendah ?? '-' ?></h3>
            </div>
            <div class="bg-surface-white p-lg rounded-xl shadow-sm">
                <p class="text-label-md font-label-md text-text-muted mb-1">Sudah Dinilai</p>
                <h3 class="text-headline-md font-headline-md text-text-main"><?= $jumlahDinilai ?>/<?= $jumlahSiswaKelas ?></h3>
            </div>
        </div>

        <!-- Tabel Nilai -->
        <div class="bg-surface-white rounded-xl shadow-sm overflow-hidden">
            <div class="table-container overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[760px]">
                    <thead>
                        <tr class="bg-surface-container-low text-label-md font-label-md text-text-muted uppercase tracking-wider border-b border-outline-variant">
                            <th class="p-4">Nama Siswa</th>
                            <th class="p-4 text-center">T1</th>
                            <th class="p-4 text-center">T2</th>
                            <th class="p-4 text-center">T3</th>
                            <th class="p-4 text-center">UTS</th>
                            <th class="p-4 text-center">UAS</th>
                            <th class="p-4 text-center">Nilai Akhir</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant text-body-sm">
                        <?php if (empty($daftarNilai)): ?>
                            <tr><td colspan="8" class="p-8 text-center text-text-muted">Tidak ada siswa aktif di kelas ini.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($daftarNilai as $row): ?>
                            <tr class="hover:bg-surface-bright transition-colors">
                                <td class="p-4 font-medium text-text-main"><?= h($row['nama_lengkap']) ?><br><span class="text-label-md text-text-muted font-normal"><?= h($row['nis']) ?></span></td>
                                <td class="p-4 text-center text-text-muted"><?= $row['tugas_1'] ?? '-' ?></td>
                                <td class="p-4 text-center text-text-muted"><?= $row['tugas_2'] ?? '-' ?></td>
                                <td class="p-4 text-center text-text-muted"><?= $row['tugas_3'] ?? '-' ?></td>
                                <td class="p-4 text-center text-text-muted"><?= $row['uts'] ?? '-' ?></td>
                                <td class="p-4 text-center text-text-muted"><?= $row['uas'] ?? '-' ?></td>
                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 rounded font-bold <?= warnaBadgeNilai($row['nilai_akhir']) ?>"><?= $row['nilai_akhir'] ?? '-' ?></span>
                                </td>
                                <td class="p-4 text-center">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['edit_siswa' => $row['siswa_id']])) ?>" class="text-primary hover:underline text-label-md font-label-md">Input Nilai</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- ============================================================= -->
<!-- MODAL: INPUT / EDIT NILAI                                     -->
<!-- ============================================================= -->
<div class="fixed inset-0 z-[60] <?= $editData ? '' : 'hidden' ?> flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="window.location.href='rekap_nilai.php?<?= http_build_query(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) ?>'"></div>
    <div class="relative bg-surface-white rounded-xl shadow-[0px_8px_32px_rgba(0,0,0,0.12)] w-full max-w-md">
        <?php if ($editData): ?>
        <form action="../actions/profil/nilai_simpan.php" method="post" class="p-lg">
            <input type="hidden" name="siswa_id" value="<?= (int)$editData['siswa_id'] ?>">
            <input type="hidden" name="mapel_id" value="<?= (int)$mapelId ?>">
            <input type="hidden" name="kelas_id" value="<?= (int)$kelasId ?>">
            <input type="hidden" name="semester" value="<?= h($semester) ?>">
            <input type="hidden" name="tahun_ajaran" value="<?= h($tahunAjaran) ?>">

            <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-4">
                <h3 class="text-headline-sm font-headline-sm text-text-main">Input Nilai: <?= h($editData['nama_lengkap']) ?></h3>
                <a href="rekap_nilai.php?<?= http_build_query(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) ?>" class="text-text-muted hover:text-error"><span class="material-symbols-outlined">close</span></a>
            </div>
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div><label class="text-label-md text-text-muted block mb-1">Tugas 1</label><input type="number" min="0" max="100" name="tugas_1" value="<?= h((string)($editData['tugas_1'] ?? '')) ?>" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"></div>
                <div><label class="text-label-md text-text-muted block mb-1">Tugas 2</label><input type="number" min="0" max="100" name="tugas_2" value="<?= h((string)($editData['tugas_2'] ?? '')) ?>" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"></div>
                <div><label class="text-label-md text-text-muted block mb-1">Tugas 3</label><input type="number" min="0" max="100" name="tugas_3" value="<?= h((string)($editData['tugas_3'] ?? '')) ?>" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"></div>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div><label class="text-label-md text-text-muted block mb-1">UTS</label><input type="number" min="0" max="100" name="uts" value="<?= h((string)($editData['uts'] ?? '')) ?>" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"></div>
                <div><label class="text-label-md text-text-muted block mb-1">UAS</label><input type="number" min="0" max="100" name="uas" value="<?= h((string)($editData['uas'] ?? '')) ?>" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"></div>
            </div>
            <div class="mb-2">
                <label class="text-label-md text-text-muted block mb-1">Catatan (opsional)</label>
                <input name="catatan" value="<?= h($editData['catatan'] ?? '') ?>" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-body-sm focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Contoh: Perlu remedial UTS">
            </div>
            <p class="text-label-md text-text-muted mb-4">Nilai Akhir = 30% rata-rata tugas + 30% UTS + 40% UAS.</p>
            <div class="flex justify-end gap-3 pt-4 border-t border-outline-variant">
                <a href="rekap_nilai.php?<?= http_build_query(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) ?>" class="px-5 py-2 rounded-lg text-label-lg font-label-lg text-text-muted hover:bg-surface-container-low transition-colors">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg text-label-lg font-label-lg bg-primary text-white hover:bg-primary/90 transition-colors shadow-sm">Simpan Nilai</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL: SPREADSHEET PREVIEW (Excel / Google Sheets Style)       -->
<!-- ============================================================= -->
<div id="spreadsheet-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-3 md:p-6 bg-slate-900/60 backdrop-blur-sm">
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[92vh] flex flex-col border border-slate-300 overflow-hidden">
        
        <!-- Header Bar Excel / Spreadsheet Theme -->
        <div class="bg-[#107c41] text-white px-5 py-3 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[20px] text-white">grid_on</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm leading-tight">
                        Pratinjau Spreadsheet: Rekap_Nilai_<?= h($namaKelasAktif) ?>_<?= h($namaMapelAktif) ?>.xlsx
                    </h3>
                    <p class="text-[11px] text-white/80 font-mono">
                        Semester <?= h($semester) ?> • Tahun Ajaran <?= h($tahunAjaran) ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="export_nilai.php?<?= http_build_query(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) ?>"
                   class="px-4 py-1.5 rounded-lg bg-white text-[#107c41] hover:bg-slate-100 text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">download</span> Unduh Excel (CSV)
                </a>
                <button type="button" onclick="closeSpreadsheetPreview()" class="text-white/80 hover:text-white p-1 rounded-lg hover:bg-white/10 transition-colors">
                    <span class="material-symbols-outlined text-[22px]">close</span>
                </button>
            </div>
        </div>

        <!-- Formula / Toolbar Bar -->
        <div class="bg-slate-100 border-b border-slate-300 px-4 py-2 flex items-center gap-3 text-xs text-slate-600 flex-shrink-0">
            <span class="font-mono font-bold bg-white px-2 py-1 rounded border border-slate-300 text-slate-800 text-[11px]">fx</span>
            <div class="flex-1 bg-white px-3 py-1 rounded border border-slate-300 font-mono text-[12px] text-slate-700 truncate">
                =ROUND(AVERAGE(D4:F4)*0.3 + G4*0.3 + H4*0.4, 1)
            </div>
            <div class="hidden sm:flex items-center gap-1.5 text-[11px] text-slate-600 font-medium border-l border-slate-300 pl-3">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span> Mode Pratinjau Spreadsheet
            </div>
        </div>

        <!-- Grid Body Table (Scrollable Excel Sheet) -->
        <div class="flex-1 overflow-auto p-4 bg-slate-200/50">
            <div class="bg-white rounded-xl shadow-xs border border-slate-300 overflow-hidden min-w-[850px]">
                <table class="w-full text-xs text-left border-collapse font-sans">
                    <!-- Column Header A, B, C... -->
                    <thead>
                        <tr class="bg-slate-200/80 text-slate-600 text-center font-mono font-bold text-[11px] border-b border-slate-300 select-none">
                            <th class="w-10 px-2 py-1.5 bg-slate-300/70 border-r border-slate-300">#</th>
                            <th class="w-24 px-3 py-1.5 border-r border-slate-300">A</th>
                            <th class="w-28 px-3 py-1.5 border-r border-slate-300">B</th>
                            <th class="px-4 py-1.5 border-r border-slate-300 text-left">C</th>
                            <th class="w-16 px-2 py-1.5 border-r border-slate-300">D</th>
                            <th class="w-16 px-2 py-1.5 border-r border-slate-300">E</th>
                            <th class="w-16 px-2 py-1.5 border-r border-slate-300">F</th>
                            <th class="w-16 px-2 py-1.5 border-r border-slate-300">G</th>
                            <th class="w-16 px-2 py-1.5 border-r border-slate-300">H</th>
                            <th class="w-20 px-2 py-1.5 border-r border-slate-300">I</th>
                            <th class="px-3 py-1.5 text-left">J</th>
                        </tr>
                        <!-- Document Title Row -->
                        <tr class="bg-emerald-50 text-emerald-900 border-b border-slate-300 font-bold">
                            <td class="text-center bg-slate-100 text-slate-500 font-mono border-r border-slate-300 py-2">1</td>
                            <td colspan="10" class="px-4 py-2 text-sm">
                                REKAP NILAI SISWA — <?= mb_strtoupper(h($namaMapelAktif)) ?> (<?= h($namaKelasAktif) ?>) — SEMESTER <?= mb_strtoupper(h($semester)) ?> <?= h($tahunAjaran) ?>
                            </td>
                        </tr>
                        <!-- Empty Spacer Row -->
                        <tr class="bg-white border-b border-slate-200">
                            <td class="text-center bg-slate-100 text-slate-500 font-mono border-r border-slate-300 py-1">2</td>
                            <td colspan="10" class="py-1"></td>
                        </tr>
                        <!-- Sheet Data Header Row -->
                        <tr class="bg-slate-100 font-bold text-slate-800 border-b-2 border-slate-400">
                            <td class="text-center bg-slate-200/80 text-slate-500 font-mono border-r border-slate-300 py-2">3</td>
                            <td class="px-3 py-2 border-r border-slate-300 font-mono">NIS</td>
                            <td class="px-3 py-2 border-r border-slate-300 font-mono">NISN</td>
                            <td class="px-4 py-2 border-r border-slate-300">Nama Lengkap</td>
                            <td class="px-2 py-2 border-r border-slate-300 text-center">Tugas 1</td>
                            <td class="px-2 py-2 border-r border-slate-300 text-center">Tugas 2</td>
                            <td class="px-2 py-2 border-r border-slate-300 text-center">Tugas 3</td>
                            <td class="px-2 py-2 border-r border-slate-300 text-center">UTS</td>
                            <td class="px-2 py-2 border-r border-slate-300 text-center">UAS</td>
                            <td class="px-2 py-2 border-r border-slate-300 text-center bg-emerald-100/80 text-emerald-900">Nilai Akhir</td>
                            <td class="px-3 py-2">Catatan</td>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-slate-800">
                        <?php if (empty($daftarNilai)): ?>
                            <tr>
                                <td class="text-center bg-slate-100 text-slate-500 font-mono border-r border-slate-300 py-4">4</td>
                                <td colspan="10" class="p-4 text-center text-slate-500 italic">Tidak ada data nilai untuk ditampilkan.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($daftarNilai as $idx => $row): ?>
                            <tr class="hover:bg-amber-50/60 transition-colors">
                                <td class="text-center bg-slate-100 text-slate-500 font-mono border-r border-slate-300 py-2"><?= $idx + 4 ?></td>
                                <td class="px-3 py-2 border-r border-slate-200 font-mono text-slate-600"><?= h($row['nis']) ?></td>
                                <td class="px-3 py-2 border-r border-slate-200 font-mono text-slate-600"><?= h($row['nisn'] ?? '-') ?></td>
                                <td class="px-4 py-2 border-r border-slate-200 font-semibold text-slate-900"><?= h($row['nama_lengkap']) ?></td>
                                <td class="px-2 py-2 border-r border-slate-200 text-center font-mono"><?= $row['tugas_1'] ?? '-' ?></td>
                                <td class="px-2 py-2 border-r border-slate-200 text-center font-mono"><?= $row['tugas_2'] ?? '-' ?></td>
                                <td class="px-2 py-2 border-r border-slate-200 text-center font-mono"><?= $row['tugas_3'] ?? '-' ?></td>
                                <td class="px-2 py-2 border-r border-slate-200 text-center font-mono"><?= $row['uts'] ?? '-' ?></td>
                                <td class="px-2 py-2 border-r border-slate-200 text-center font-mono"><?= $row['uas'] ?? '-' ?></td>
                                <td class="px-2 py-2 border-r border-slate-200 text-center font-mono font-bold bg-emerald-50/70 text-emerald-800">
                                    <?= $row['nilai_akhir'] ?? '-' ?>
                                </td>
                                <td class="px-3 py-2 text-slate-600 italic"><?= h($row['catatan'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Row Summary Statistics -->
                        <?php $rowStatIdx = count($daftarNilai) + 4; ?>
                        <tr class="bg-slate-100 font-bold border-t-2 border-slate-300">
                            <td class="text-center bg-slate-200 text-slate-500 font-mono border-r border-slate-300 py-2"><?= $rowStatIdx ?></td>
                            <td colspan="8" class="px-4 py-2 border-r border-slate-300 text-right text-slate-700 uppercase tracking-wide text-[11px]">Rata-rata Kelas:</td>
                            <td class="px-2 py-2 border-r border-slate-300 text-center font-mono font-extrabold text-emerald-800 bg-emerald-100/90"><?= $rataKelas ?? '-' ?></td>
                            <td class="px-3 py-2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer Bar Spreadsheet -->
        <div class="bg-slate-100 border-t border-slate-300 px-5 py-2.5 flex items-center justify-between flex-shrink-0 text-xs">
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 bg-white border border-slate-300 rounded font-semibold text-emerald-800 shadow-2xs flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-emerald-600">table</span> Sheet1
                </span>
                <span class="text-slate-500 text-[11px] font-mono"><?= count($daftarNilai) ?> baris data siswa</span>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="closeSpreadsheetPreview()" class="px-4 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-200 text-slate-700 font-semibold transition-colors">
                    Tutup Pratinjau
                </button>
                <a href="export_nilai.php?<?= http_build_query(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran, 'format' => 'excel']) ?>"
                   class="px-5 py-1.5 rounded-lg bg-[#107c41] hover:bg-[#0b5c30] text-white font-bold transition-colors flex items-center gap-2 shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">download</span> Unduh Excel (.xls)
                </a>
            </div>
        </div>

    </div>
</div>

<script>
function openSpreadsheetPreview() {
    document.getElementById('spreadsheet-modal').classList.remove('hidden');
}
function closeSpreadsheetPreview() {
    document.getElementById('spreadsheet-modal').classList.add('hidden');
}
</script>

