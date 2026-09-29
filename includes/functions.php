<?php
/**
 * =====================================================================
 * FUNGSI BANTU (HELPERS)
 * =====================================================================
 */

/** Escape output HTML supaya aman dari XSS. Dipakai di semua tempat cetak data user. */
function h(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/** Format tanggal MySQL (Y-m-d atau Y-m-d H:i:s) ke format Indonesia, contoh: 24 Mei 2024 */
function formatTanggalIndo(?string $tanggal, bool $denganJam = false): string
{
    if (empty($tanggal) || $tanggal === '0000-00-00') {
        return '-';
    }
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($tanggal);
    $hasil = date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    if ($denganJam) {
        $hasil .= ', ' . date('H:i', $ts) . ' WIB';
    }
    return $hasil;
}

/** Waktu relatif sederhana ("2 jam yang lalu", "Kemarin", dst) untuk pengumuman. */
function waktuRelatif(string $tanggal): string
{
    $selisih = time() - strtotime($tanggal);
    if ($selisih < 60) return 'Baru saja';
    if ($selisih < 3600) return floor($selisih / 60) . ' menit yang lalu';
    if ($selisih < 86400) return floor($selisih / 3600) . ' jam yang lalu';
    if ($selisih < 172800) return 'Kemarin';
    if ($selisih < 604800) return floor($selisih / 86400) . ' hari yang lalu';
    return formatTanggalIndo($tanggal);
}

/** Ubah ukuran file (bytes) menjadi format mudah dibaca, contoh: 4.2 MB */
function formatUkuranFile(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

/** Ambil inisial dari nama (untuk avatar placeholder), contoh "Ahmad Wijaya" -> "AW" */
function inisialNama(string $nama): string
{
    $kata = preg_split('/\s+/', trim($nama));
    $inisial = '';
    foreach (array_slice($kata, 0, 2) as $k) {
        $inisial .= mb_strtoupper(mb_substr($k, 0, 1));
    }
    return $inisial ?: '?';
}

/** Hitung nilai akhir dari komponen tugas, UTS, UAS.
 *  Bobot default: rata-rata tugas 30%, UTS 30%, UAS 40%.
 *  Komponen yang kosong (NULL) diabaikan dari rata-rata tugas. */
function hitungNilaiAkhir($tugas1, $tugas2, $tugas3, $uts, $uas): ?float
{
    $tugas = array_filter([$tugas1, $tugas2, $tugas3], fn($v) => $v !== null && $v !== '');
    $rataTugas = count($tugas) > 0 ? array_sum($tugas) / count($tugas) : null;

    if ($rataTugas === null && $uts === null && $uas === null) {
        return null;
    }

    $bobotTerpakai = 0;
    $totalNilai = 0;
    if ($rataTugas !== null) { $totalNilai += $rataTugas * 0.30; $bobotTerpakai += 0.30; }
    if ($uts !== null && $uts !== '')  { $totalNilai += (float)$uts * 0.30; $bobotTerpakai += 0.30; }
    if ($uas !== null && $uas !== '')  { $totalNilai += (float)$uas * 0.40; $bobotTerpakai += 0.40; }

    if ($bobotTerpakai === 0) {
        return null;
    }
    // Normalisasi jika ada komponen yang belum diisi, supaya proporsional.
    return round($totalNilai / $bobotTerpakai, 1);
}

/** Badge warna berdasarkan nilai akhir, dipakai di tabel Rekap Nilai. */
function warnaBadgeNilai(?float $nilai): string
{
    if ($nilai === null) return 'bg-surface-container text-text-muted';
    if ($nilai >= 90) return 'bg-primary-container text-on-primary-container';
    if ($nilai >= 80) return 'bg-status-gold/60 text-secondary-fixed-dim';
    if ($nilai >= 75) return 'bg-surface-container-highest text-on-surface-variant';
    return 'bg-error-container text-error';
}

/** Simpan pesan flash (notifikasi sukses/gagal) untuk ditampilkan setelah redirect. */
function setFlash(string $tipe, string $pesan): void
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

/** Ambil & hapus pesan flash yang tersimpan. */
function getFlash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** Redirect helper singkat. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Nama hari dalam Bahasa Indonesia dari angka date('N') PHP (1=Senin ... 7=Minggu). */
function namaHariIndo(int $n): string
{
    $hari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    return $hari[$n] ?? '';
}

/** Cetak banner notifikasi flash (sukses/gagal) jika ada. Panggil di awal konten halaman. */
function renderFlash(): void
{
    $flash = getFlash();
    if (!$flash) {
        return;
    }
    $sukses = $flash['tipe'] === 'sukses';
    $bgClass = $sukses ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800';
    $icon = $sukses ? 'check_circle' : 'error';
    $iconColor = $sukses ? 'text-emerald-500' : 'text-red-500';
    
    echo '<div class="flex items-center gap-3 border rounded-xl px-5 py-4 mb-6 shadow-sm ' . $bgClass . '">'
        . '<span class="material-symbols-outlined ' . $iconColor . ' text-[24px]">' . $icon . '</span>'
        . '<span class="text-sm font-semibold flex-1">' . h($flash['pesan']) . '</span>'
        . '</div>';
}

/** Icon Material Symbols + warna latar berdasarkan tipe file materi. */
function ikonTipeFile(string $tipe): array
{
    return match ($tipe) {
        'pdf'   => ['picture_as_pdf', 'bg-error text-white', 'text-error/40'],
        'video' => ['video_library', 'bg-secondary text-on-secondary', 'text-primary/40'],
        'docx'  => ['description', 'bg-primary text-white', 'text-primary/40'],
        'pptx'  => ['present_to_all', 'bg-secondary-fixed-dim text-on-secondary-fixed', 'text-secondary-fixed-dim/60'],
        'xlsx'  => ['table_chart', 'bg-tertiary text-white', 'text-tertiary/40'],
        default => ['insert_drive_file', 'bg-outline text-white', 'text-outline/40'],
    };
}

/**
 * =====================================================================
 * XSS PROTECTION - HTML SANITIZATION
 * =====================================================================
 */

/** 
 * Sanitasi HTML konten dari rich text editor.
 * Hanya izinkan tag dan atribut yang aman.
 */
function sanitizeHtml(string $html): string
{
    // Daftar tag yang diizinkan
    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'strike', 'del',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'a', 'img', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
        'div', 'span', 'blockquote', 'pre', 'code',
        'iframe' // untuk embed video
    ];
    
    // Daftar atribut yang diizinkan per tag
    $allowedAttributes = [
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'width', 'height', 'class'],
        'iframe' => ['src', 'width', 'height', 'frameborder', 'allowfullscreen', 'class'],
        'table' => ['class', 'border', 'cellpadding', 'cellspacing'],
        'td' => ['colspan', 'rowspan', 'class'],
        'th' => ['colspan', 'rowspan', 'class'],
        'div' => ['class', 'style'],
        'span' => ['class', 'style'],
        'p' => ['class', 'style'],
        'h1' => ['class'], 'h2' => ['class'], 'h3' => ['class'],
        'h4' => ['class'], 'h5' => ['class'], 'h6' => ['class'],
        'ul' => ['class'], 'ol' => ['class'], 'li' => ['class'],
        'blockquote' => ['class'], 'pre' => ['class'], 'code' => ['class']
    ];
    
    // Konfigurasi HTMLPurifier-like manual
    $config = \HTMLPurifier_Config::createDefault();
    $config->set('HTML.Allowed', implode(',', array_map(function($tag) use ($allowedAttributes) {
        if (isset($allowedAttributes[$tag])) {
            return $tag . '[' . implode(',', $allowedAttributes[$tag]) . ']';
        }
        return $tag;
    }, $allowedTags)));
    
    $config->set('URI.AllowedSchemes', ['http', 'https', 'mailto']);
    $config->set('AutoFormat.RemoveEmpty', true);
    $config->set('HTML.TargetBlank', true);
    
    // Jika HTMLPurifier tidak tersedia, gunakan fallback
    if (!class_exists('HTMLPurifier')) {
        return sanitizeHtmlFallback($html, $allowedTags, $allowedAttributes);
    }
    
    $purifier = new \HTMLPurifier($config);
    return $purifier->purify($html);
}

