<?php
/**
 * API Kehadiran Siswa
 * GET /api/kehadiran.php
 * Header Authorization: Bearer <token>
 * Query opsional: ?bulan=2025-06 (format YYYY-MM), ?limit=30
 * Response: riwayat kehadiran + ringkasan persentase
 */
require_once __DIR__ . '/config.php';

$siswa = requireAuth();

$bulan = $_GET['bulan'] ?? '';
$limit = (int)($_GET['limit'] ?? 30);

$sql = "SELECT tanggal, status, dicatat_oleh FROM kehadiran WHERE siswa_id = ?";
$params = [$siswa['id']];

if ($bulan !== '' && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $sql .= " AND DATE_FORMAT(tanggal, '%Y-%m') = ?";
    $params[] = $bulan;
}

$sql .= " ORDER BY tanggal DESC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

// Ringkasan
$total = count($riwayat);
$counts = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
foreach ($riwayat as $r) {
    $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
}
$persenHadir = $total > 0 ? round(($counts['hadir'] / $total) * 100, 1) : 0.0;

// Hitung bulan terakhir (30 hari) untuk konsistensi dashboard
$stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'hadir') AS hadir
                       FROM kehadiran WHERE siswa_id = ? AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$siswa['id']]);
$absen30 = $stmt->fetch();
$persen30 = ($absen30 && $absen30['total'] > 0) ? round(($absen30['hadir'] / $absen30['total']) * 100, 1) : 0.0;

foreach ($riwayat as &$r) {
    $r['tanggal'] = isoDate($r['tanggal'] . ' 00:00:00');
}
unset($r);

json_out([
    'success' => true,
    'data' => [
        'ringkasan' => [
            'total' => $total,
            'hadir' => $counts['hadir'],
            'izin' => $counts['izin'],
            'sakit' => $counts['sakit'],
            'alpa' => $counts['alpa'],
            'persen_hadir' => $persenHadir,
            'persen_hadir_30_hari' => $persen30,
        ],
        'riwayat' => $riwayat,
    ],
]);

