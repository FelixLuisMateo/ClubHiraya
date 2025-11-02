<?php
session_start();
include 'php/db_connect.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the form data
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        echo "<script>alert('Please enter email and password.');</script>";
    } else {
        // Prepare the SQL query to find the user
        $sql = "SELECT id, email, password, role FROM users WHERE email = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $dbPass = $user['password'];

                // Plain-text comparison (insecure). Only for local testing.
                if ($password === $dbPass) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];

                    // Redirect based on role
                    $role = strtolower($user['role']);
                    if ($role === 'admin' || $role === 'manager') {
                        header("Location: admin_dashboard.php");
                        exit();
                    } else {
                        header("Location: employee_dashboard.php");
                        exit();
                    }
                } else {
                    echo "<script>alert('Incorrect password!');</script>";
                }
            } else {
                echo "<script>alert('Email not found!');</script>";
            }

            $stmt->close();
        } else {
            echo "<script>alert('Database error.');</script>";
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1440, initial-scale=1.0">
    <title>Club Hiraya - Login</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-center">
            <div class="login-content">
                <form action="login.php" method="POST">
                    <label for="email">EMPLOYEE LOGIN</label>
                    <input type="email" id="email" name="email" placeholder="Email" required>
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <button type="submit">Confirm Log in</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>