/** 
 * Fallback sanitasi HTML jika HTMLPurifier tidak tersedia.
 * Menggunakan strip_tags + regex untuk atribut.
 */
function sanitizeHtmlFallback(string $html, array $allowedTags, array $allowedAttributes): string
{
    // Step 1: Hapus tag yang tidak diizinkan
    $allowedTagsStr = '<' . implode('><', $allowedTags) . '>';
    $html = strip_tags($html, $allowedTagsStr);
    
    // Step 2: Bersihkan atribut berbahaya dari setiap tag
    $html = preg_replace_callback(
        '/<(\w+)([^>]*)>/i',
        function ($matches) use ($allowedAttributes) {
            $tag = strtolower($matches[1]);
            $attrs = $matches[2];
            
            // Jika tag tidak punya atribut yang diizinkan, return tag saja
            if (!isset($allowedAttributes[$tag])) {
                return "<$tag>";
            }
            
            // Parse dan filter atribut
            $allowed = $allowedAttributes[$tag];
            $cleanAttrs = '';
            
            preg_match_all('/(\w+)=(["\'])(.*?)\2/i', $attrs, $attrMatches, PREG_SET_ORDER);
            foreach ($attrMatches as $attr) {
                $attrName = strtolower($attr[1]);
                $attrValue = $attr[3];
                
                if (in_array($attrName, $allowed)) {
                    // Sanitasi value atribut
                    if ($attrName === 'href') {
                        // Hanya izinkan http, https, mailto
                        if (!preg_match('/^(https?:|mailto:)/i', $attrValue)) {
                            continue;
                        }
                    }
                    if ($attrName === 'src') {
                        // Hanya izinkan http, https untuk img/iframe
                        if (!preg_match('/^(https?:|\/)/i', $attrValue)) {
                            continue;
                        }
                    }
                    
                    $cleanAttrs .= ' ' . $attrName . '="' . htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8') . '"';
                }
            }
            
            return "<$tag$cleanAttrs>";
        },
        $html
    );
    
    // Step 3: Hapus event handler (onclick, onload, dll)
    $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    
    // Step 4: Hapus javascript: protocol
    $html = preg_replace('/javascript:/i', '', $html);
    
    return $html;
}

