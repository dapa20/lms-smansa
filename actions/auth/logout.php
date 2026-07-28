<?php
require_once __DIR__ . '/../../includes/auth.php';

$_SESSION = [];
session_destroy();

// Hapus cookie session di browser juga (termasuk yang dibuat oleh "Ingat Saya").
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain']);
}

redirect('../../login.php');
