<?php
require_once __DIR__ . '/php/auth.php';
require_login();

// Expect POST with csrf_token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        // Invalid request
        header('HTTP/1.1 400 Bad Request');
        echo 'Invalid CSRF token.';
        exit;
    }
    // Destroy session safely
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

// If accessed via GET, redirect to login
header('Location: login.php');
exit;
?>