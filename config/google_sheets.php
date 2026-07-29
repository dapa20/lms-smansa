<?php
/**
 * =====================================================================
 * KONFIGURASI GOOGLE SHEETS API
 * =====================================================================
 * Untuk menggunakan fitur "Buka di Google Sheets":
 *
 * 1. Buka https://console.cloud.google.com
 * 2. Buat project baru (misal: "LMS Smansa")
 * 3. Enable "Google Sheets API" dan "Google Drive API" di APIs & Services > Library
 * 4. Buat Service Account di APIs & Services > Credentials > Create Credentials > Service Account
 * 5. Di Service Account yang baru dibuat > tab "Keys" > Add Key > JSON > Download
 * 6. Simpan file JSON yang didownload ke:
 *    c:\xampp\htdocs\lms-smansa\config\service_account.json
 *
 * PENTING: Jangan commit file service_account.json ke Git!
 *          File tersebut sudah ditambahkan ke .gitignore secara otomatis.
 * =====================================================================
 */

if (!defined('GOOGLE_SERVICE_ACCOUNT_JSON')) {
    define('GOOGLE_SERVICE_ACCOUNT_JSON', __DIR__ . '/service_account.json');
}

/**
 * Nama aplikasi yang akan tampil di Google API dashboard
 */
if (!defined('GOOGLE_APP_NAME')) {
    define('GOOGLE_APP_NAME', 'LMS SMANSA');
}

/**
 * ID Folder Google Drive (Opsional tapi Direkomendasikan)
 * Tempat menyimpan file rekap spreadsheet di Google Drive Anda.
 * Bagikan (Share) folder Drive Anda ke email Service Account sebagai Editor.
 */
if (!defined('GOOGLE_DRIVE_FOLDER_ID')) {
    define('GOOGLE_DRIVE_FOLDER_ID', getenv('GOOGLE_DRIVE_FOLDER_ID') ?: '1NpEM0ghh3iaNtRXzKGvOLXbAEEZ_8x4G');
}

/**
 * Cek apakah konfigurasi Google Sheets sudah siap
 */
function isGoogleSheetsConfigured(): bool
{
    return file_exists(GOOGLE_SERVICE_ACCOUNT_JSON);
}
