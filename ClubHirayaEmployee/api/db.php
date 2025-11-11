<?php
// api/db.php - PDO MySQL helper tuned for XAMPP
// Edit DB_* constants below to match your environment.

// DEV: show errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CONFIG - adjust as needed for your XAMPP setup
define('DB_HOST', '127.0.0.1');   // prefer 127.0.0.1 to avoid socket vs TCP ambiguity
define('DB_PORT', '3306');        // XAMPP default MySQL port
define('DB_NAME', 'clubhiraya');  // change if your DB name differs
define('DB_USER', 'root');        // default XAMPP user
define('DB_PASS', '');            // default XAMPP password is empty
define('DB_CHAR', 'utf8mb4');

header('Content-Type: application/json; charset=utf-8');

// Quick check: ensure PDO MySQL driver is available
if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'PHP pdo_mysql extension is not enabled. Enable extension=pdo_mysql in php.ini and restart Apache.']);
    exit;
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_PERSISTENT => false,
];

$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // You may remove the following line in production; it's just helpful for debugging in development.
    // (Do not echo here for normal API consumers; other scripts should include this file but they set headers themselves.)
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>