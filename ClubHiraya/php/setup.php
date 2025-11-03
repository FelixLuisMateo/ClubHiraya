<?php
// One-time setup script to create 'clubhiraya' DB, users table, and seed an admin + employee.
// Run once and then remove or protect this file.

$servername = "localhost";
$username = "root";
$password = "";

// Connect without selecting a DB to create it
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$dbname = "clubhiraya";
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === FALSE) {
    die("Error creating database: " . $conn->error);
}

$conn->select_db($dbname);

// Create users table
$tableSql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('employee','admin','manager') NOT NULL DEFAULT 'employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($tableSql) === FALSE) {
    die("Error creating table: " . $conn->error);
}

// Seed an admin and an employee if they don't exist
$seedAccounts = [
    ['email' => 'admin@example.com', 'password' => 'AdminPass123', 'role' => 'admin'],
    ['email' => 'employee@example.com', 'password' => 'EmployeePass123', 'role' => 'employee'],
];

$insertStmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
foreach ($seedAccounts as $acct) {
    // check exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $check->bind_param('s', $acct['email']);
    $check->execute();
    $res = $check->get_result();
    if ($res && $res->num_rows === 0) {
        $hash = password_hash($acct['password'], PASSWORD_DEFAULT);
        $insertStmt->bind_param('sss', $acct['email'], $hash, $acct['role']);
        $insertStmt->execute();
        echo "Inserted: {$acct['email']} (role: {$acct['role']})<br>";
    } else {
        echo "Already exists: {$acct['email']}<br>";
    }
    $check->close();
}
$insertStmt->close();

echo "<br>Setup complete. Please remove or protect setup.php after use.<br>";
$conn->close();
?>