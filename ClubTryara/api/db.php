<?php
// file: api/db.php
// Database connection helper using PDO. Keep credentials here and reuse in other API files.
// IMPORTANT: In production do NOT show errors; this file currently enables error display for development.

/*
  How to configure:
  - $DB_HOST: usually '127.0.0.1' or 'localhost'
  - $DB_NAME: name of database you created (example: 'restaurant')
  - $DB_USER / $DB_PASS: MySQL user and password
  - After editing, restart Apache (if needed) and test the endpoint:
      http://localhost/YourProject/api/get_tables.php
*/

ini_set('display_errors', 1);
error_reporting(E_ALL);

$DB_HOST = '127.0.0.1';
$DB_NAME = 'restaurant'; // change to your database name
$DB_USER = 'root';
$DB_PASS = ''; // XAMPP default is empty string
$DB_CHAR = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // fetch associative arrays
];

try {
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHAR}";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Exception $e) {
    // Return JSON error to help debugging from the browser
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}