<?php
// logout.php
// Destroys the session and redirects the user to the login page.
// This script expects a POST (logout form submission). If you prefer GET-based logout,
// you can remove the request method check.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Optional: if someone visits logout.php directly by GET, redirect them back.
    header('Location: ../login.php');
    exit;
}

// Start session (if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear session variables
$_SESSION = [];

// If session uses cookies, clear the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to your login page. Adjust this path to match your project's login location.
header('Location: ../login.php');
exit;