/** 
 * Sanitasi teks biasa (non-HTML) untuk output.
 * Lebih ketat dari h(), cocok untuk plain text.
 */
function e(string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * =====================================================================
 * AUDIT LOG
 * =====================================================================
 */

/**
 * Mencatat aktivitas penting ke dalam tabel audit_logs
 */
function logAktivitas(string $action, string $description): void
{
    global $pdo;
    
    // Pastikan $pdo tersedia
    if (!isset($pdo)) {
        return;
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $action, $description, $ip]);
    } catch (\Throwable $th) {
        // Abaikan jika terjadi error pencatatan log agar tidak mengganggu flow utama
    }
}

/**
 * =====================================================================
 * UI HELPERS
 * =====================================================================
 */

/**
 * Menampilkan halaman "Coming Soon" untuk fitur yang belum dibuat.
 */
function renderComingSoon(string $namaFitur): void
{
    echo '
    <div class="flex flex-col items-center justify-center min-h-[60vh] py-16 px-4 text-center">
        <div class="w-32 h-32 mb-8 bg-gradient-to-tr from-slate-100 to-slate-200 rounded-full flex items-center justify-center shadow-inner border border-white">
            <span class="material-symbols-outlined text-slate-400 text-[64px]">construction</span>
        </div>
        <h2 class="text-3xl font-bold text-slate-800 mb-4 tracking-tight">Fitur Sedang Dibangun</h2>
        <p class="text-slate-500 max-w-md mx-auto mb-10 leading-relaxed text-sm font-medium">
            Sabar ya! Halaman <strong class="text-slate-700">' . h($namaFitur) . '</strong> saat ini masih dalam tahap pengembangan oleh tim IT kami. Kami akan segera merilisnya.
        </p>
        <button onclick="history.back()" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-bold px-6 py-3 rounded-xl flex items-center gap-2 transition-all shadow-sm hover:shadow-md">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            Kembali
        </button>
    </div>
    ';
}
