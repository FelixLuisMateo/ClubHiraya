<?php
// file: api/get_tables.php
// Simple endpoint that returns all rows from `tables` as JSON.
// Make sure api/db.php is configured correctly and the 'tables' table exists.

/*
  Expected response:
  { "success": true, "data": [ { "id":1, "name":"Table 1", "status":"occupied", "seats":6, "guest":"" }, ... ] }
*/

header('Content-Type: application/json; charset=utf-8');

// DEV: show PHP errors in response to help debugging (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load DB connection (adjust path if your structure differs)
require_once __DIR__ . '/db.php';

try {
    // Use a simple SELECT. In a real app, you might implement pagination and permissions.
    $stmt = $pdo->query("SELECT id, name, status, seats, IFNULL(guest,'') AS guest, updated_at FROM `tables` ORDER BY id ASC");
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}