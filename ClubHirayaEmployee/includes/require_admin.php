<?php
// require_admin.php
// If the user is not in an allowed role, show a 403 "Access denied" page (no redirect).
// Place this file at the very top of any protected PHP page (before any output).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Adjust this to match where you store role info in session
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;

// Allowed roles that can access inventory and sales pages
$allowed_roles = ['admin', 'manager']; // change as needed

if (!$role || !in_array($role, $allowed_roles, true)) {
    // Send 403 Forbidden
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');

    // Simple, safe HTML page informing the user they can't access this page
    // Keep markup minimal so it works even before full site CSS loads.
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Access Denied</title>';
    echo '<style>';
    echo 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f7f7f8;color:#111;margin:0;display:flex;align-items:center;justify-content:center;height:100vh}';
    echo '.card{max-width:720px;margin:24px;background:#fff;border-radius:12px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,0.06);text-align:center}';
    echo '.badge{display:inline-block;background:#ff6b6b;color:#fff;padding:6px 10px;border-radius:8px;font-weight:700;margin-bottom:12px}';
    echo 'h1{margin:6px 0 8px;font-size:20px}p{margin:0 0 16px;color:#555}a{color:#d33fd3;text-decoration:none;font-weight:600}';
    echo '.actions{margin-top:16px}';
    echo '.btn{display:inline-block;background:#e9e9ff;color:#111;padding:8px 14px;border-radius:8px;text-decoration:none;margin:0 6px}';
    echo '</style></head><body>';
    echo '<div class="card">';
    echo '<div class="badge">Access Denied</div>';
    echo '<h1>You don\'t have permission to view this page</h1>';
    echo '<p>If you believe this is an error, please contact your administrator.</p>';
    // provide a sensible back link for employees
    echo '<div class="actions">';
    echo '<a class="btn" href="../employee_dashboard.php">Back to dashboard</a>';
    // optionally, allow login page link
    echo '<a class="btn" href="../login.php">Sign in as different user</a>';
    echo '</div>';
    echo '</div>';
    echo '</body></html>';

    exit;
}
?>