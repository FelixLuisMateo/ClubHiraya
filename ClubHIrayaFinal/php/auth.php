<?php
// auth.php - reusable auth check to include at the top of protected pages

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// You can change 'user_id' to whatever key you use to mark a logged-in user
if (!isset($_SESSION['user_id'])) {
    // Save the requested URL so you can return after successful login (optional)
    // Only store simple paths to avoid open-redirect risks
    if (!empty($_SERVER['REQUEST_URI'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    }

    // Redirect to login page - adjust path to your login page
    header('Location: ../ClubHirayaFinal/login.php');
    exit;
}

// (Optional) Additional checks can be added here, e.g. role checking:
// if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) { ... deny ... }