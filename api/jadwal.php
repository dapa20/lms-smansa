<?php
/**
 * API Jadwal Pelajaran Siswa
 * GET /api/jadwal.php
 * Header Authorization: Bearer <token>
 * Query opsional: ?hari=Senin (filter per hari)
 * Response: jadwal berdasarkan kelas siswa
 */
require_once __DIR__ . '/config.php';

$siswa = requireAuth();
$kelasId = (int)$siswa['kelas_id'];

$hari = $_GET['hari'] ?? '';

$sql = "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, j.ruang, j.jenis, j.keterangan,
               mp.id AS mapel_id, mp.kode_mapel, mp.nama_mapel,
               u.nama_lengkap AS nama_guru
        FROM jadwal_mengajar j
        JOIN mata_pelajaran mp ON mp.id = j.mapel_id
        JOIN users u ON u.id = j.guru_id
        WHERE j.kelas_id = ? AND j.jenis = 'reguler'";
$params = [$kelasId];

if ($hari !== '') {
    $sql .= " AND j.hari = ?";
    $params[] = $hari;
}

// Urutkan berdasarkan urutan hari & jam
$sql .= " ORDER BY FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jadwal = $stmt->fetchAll();

foreach ($jadwal as &$j) {
    $j['id'] = (int)$j['id'];
    $j['mapel_id'] = (int)$j['mapel_id'];
    // jam_mulai/jam_selesai berupa TIME -> potong jam:menit
    $j['jam_mulai'] = substr($j['jam_mulai'], 0, 5);
    $j['jam_selesai'] = substr($j['jam_selesai'], 0, 5);
}
unset($j);

json_out([
    'success' => true,
    'data' => [
        'nama_kelas' => $siswa['nama_kelas'] ?? '-',
        'jadwal' => $jadwal,
    ],
]);
