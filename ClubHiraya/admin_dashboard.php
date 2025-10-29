<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit();
}
$role = strtolower($_SESSION['user_role']);
if ($role !== 'admin' && $role !== 'manager') {
    // not allowed
    header('Location: employee_dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Admin Dashboard</title></head>
<body>
<h1>Admin/Manager Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['user_email']); ?> (<?php echo htmlspecialchars($_SESSION['user_role']); ?>)</p>
<p><a href="logout.php">Log out</a></p>
</body>
</html>