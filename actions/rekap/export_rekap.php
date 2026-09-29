<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

// Pastikan tidak ada output sebelum header
ob_clean();

$format = $_GET['format'] ?? 'excel';
$semester = $_GET['semester'] ?? '1';
$bulan = $_GET['bulan'] ?? 'all';

$user = currentUser();
$isAdmin = isAdmin();

// Ambil kelas yang sesuai
$kelasId = null;
$namaKelas = '';
$namaWaliKelas = '';

if ($isAdmin) {
    $kelas = $pdo->query("SELECT k.id, k.nama_kelas, u.nama_lengkap as wali_nama 
                          FROM kelas k 
                          LEFT JOIN users u ON k.wali_kelas_id = u.id 
                          ORDER BY k.tingkat, k.nama_kelas LIMIT 1")->fetch();
} else {
    $stmtWali = $pdo->prepare("SELECT k.id, k.nama_kelas, u.nama_lengkap as wali_nama 
                               FROM kelas k 
                               LEFT JOIN users u ON k.wali_kelas_id = u.id 
                               WHERE k.wali_kelas_id = ? LIMIT 1");
    $stmtWali->execute([$user['id']]);
    $kelas = $stmtWali->fetch();
}

if ($kelas) {
    $kelasId = $kelas['id'];
    $namaKelas = $kelas['nama_kelas'];
    $namaWaliKelas = $kelas['wali_nama'] ?? 'Wali Kelas';
}

if (!$kelasId) {
    die("Anda bukan wali kelas. Akses ditolak.");
}

$stmt = $pdo->prepare("SELECT nama_lengkap FROM siswa WHERE kelas_id = ? AND status = 'aktif' ORDER BY nama_lengkap ASC");
$stmt->execute([$kelasId]);
$students = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Dummy data for presentation if empty
if (empty($students)) {
    $students = [
        'AHMAD HUSAIN DEEDAT',
        'AISYAH DEDE JULIANTI',
        'ALLAM MUYASSAR',
        'ARDHIONA SAVA AMELIA',
        'CITRA ZILVANA SATYA MADANI'
    ];
}

$bulanLabel = $bulan === 'all' ? 'Satu Semester' : ($bulan == 1 ? 'Januari' : ($bulan == 2 ? 'Februari' : 'Maret'));
$semesterLabel = $semester == 1 ? 'I (satu)' : 'II (dua)';

// ==========================================
// EXPORT EXCEL (HTML as XLS)
// ==========================================
if ($format === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Rekap_Presensi_" . str_replace(' ', '_', $namaKelas) . ".xls");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8"></head>';
    echo '<body>';
    
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    
    // Header
    echo '<tr>';
    echo '<th colspan="10" style="font-size: 16px; text-align: center;"><b>REKAPITULASI PROFIL MURID</b></th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th colspan="10" style="text-align: center;">Kelas: ' . htmlspecialchars($namaKelas) . ' | Semester: ' . htmlspecialchars($semesterLabel) . ' | Bulan: ' . htmlspecialchars($bulanLabel) . '</th>';
    echo '</tr>';
    
    echo '<tr><td colspan="10"></td></tr>'; // Spacer
    
    // Table Headers
    echo '<tr>';
    echo '<th rowspan="2" style="background-color: #f2f2f2;">No</th>';
    echo '<th rowspan="2" style="background-color: #f2f2f2;">Nama Siswa</th>';
    echo '<th colspan="5" style="background-color: #f2f2f2;">Jumlah</th>';
    echo '<th colspan="2" style="background-color: #f2f2f2;">Sumber Data</th>';
    echo '<th rowspan="2" style="background-color: #f2f2f2;">Poin</th>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th style="background-color: #f2f2f2;">H</th>';
    echo '<th style="background-color: #f2f2f2;">S</th>';
    echo '<th style="background-color: #f2f2f2;">I</th>';
    echo '<th style="background-color: #f2f2f2;">T</th>';
    echo '<th style="background-color: #f2f2f2;">A</th>';
    echo '<th style="background-color: #f2f2f2;">WALAS</th>';
    echo '<th style="background-color: #f2f2f2;">BK</th>';
    echo '</tr>';
    
    // Table Data
    foreach ($students as $index => $name) {
        echo '<tr>';
        echo '<td style="text-align: center;">' . ($index + 1) . '</td>';
        echo '<td>' . htmlspecialchars(strtoupper($name)) . '</td>';
        echo '<td style="text-align: center;">0</td>';
        echo '<td style="text-align: center;">0</td>';
        echo '<td style="text-align: center;">0</td>';
        echo '<td style="text-align: center;">0</td>';
        echo '<td style="text-align: center;">0</td>';
        echo '<td style="text-align: center;">0</td>';
        echo '<td style="text-align: center;">0</td>';
        echo '<td style="text-align: center;">0</td>';
        echo '</tr>';
    }
    
    echo '<tr><td colspan="10"></td></tr>'; // Spacer
    echo '<tr><td colspan="10"></td></tr>'; // Spacer
    
    // Signatures
    echo '<tr>';
    echo '<td colspan="2" style="text-align: center; border:none;">Mengetahui,</td>';
    echo '<td colspan="5" style="border:none;"></td>';
    echo '<td colspan="3" style="text-align: center; border:none;">Bandung, ' . date('d F Y') . '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="2" style="text-align: center; border:none;">Kepala Sekolah</td>';
    echo '<td colspan="5" style="border:none;"></td>';
    echo '<td colspan="3" style="text-align: center; border:none;">Wali Kelas</td>';
    echo '</tr>';
    
    echo '<tr><td colspan="10" style="border:none; height: 60px;"></td></tr>'; // Signature space
    
    echo '<tr>';
    echo '<td colspan="2" style="text-align: center; border:none;"><b><u>H. Junaedi, M.Pd</u></b></td>';
    echo '<td colspan="5" style="border:none;"></td>';
    echo '<td colspan="3" style="text-align: center; border:none;"><b><u>' . htmlspecialchars($namaWaliKelas) . '</u></b></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="2" style="text-align: center; border:none;">NIP. 19700101 199512 1 001</td>';
    echo '<td colspan="5" style="border:none;"></td>';
    echo '<td colspan="3" style="text-align: center; border:none;">NIP. -</td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</body></html>';
    exit;
}

// ==========================================
// EXPORT PDF (HTML Printable)
// ==========================================
if ($format === 'pdf') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Rekap Presensi - <?= htmlspecialchars($namaKelas) ?></title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                font-size: 11pt; 
                line-height: 1.4;
                color: #000;
                margin: 0;
                padding: 20px 40px;
            }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { font-size: 14pt; margin: 0 0 5px 0; }
            .header p { margin: 0; font-size: 11pt; }
            
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #000; padding: 6px 8px; }
            th { background-color: #f2f2f2; text-align: center; font-weight: bold; }
            .text-center { text-align: center; }
            
            .signatures { 
                width: 100%; 
                margin-top: 50px; 
                border: none;
            }
            .signatures td { 
                border: none; 
                text-align: center; 
                width: 50%;
                vertical-align: bottom;
                padding: 0;
            }
            .sig-space { height: 80px; }
            
            @media print {
                body { padding: 0; }
                @page { margin: 1.5cm; }
                button.print-btn { display: none; }
            }
            
            button.print-btn {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #3b82f6;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-weight: bold;
                cursor: pointer;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            }
            button.print-btn:hover { background: #2563eb; }
        </style>
    </head>
    <body onload="window.print()">
        <button class="print-btn" onclick="window.print()">Cetak PDF</button>
        
        <div class="header">
            <h1>REKAPITULASI PROFIL MURID</h1>
            <p>Kelas: <?= htmlspecialchars($namaKelas) ?> | Semester: <?= htmlspecialchars($semesterLabel) ?> | Bulan: <?= htmlspecialchars($bulanLabel) ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width: 5%">No</th>
                    <th rowspan="2" style="width: 35%">Nama Siswa</th>
                    <th colspan="5">Jumlah</th>
                    <th colspan="2">Sumber Data</th>
                    <th rowspan="2" style="width: 8%">Poin</th>
                </tr>
                <tr>
                    <th style="width: 6%">H</th>
                    <th style="width: 6%">S</th>
                    <th style="width: 6%">I</th>
                    <th style="width: 6%">T</th>
                    <th style="width: 6%">A</th>
                    <th style="width: 10%">WALAS</th>
                    <th style="width: 10%">BK</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $index => $name): ?>
                <tr>
                    <td class="text-center"><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars(strtoupper($name)) ?></td>
                    <td class="text-center">0</td>
                    <td class="text-center">0</td>
                    <td class="text-center">0</td>
                    <td class="text-center">0</td>
                    <td class="text-center">0</td>
                    <td class="text-center">0</td>
                    <td class="text-center">0</td>
                    <td class="text-center">0</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <table class="signatures">
            <tr>
                <td>
                    Mengetahui,<br>
                    Kepala Sekolah
                    <div class="sig-space"></div>
                    <b><u>H. Junaedi, M.Pd</u></b><br>
                    NIP. 19700101 199512 1 001
                </td>
                <td>
                    Bandung, <?= date('d F Y') ?><br>
                    Wali Kelas
                    <div class="sig-space"></div>
                    <b><u><?= htmlspecialchars($namaWaliKelas) ?></u></b><br>
                    NIP. -
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    exit;
}
