<?php
/**
 * API Nilai Siswa
 * GET /api/nilai.php
 * Header Authorization: Bearer <token>
 * Query opsional: ?semester=Ganjil&tahun_ajaran=2024/2025
 * Response: nilai per mata pelajaran + ringkasan (rata-rata, kumulatif)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

$siswa = requireAuth();

$semester    = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? ($siswa['tahun_ajaran'] ?? '2024/2025');

$stmt = $pdo->prepare("SELECT n.id, n.mapel_id, n.semester, n.tahun_ajaran,
                              n.tugas_1, n.tugas_2, n.tugas_3, n.uts, n.uas, n.catatan,
                              mp.nama_mapel, mp.kode_mapel
                       FROM nilai n
                       JOIN mata_pelajaran mp ON mp.id = n.mapel_id
                       WHERE n.siswa_id = ? AND n.semester = ? AND n.tahun_ajaran = ?
                       ORDER BY mp.nama_mapel");
$stmt->execute([$siswa['id'], $semester, $tahunAjaran]);
$rows = $stmt->fetchAll();

$daftarNilai = [];
$totalNilaiAkhir = [];
foreach ($rows as $r) {
    $nilaiAkhir = hitungNilaiAkhir($r['tugas_1'], $r['tugas_2'], $r['tugas_3'], $r['uts'], $r['uas']);
    if ($nilaiAkhir !== null) {
        $totalNilaiAkhir[] = $nilaiAkhir;
    }
    $daftarNilai[] = [
        'id' => (int)$r['id'],
        'mapel_id' => (int)$r['mapel_id'],
        'nama_mapel' => $r['nama_mapel'],
        'kode_mapel' => $r['kode_mapel'],
        'tugas_1' => $r['tugas_1'] !== null ? (float)$r['tugas_1'] : null,
        'tugas_2' => $r['tugas_2'] !== null ? (float)$r['tugas_2'] : null,
        'tugas_3' => $r['tugas_3'] !== null ? (float)$r['tugas_3'] : null,
        'uts' => $r['uts'] !== null ? (float)$r['uts'] : null,
        'uas' => $r['uas'] !== null ? (float)$r['uas'] : null,
        'nilai_akhir' => $nilaiAkhir,
        'catatan' => $r['catatan'],
    ];
}

$rataRata = count($totalNilaiAkhir) > 0 ? round(array_sum($totalNilaiAkhir) / count($totalNilaiAkhir), 1) : null;

json_out([
    'success' => true,
    'data' => [
        'semester' => $semester,
        'tahun_ajaran' => $tahunAjaran,
        'rata_rata' => $rataRata,
        'jumlah_mapel' => count($daftarNilai),
        'daftar_nilai' => $daftarNilai,
    ],
]);

