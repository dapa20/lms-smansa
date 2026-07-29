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
        <?php if (!empty($_GET['error'])): ?>
            <div class="mb-6 p-4 rounded-xl bg-error-container text-on-error-container border border-error/20 flex items-start gap-3 shadow-xs">
                <span class="material-symbols-outlined text-error flex-shrink-0 mt-0.5">error</span>
                <div class="text-body-sm font-medium">
                    <?= h($_GET['error']) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-lg gap-4">
            <div>
                <h2 class="text-headline-lg font-headline-lg font-semibold text-text-main mb-1">Rekap Nilai</h2>
                <p class="text-body-md font-body-md text-text-muted">Pantau dan kelola nilai akhir siswa per mata pelajaran.</p>
            </div>
            <?php if ($kelasId && $mapelId): ?>
                <div class="flex flex-wrap gap-2">
                    <a href="../actions/nilai/export_to_gsheets.php?<?= http_build_query(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) ?>"
                       target="_blank"
                       class="bg-[#0f9d58] hover:bg-[#0b8043] text-white px-5 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm"
                       title="Buat Google Spreadsheet baru dengan data nilai sudah terisi">
                        <svg class="w-5 h-5 fill-current flex-shrink-0" viewBox="0 0 24 24"><path d="M11.318 12.545H7.91v-1.909h3.41v1.91zm4.26 0h-3.41v-1.909h3.41v1.91zm0 3.273h-3.41V13.91h3.41v1.91zm-4.26 0H7.91V13.91h3.41v1.91zM20.727 6h-5.455V4.364A.364.364 0 0 0 14.91 4H9.09a.364.364 0 0 0-.363.364V6H3.273A1.273 1.273 0 0 0 2 7.273v12.454A1.273 1.273 0 0 0 3.273 21h17.454A1.273 1.273 0 0 0 22 19.727V7.273A1.273 1.273 0 0 0 20.727 6zm-11.636-.727h5.818v.727H9.09v-.727zm10.91 14.09H4V10h16v9.363z"/></svg>
                        Buka di Google Sheets
                    </a>
                    <a href="export_nilai.php?<?= http_build_query(['kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran, 'format' => 'excel']) ?>"
                       class="bg-surface-white border border-outline-variant hover:border-primary hover:text-primary text-text-main px-5 py-2 rounded-lg text-label-lg font-label-lg flex items-center gap-2 transition-colors shadow-sm">
                        <span class="material-symbols-outlined">download</span> Unduh Excel (.xls)
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

