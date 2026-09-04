<?php
/**
 * API Kalender Akademik Siswa (Realtime)
 * GET /api/kalender.php
 * Header Authorization: Bearer <token>
 *
 * Response: daftar event kalender yang digabung dari:
 *   1. Jadwal ujian/rapat (tabel jadwal_mengajar, kolom tanggal terisi)
 *   2. Deadline tugas & ujian (tabel tugas_ujian)
 *   3. Hari libur nasional Indonesia (fallback statis dalam kode)
 * Data otomatis mengikuti tahun berjalan (2026).
 */
require_once __DIR__ . '/config.php';

$siswa = requireAuth();
$kelasId = (int)$siswa['kelas_id'];
$tingkat = $siswa['tingkat'] ?? 'XII';

$events = [];

// ─── 1. Jadwal berjenis ujian/rapat/lainnya untuk kelas siswa ───────
$stmt = $pdo->prepare(
    "SELECT j.id, j.tanggal, j.jam_mulai, j.jam_selesai, j.jenis, j.keterangan,
            mp.nama_mapel
     FROM jadwal_mengajar j
     LEFT JOIN mata_pelajaran mp ON mp.id = j.mapel_id
     WHERE j.kelas_id = ?
       AND j.jenis != 'reguler'
       AND j.tanggal IS NOT NULL
       AND YEAR(j.tanggal) = YEAR(CURDATE())
     ORDER BY j.tanggal ASC"
);
$stmt->execute([$kelasId]);
foreach ($stmt->fetchAll() as $r) {
    $events[] = [
        'id'               => 'j' . $r['id'],
        'tanggal_mulai'    => $r['tanggal'],
        'tanggal_selesai'  => $r['tanggal'],
        'judul'            => $r['keterangan'] ?: (($r['nama_mapel'] ? $r['nama_mapel'] . ' — ' : '') . ucfirst($r['jenis'])),
        'kategori'         => $r['jenis'] === 'ujian' ? 'akademik' : 'kegiatan',
        'waktu'            => substr($r['jam_mulai'], 0, 5) . ' - ' . substr($r['jam_selesai'], 0, 5) . ' WIB',
        'lokasi'           => 'Kelas ' . ($siswa['nama_kelas'] ?? '-'),
        'deskripsi'        => null,
        'sumber'           => 'jadwal',
    ];
}

// ─── 2. Deadline tugas & ujian untuk kelas siswa ─────────────────────
$stmt = $pdo->prepare(
    "SELECT t.id, t.judul, t.jenis, t.tanggal_deadline, mp.nama_mapel
     FROM tugas_ujian t
     JOIN mata_pelajaran mp ON mp.id = t.mapel_id
     WHERE t.kelas_id = ?
       AND YEAR(t.tanggal_deadline) = YEAR(CURDATE())
       AND t.status = 'aktif'
     ORDER BY t.tanggal_deadline ASC"
);
$stmt->execute([$kelasId]);
foreach ($stmt->fetchAll() as $r) {
    $tanggal = substr($r['tanggal_deadline'], 0, 10);
    $events[] = [
        'id'               => 't' . $r['id'],
        'tanggal_mulai'    => $tanggal,
        'tanggal_selesai'  => $tanggal,
        'judul'            => 'Deadline: ' . $r['judul'],
        'kategori'         => $r['jenis'] === 'tugas' ? 'kegiatan' : 'akademik',
        'waktu'            => substr($r['tanggal_deadline'], 11, 5) . ' WIB',
        'lokasi'           => $r['nama_mapel'],
        'deskripsi'        => 'Tenggat pengumpulan: ' . $r['tanggal_deadline'],
        'sumber'           => 'tugas',
    ];
}

// ─── 3. Hari libur nasional Indonesia 2026 (fallback statis) ────────
$liburNasional = [
    '2026-01-01' => ['judul' => 'Tahun Baru 2026',                'kategori' => 'libur'],
    '2026-03-19' => ['judul' => 'Isra Miraj 1447 H',              'kategori' => 'libur'],
    '2026-03-31' => ['judul' => 'Hari Raya Nyepi (Saka 1948)',    'kategori' => 'libur'],
    '2026-04-03' => ['judul' => 'Wafat Isa Almasih',              'kategori' => 'libur'],
    '2026-04-17' => ['judul' => 'Idulfitri 1447 H',               'kategori' => 'libur'],
    '2026-04-18' => ['judul' => 'Idulfitri 1447 H',               'kategori' => 'libur'],
    '2026-05-01' => ['judul' => 'Hari Buruh Internasional',       'kategori' => 'libur'],
    '2026-05-21' => ['judul' => 'Kenaikan Isa Almasih',           'kategori' => 'libur'],
    '2026-05-28' => ['judul' => 'Idul Adha 1447 H',               'kategori' => 'libur'],
    '2026-06-01' => ['judul' => 'Hari Lahir Pancasila',           'kategori' => 'libur'],
    '2026-06-17' => ['judul' => 'Tahun Baru Islam 1448 H',        'kategori' => 'libur'],
    '2026-08-17' => ['judul' => 'HUT Kemerdekaan RI ke-81',       'kategori' => 'libur'],
    '2026-08-26' => ['judul' => 'Maulid Nabi Muhammad SAW',       'kategori' => 'libur'],
    '2026-12-25' => ['judul' => 'Hari Raya Natal',                'kategori' => 'libur'],
];

$tahunIni = date('Y');
foreach ($liburNasional as $tgl => $ev) {
    if (substr($tgl, 0, 4) === $tahunIni) {
        $events[] = [
            'id'               => 'h' . $tgl,
            'tanggal_mulai'    => $tgl,
            'tanggal_selesai'  => $tgl,
            'judul'            => $ev['judul'],
            'kategori'         => $ev['kategori'],
            'waktu'            => null,
            'lokasi'           => 'Libur Nasional',
            'deskripsi'        => null,
            'sumber'           => 'libur',
        ];
    }
}

// Urutkan event berdasarkan tanggal mulai
usort($events, function ($a, $b) {
    return strcmp($a['tanggal_mulai'], $b['tanggal_mulai']);
});

json_out([
    'success' => true,
    'data' => [
        'tahun_ajaran' => $siswa['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1)),
        'nama_kelas'   => $siswa['nama_kelas'] ?? '-',
        'semester'     => date('n') <= 6 ? 'Genap' : 'Ganjil',
        'events'       => $events,
    ],
]);

