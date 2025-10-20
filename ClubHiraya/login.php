<?php
// Start the session to store login info
session_start();
include 'db_connect.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the form data
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Prepare the SQL query to find the employee
    $sql = "SELECT * FROM employees WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password (if stored using password_hash)
        if (password_verify($password, $user['password'])) {
            $_SESSION['employee_id'] = $user['id'];
            $_SESSION['employee_email'] = $user['email'];
            header("Location: dashboard.php");
            exit();
        } else {
            echo "<script>alert('Incorrect password!');</script>";
        }
    } else {
        echo "<script>alert('Email not found!');</script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1440, initial-scale=1.0">
    <title>Club Hiraya - Employee Login</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-center"></div>
        <div class="login-content">
            <form action="login.php" method="POST">
                <label for="email">EMPLOYEE LOGIN</label>
                <input type="email" id="email" name="email" placeholder="Email" required>
                <input type="password" id="password" name="password" placeholder="Password" required>
                <button type="submit">Confirm Log in</button>
            </form>
        </div>
    </div>
</body>
</html>
