<?php
// settings/change-password.php
// Minimal change: store new passwords as plain text (NOT RECOMMENDED) per your request.
// IMPORTANT: storing plaintext passwords is insecure. Only do this temporarily for testing and revert ASAP.

// Start session as early as possible
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple flash helper (stores messages in session)
function flash($key, $msg = null) {
    if ($msg === null) {
        if (isset($_SESSION[$key])) {
            $m = $_SESSION[$key];
            unset($_SESSION[$key]);
            return $m;
        }
        return null;
    }
    $_SESSION[$key] = $msg;
}

// Require DB connection (relative to settings/ -> ../php/db_connect.php)
$dbConnectPath = __DIR__ . '/../php/db_connect.php';
if (!file_exists($dbConnectPath)) {
    flash('change_pass_error', 'Server configuration error: missing DB connection file.');
    header('Location: settings.php');
    exit();
}
require_once $dbConnectPath;

// Ensure db_connect.php provides a mysqli $conn
if (!isset($conn) || !($conn instanceof mysqli)) {
    flash('change_pass_error', 'Server configuration error: invalid database connection.');
    header('Location: settings.php');
    exit();
}

// Attempt to find current user id/email in session (adjust to your auth keys)
$uid = null;
$email = null;
if (!empty($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
} elseif (!empty($_SESSION['id'])) {
    $uid = (int)$_SESSION['id'];
} elseif (!empty($_SESSION['email'])) {
    $email = $_SESSION['email'];
} elseif (!empty($_SESSION['username'])) {
    $email = $_SESSION['username'];
}

if (!$uid && !$email) {
    flash('change_pass_error', 'You must be signed in to change your password.');
    header('Location: settings.php');
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_change_pass'])) $_SESSION['csrf_change_pass'] = bin2hex(random_bytes(24));
$csrf_token = $_SESSION['csrf_change_pass'];

function is_hashed_password(string $hash): bool {
    $info = password_get_info($hash);
    return isset($info['algo']) && $info['algo'] !== 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string)($_SESSION['csrf_change_pass'] ?? ''), (string)$posted_token)) {
        flash('change_pass_error', 'Invalid request (CSRF).');
        header('Location: change-password.php');
        exit();
    }

    $current = trim((string)($_POST['current_password'] ?? ''));
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        flash('change_pass_error', 'Please fill all fields.');
        header('Location: change-password.php');
        exit();
    }
    if ($new !== $confirm) {
        flash('change_pass_error', 'New password and confirmation do not match.');
        header('Location: change-password.php');
        exit();
    }
    if (strlen($new) < 8) {
        flash('change_pass_error', 'New password must be at least 8 characters long.');
        header('Location: change-password.php');
        exit();
    }

    // Fetch user row
    if ($uid) {
        $stmt = $conn->prepare('SELECT id, password FROM users WHERE id = ? LIMIT 1');
        if ($stmt) $stmt->bind_param('i', $uid);
    } else {
        $stmt = $conn->prepare('SELECT id, password FROM users WHERE email = ? LIMIT 1');
        if ($stmt) $stmt->bind_param('s', $email);
    }
    if (!$stmt) {
        flash('change_pass_error', 'Database error (prepare failed).');
        header('Location: change-password.php');
        exit();
    }
    if (!$stmt->execute()) {
        $stmt->close();
        flash('change_pass_error', 'Database error (execute failed).');
        header('Location: change-password.php');
        exit();
    }
    $stmt->bind_result($dbId, $dbHash);
    $found = null;
    if ($stmt->fetch()) {
        $found = ['id' => $dbId, 'password' => $dbHash];
    }
    $stmt->close();

    if (!$found) {
        flash('change_pass_error', 'User account not found.');
        header('Location: change-password.php');
        exit();
    }

    $stored = (string)$found['password'];
    $current_ok = false;
    if (is_hashed_password($stored)) {
        // If stored is hashed (old accounts), verify using password_verify
        if (password_verify($current, $stored)) $current_ok = true;
    } else {
        // Plaintext stored password
        if (hash_equals($stored, $current)) $current_ok = true;
    }

    if (!$current_ok) {
        flash('change_pass_error', 'Current password is incorrect.');
        header('Location: change-password.php');
        exit();
    }

    // IMPORTANT CHANGE: store the new password as plain text (requested)
    $newPlain = $new;

    $uStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
    if (!$uStmt) {
        flash('change_pass_error', 'Database error (prepare failed).');
        header('Location: change-password.php');
        exit();
    }
    $uStmt->bind_param('si', $newPlain, $found['id']);
    $ok = $uStmt->execute();
    $uStmt->close();

    if ($ok) {
        // remove CSRF token and rotate session id
        unset($_SESSION['csrf_change_pass']);
        session_regenerate_id(true);

        flash('change_pass_success', 'Your password has been updated.');
        header('Location: settings.php');
        exit();
    } else {
        flash('change_pass_error', 'Failed to update password. Please try again later.');
        header('Location: change-password.php');
        exit();
    }
}

// GET: render form
$err = flash('change_pass_error');
$ok = flash('change_pass_success');
?>
<!doctype html>
<html lang="en">
<head >
  <meta charset="utf-8">
  <title>Change Password</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .cp-wrapper { max-width:480px;margin:36px auto;padding:18px;background:#fff;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.06); }
    .cp-row{display:flex;flex-direction:column;gap:8px;margin-bottom:12px}
    .cp-row input{padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
    .cp-actions{display:flex;gap:8px;align-items:center}
    .btn{padding:10px 14px;border-radius:6px;border:0;background:#4b4bff;color:#fff;font-weight:700;cursor:pointer}
    .btn-secondary{background:#eee;color:#111}
    .flash-error{background:#ffe5e5;border:1px solid #ffbcbc;padding:10px;border-radius:6px;color:#900;margin-bottom:12px}
    .flash-ok{background:#e6ffea;border:1px solid #b7f0c7;padding:10px;border-radius:6px;color:#0a5;margin-bottom:12px}
  </style>
</head>
<body>
  <main class="cp-wrapper" role="main" aria-labelledby="cp-title">
    <h1 id="cp-title">Change Password</h1>

    <?php if ($err): ?>
      <div class="flash-error" role="alert"><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <?php if ($ok): ?>
      <div class="flash-ok" role="status"><?php echo htmlspecialchars($ok, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <form method="POST" action="change-password.php" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">

      <div class="cp-row">
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" required>
      </div>

      <div class="cp-row">
        <label for="new_password">New password (min 8 chars)</label>
        <input id="new_password" name="new_password" type="password" required>
      </div>

      <div class="cp-row">
        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" required>
      </div>

      <div class="cp-actions">
        <button type="submit" class="btn">Update Password</button>
        <a class="btn btn-secondary" href="settings.php">Back to Settings</a>
      </div>
    </form>
  </main>
</body>
</html>