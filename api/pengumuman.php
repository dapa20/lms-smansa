<?php
/**
 * API Pengumuman
 * GET /api/pengumuman.php
 * Header Authorization: Bearer <token>
 * Query opsional: ?kategori=penting|informasi
 */
require_once __DIR__ . '/config.php';

requireAuth(); // wajib login

$kategori = $_GET['kategori'] ?? '';

$sql = "SELECT p.id, p.judul, p.isi, p.kategori, p.created_at,
               u.nama_lengkap AS pembuat
        FROM pengumuman p
        JOIN users u ON u.id = p.dibuat_oleh";
$params = [];
if ($kategori !== '') {
    $sql .= " WHERE p.kategori = ?";
    $params[] = $kategori;
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pengumuman = $stmt->fetchAll();

foreach ($pengumuman as &$p) {
    $p['id'] = (int)$p['id'];
    $p['created_at'] = isoDate($p['created_at']);
}
unset($p);

json_out(['success' => true, 'data' => $pengumuman]);

