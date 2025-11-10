<?php
// Central auth file — include at the top of every protected page BEFORE any output.
// Location: ClubHIrayaFinal/php/auth.php

// Harden session cookie settings and start session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Create a minimal session init marker
if (empty($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Inactivity timeout (seconds)
$inactiveLimit = 30 * 60; // 30 minutes
if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactiveLimit)) {
    session_unset();
    session_destroy();
    // If timed out, redirect to login relative to the project root
    header('Location: ../login.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Authentication helpers
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

function require_role($allowed): void {
    if (is_string($allowed)) $allowed = [$allowed];
    if (empty($_SESSION['role']) || !in_array($_SESSION['role'], $allowed, true)) {
        // forbidden: redirect to login or a no-access page
        header('Location: ../login.php');
        exit;
    }
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

// CSRF helpers
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>