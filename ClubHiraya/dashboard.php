<?php
session_start();
if (!isset($_SESSION['employee_id'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard</title>
</head>
<body>
    <h2>Welcome, <?php echo $_SESSION['employee_email']; ?>!</h2>
    <a href="logout.php">Logout</a>
</body>
</html>
