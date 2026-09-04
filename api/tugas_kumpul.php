<?php
/**
 * API Kumpul Tugas Siswa
 * POST /api/tugas_kumpul.php
 * Header Authorization: Bearer <token>
 * Body (form-data): { tugas_id, file_jawaban (optional, file) }
 * Atau JSON: { tugas_id, catatan }
 *
 * NOTE: Untuk upload file sungguhan perlu folder uploads/pengumpulan/
 * (disarankan). Fallback: simpan status 'terkumpul' tanpa file.
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metode tidak diizinkan. Gunakan POST.', 405);
}

$siswa = requireAuth();
$data = json_body();
$tugasId = (int)($data['tugas_id'] ?? $_POST['tugas_id'] ?? 0);

if ($tugasId <= 0) {
    json_error('tugas_id wajib diisi.');
}

// Cek tugas ada & memang untuk kelas siswa
$stmt = $pdo->prepare('SELECT * FROM tugas_ujian WHERE id = ? AND kelas_id = ?');
$stmt->execute([$tugasId, $siswa['kelas_id']]);
$tugas = $stmt->fetch();
if (!$tugas) {
    json_error('Tugas tidak ditemukan.', 404);
}

// Cek apakah sudah pernah mengumpulkan
$stmt = $pdo->prepare('SELECT * FROM pengumpulan_tugas WHERE tugas_id = ? AND siswa_id = ?');
$stmt->execute([$tugasId, $siswa['id']]);
$existing = $stmt->fetch();

$waktuKumpul = date('Y-m-d H:i:s');
$deadline = $tugas['tanggal_deadline'];
$statusKumpul = strtotime($waktuKumpul) <= strtotime($deadline) ? 'terkumpul' : 'terlambat';

// Proses file (jika dikirim)
$fileJawaban = null;
$namaFileAsli = null;
if (!empty($_FILES['file_jawaban']) && $_FILES['file_jawaban']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/pengumpulan/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $ext = strtolower(pathinfo($_FILES['file_jawaban']['name'], PATHINFO_EXTENSION));
    $namaFileBaru = 'siswa' . $siswa['id'] . '_tugas' . $tugasId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (move_uploaded_file($_FILES['file_jawaban']['tmp_name'], $uploadDir . $namaFileBaru)) {
        $fileJawaban = $namaFileBaru;
        $namaFileAsli = $_FILES['file_jawaban']['name'];
    }
}

if ($existing) {
    $stmt = $pdo->prepare("UPDATE pengumpulan_tugas
                           SET waktu_kumpul = ?, file_jawaban = COALESCE(?, file_jawaban), status = ?
                           WHERE tugas_id = ? AND siswa_id = ?");
    $stmt->execute([$waktuKumpul, $fileJawaban, $statusKumpul, $tugasId, $siswa['id']]);
} else {
    $stmt = $pdo->prepare("INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, waktu_kumpul, file_jawaban, status)
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$tugasId, $siswa['id'], $waktuKumpul, $fileJawaban, $statusKumpul]);
}

json_out([
    'success' => true,
    'message' => 'Tugas berhasil dikumpulkan.',
    'data' => [
        'tugas_id' => $tugasId,
        'status' => $statusKumpul,
        'waktu_kumpul' => isoDate($waktuKumpul),
        'file_jawaban' => $fileJawaban,
    ],
]);
