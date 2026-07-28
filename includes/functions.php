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
    $bgClass = $sukses ? 'bg-primary/10 border-primary/30 text-primary' : 'bg-error-container border-error/30 text-error';
    $icon = $sukses ? 'check_circle' : 'error';
    echo '<div class="flex items-center gap-3 border rounded-lg px-4 py-3 mb-lg ' . $bgClass . '">'
        . '<span class="material-symbols-outlined">' . $icon . '</span>'
        . '<span class="text-body-sm font-body-sm flex-1">' . h($flash['pesan']) . '</span>'
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
