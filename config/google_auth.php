<?php
/**
 * =====================================================================
 * KONFIGURASI GOOGLE AUTHENTICATION (GOOGLE OAUTH / GIS)
 * =====================================================================
 * Masukkan Google Client ID yang diperoleh dari Google Cloud Console.
 * OAuth Credentials -> OAuth 2.0 Client IDs (Web Application)
 * Authorized JavaScript origins: http://localhost (atau domain lokasi LMS)
 * Authorized redirect URIs: http://localhost/actions/google_login_action.php
 * =====================================================================
 */

if (!defined('GOOGLE_CLIENT_ID')) {
    // Ganti dengan Google Client ID Anda dari Google Cloud Console
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '510485963991-9g90gib604hs137q0efh9i4povaecc7a.apps.googleusercontent.com');
}

/**
 * Memeriksa apakah Google Client ID sudah dikonfigurasi dengan benar
 */
function isGoogleAuthConfigured(): bool
{
    return defined('GOOGLE_CLIENT_ID') && strpos(GOOGLE_CLIENT_ID, 'YOUR_GOOGLE_CLIENT_ID') === false;
}
