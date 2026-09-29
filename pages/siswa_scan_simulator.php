<?php
/**
 * =====================================================================
 * SIMULATOR SCAN QR SISWA (UNTUK PENGUJIAN INTEGRASI APLIKASI SISWA)
 * =====================================================================
 * Memungkinkan pengajar/pengembang mensimulasikan scan QR code dari sisi siswa:
 * 1. Memilih siswa yang akan melakukan scan
 * 2. Memasukkan / menempelkan kode QR (atau auto-fill dari sesi aktif)
 * 3. Memanggil API /api/absen_qr_scan.php dengan Bearer Token asli siswa
 * =====================================================================
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle = 'Simulator Scan QR Siswa';
$user = currentUser();

// Ambil daftar sesi QR yang sedang aktif
$sesiAktif = $pdo->query("SELECT s.*, k.nama_kelas, u.nama_lengkap AS nama_guru, m.nama_mapel
                          FROM absen_qr_sessions s
                          JOIN kelas k ON k.id = s.kelas_id
                          JOIN users u ON u.id = s.guru_id
                          LEFT JOIN mata_pelajaran m ON m.id = s.mapel_id
                          WHERE s.is_active = 1 AND s.berlaku_sampai >= NOW()
                          ORDER BY s.id DESC")->fetchAll();

// Ambil daftar siswa untuk simulasi (prioritas siswa di kelas yang sesinya aktif)
$kelasIdAktif = !empty($sesiAktif) ? (int)$sesiAktif[0]['kelas_id'] : 0;
$stmtSiswa = $pdo->prepare("SELECT s.*, k.nama_kelas 
                            FROM siswa s 
                            JOIN kelas k ON k.id = s.kelas_id
                            WHERE s.status = 'aktif'
                            ORDER BY (s.kelas_id = ?) DESC, k.nama_kelas, s.nama_lengkap
                            LIMIT 50");
$stmtSiswa->execute([$kelasIdAktif]);
$daftarSiswa = $stmtSiswa->fetchAll();

// Helper: ambil atau buat token untuk siswa terpilih
function getOrCreateSiswaToken(PDO $pdo, int $siswaId): string {
    $stmt = $pdo->prepare("SELECT token FROM siswa_tokens WHERE siswa_id = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$siswaId]);
    $token = $stmt->fetchColumn();

    if (!$token) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (30 * 86400));
        $stmtIns = $pdo->prepare("INSERT INTO siswa_tokens (siswa_id, token, expires_at) VALUES (?, ?, ?)");
        $stmtIns->execute([$siswaId, $token, $expires]);
    }
    return $token;
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/topbar.php';
?>

<main class="pt-16 md:ml-[280px] min-h-screen bg-slate-50/70 pb-16">
    <div class="p-4 sm:p-6 lg:p-8 max-w-[1100px] mx-auto space-y-6">
        
        <!-- Header -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-[24px]">phone_android</span>
                    </span>
                    <h1 class="text-xl font-bold text-slate-800">Simulator Scan QR Siswa (Mobile API)</h1>
                </div>
                <p class="text-xs text-slate-500">
                    Alat bantu pengujian untuk memverifikasi respon API <code>POST /api/absen_qr_scan.php</code> persis seperti yang dikirim oleh aplikasi Flutter siswa.
                </p>
            </div>

            <a href="<?= APP_URL ?>/pages/absen_qr.php" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition self-start sm:self-auto">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Kembali ke Halaman Guru
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            
            <!-- Left: Form Simulator -->
            <div class="md:col-span-7 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-600 text-[18px]">send</span>
                    Kirim Request Scan Siswa
                </h2>

                <div class="space-y-4">
                    <!-- Pilih Sesi Aktif Cepat -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700">Pilih Dari Sesi Aktif Saat Ini</label>
                        <?php if (empty($sesiAktif)): ?>
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px]">info</span>
                                Tidak ada sesi QR yang sedang aktif. Silakan buat sesi terlebih dahulu di <a href="<?= APP_URL ?>/pages/absen_qr.php" class="font-bold underline">Halaman Absen QR</a>.
                            </div>
                        <?php else: ?>
                            <select id="select-sesi-aktif" onchange="applySesi(this.value)" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500">
                                <option value="">-- Pilih Sesi Aktif --</option>
                                <?php foreach ($sesiAktif as $s): ?>
                                    <option value="<?= h($s['kode_qr']) ?>" data-kelas-id="<?= $s['kelas_id'] ?>">
                                        <?= h($s['kode_qr']) ?> - Kelas <?= h($s['nama_kelas']) ?> (<?= h($s['nama_mapel'] ?? 'Umum') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Kode QR String -->
                    <div class="space-y-1.5">
                        <label for="input-kode-qr" class="block text-xs font-bold text-slate-700">
                            Kode QR (Payload Hasil Scan) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" id="input-kode-qr" placeholder="Contoh: ABS-XIIMIPA1-A8F9K2"
                                   value="<?= !empty($sesiAktif) ? h($sesiAktif[0]['kode_qr']) : '' ?>"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
                            <button type="button" onclick="pasteClipboard()" class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-emerald-600" title="Paste dari Clipboard">
                                <span class="material-symbols-outlined text-[18px]">content_paste</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-400">Kode ini yang dibaca kamera HP siswa saat mengarahkan ke layar.</p>
                    </div>

                    <!-- Pilih Siswa yang Mensimulasikan Scan -->
                    <div class="space-y-1.5">
                        <label for="select-siswa" class="block text-xs font-bold text-slate-700">
                            Pilih Siswa yang Melakukan Scan <span class="text-red-500">*</span>
                        </label>
                        <select id="select-siswa" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500">
                            <?php foreach ($daftarSiswa as $sw): 
                                $token = getOrCreateSiswaToken($pdo, (int)$sw['id']);
                            ?>
                                <option value="<?= $sw['id'] ?>" data-token="<?= $token ?>" data-kelas-id="<?= $sw['kelas_id'] ?>">
                                    <?= h($sw['nama_lengkap']) ?> (Kelas: <?= h($sw['nama_kelas']) ?> | NISN: <?= h($sw['nisn']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Token Siswa (Otomatis) -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700">Token Siswa (Header: <code>Authorization: Bearer</code>)</label>
                        <input type="text" id="input-token-siswa" readonly class="w-full px-3.5 py-2 bg-slate-100 border border-slate-200 rounded-xl text-[11px] font-mono text-slate-500 select-all">
                    </div>

                    <!-- Tombol Simulasi Scan -->
                    <button type="button" id="btn-scan" onclick="submitScan()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">qr_code_scanner</span>
                        Simulasikan Siswa Scan QR Sekarang
                    </button>
                </div>
            </div>

            <!-- Right: Response JSON & Log -->
            <div class="md:col-span-5 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600 text-[18px]">terminal</span>
                        Respon API Siswa
                    </h2>
                    <span id="response-badge" class="hidden px-2.5 py-0.5 text-[10px] font-bold rounded-full"></span>
                </div>

                <div class="space-y-2">
                    <div class="text-[11px] font-semibold text-slate-500">HTTP Request:</div>
                    <code class="block p-2 bg-slate-900 text-emerald-400 font-mono text-[11px] rounded-lg overflow-x-auto">
                        POST <?= APP_URL ?>/api/absen_qr_scan.php
                    </code>
                </div>

                <div class="space-y-2">
                    <div class="text-[11px] font-semibold text-slate-500">JSON Output:</div>
                    <pre id="json-output" class="p-4 bg-slate-900 text-slate-200 font-mono text-[11px] rounded-xl overflow-x-auto h-[260px] border border-slate-800">
Tekan tombol "Simulasikan Siswa Scan QR Sekarang" untuk melihat response API...
                    </pre>
                </div>

                <div id="scan-feedback" class="hidden p-3 rounded-xl text-xs font-semibold"></div>
            </div>

        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    updateTokenField();
    document.getElementById('select-siswa').addEventListener('change', updateTokenField);
});

function updateTokenField() {
    const sel = document.getElementById('select-siswa');
    const opt = sel.options[sel.selectedIndex];
    const token = opt ? opt.getAttribute('data-token') : '';
    document.getElementById('input-token-siswa').value = token;
}

function applySesi(kode) {
    if (kode) {
        document.getElementById('input-kode-qr').value = kode;
    }
}

function pasteClipboard() {
    navigator.clipboard.readText().then(text => {
        if (text) {
            document.getElementById('input-kode-qr').value = text.trim();
        }
    }).catch(() => alert('Gagal mengakses clipboard'));
}

function submitScan() {
    const kodeQr = document.getElementById('input-kode-qr').value.trim();
    const token = document.getElementById('input-token-siswa').value.trim();
    const btn = document.getElementById('btn-scan');
    const output = document.getElementById('json-output');
    const badge = document.getElementById('response-badge');
    const feedback = document.getElementById('scan-feedback');

    if (!kodeQr) {
        alert('Silakan masukkan Kode QR terlebih dahulu');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">refresh</span> Mengirim request ke API...';
    output.textContent = 'Memproses request...';

    fetch('<?= APP_URL ?>/api/absen_qr_scan.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify({ kode_qr: kodeQr })
    })
    .then(async res => {
        const json = await res.json();
        const status = res.status;

        badge.classList.remove('hidden');
        feedback.classList.remove('hidden');

        if (res.ok && json.success) {
            badge.className = 'px-2.5 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700';
            badge.textContent = `${status} OK`;

            feedback.className = 'p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2';
            feedback.innerHTML = `<span class="material-symbols-outlined text-[18px]">check_circle</span> ${json.message}`;
        } else {
            badge.className = 'px-2.5 py-0.5 text-[10px] font-bold rounded-full bg-rose-100 text-rose-700';
            badge.textContent = `${status} ERROR`;

            feedback.className = 'p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs font-semibold flex items-center gap-2';
            feedback.innerHTML = `<span class="material-symbols-outlined text-[18px]">error</span> ${json.message || 'Gagal memproses absen'}`;
        }

        output.textContent = JSON.stringify(json, null, 2);
    })
    .catch(err => {
        output.textContent = 'Network Error: ' + err.message;
        badge.classList.remove('hidden');
        badge.className = 'px-2.5 py-0.5 text-[10px] font-bold rounded-full bg-rose-100 text-rose-700';
        badge.textContent = 'ERR';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">qr_code_scanner</span> Simulasikan Siswa Scan QR Sekarang';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
