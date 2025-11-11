<?php
session_start();
require_once '../ClubHiraya/ClubHirayaFinal/php/db_connect.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        echo "<script>alert('Please enter both email and password.');</script>";
    } else {
        // Check if user exists
        $sql = "SELECT id, email, password, role FROM users WHERE email = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $dbPass = $user['password'];

                // ⚠️ For now: plain password check (use password_hash in production)
                if ($password === $dbPass) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = strtolower($user['role']);

                    // Redirect by role
                    $role = strtolower($user['role']);
                    if ($role === 'admin' || $role === 'manager') {
                        header("Location: ../ClubHiraya/ClubHirayaFinal/admin_dashboard.php");
                        exit;
                    } elseif ($role === 'staff' || $role === 'employee') {
                        header("Location: ../ClubHiraya/ClubHirayaEmployee/employee_dashboard.php");
                        exit;
                    } else {
                        echo "<script>alert('Unknown role: $role');</script>";
                    }
                } else {
                    echo "<script>alert('Incorrect password!');</script>";
                }
            } else {
                echo "<script>alert('Email not found!');</script>";
            }

            $stmt->close();
        } else {
            echo "<script>alert('Database error: ".$conn->error."');</script>";
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
    <link rel="stylesheet" href="../ClubHiraya/ClubHirayaFinal/css/login.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-center">
            <div class="login-content">
                <form action="" method="POST">
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
