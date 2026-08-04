<?php
require_once __DIR__ . '/api/config.php';

// เคลียร์ Session ทั้งหมด
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Redirect กลับไปหน้า Login
header('Location: index.php');
exit;
