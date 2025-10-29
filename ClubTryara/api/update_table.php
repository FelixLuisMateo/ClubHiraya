<?php
// file: api/update_table.php
// Update fields for a table row. Accepts JSON POST body with id and any of: status, seats, guest, name
// Example request body: {"id":1, "status":"available", "guest":"", "seats":4}

/*
  Security / production notes:
  - Add authentication (ensure only staff can call this).
  - Sanitize and validate inputs server-side (basic checks are included below).
  - Consider CSRF protection if used from a browser session.
  - In production, disable display_errors and log errors instead of showing them to clients.
*/

header('Content-Type: application/json; charset=utf-8');

// DEV: show PHP errors (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
$status = isset($input['status']) ? trim($input['status']) : null;
$seats = isset($input['seats']) ? (int)$input['seats'] : null;
$guest = array_key_exists('guest', $input) ? trim($input['guest']) : null;
$name  = isset($input['name']) ? trim($input['name']) : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

// Allowed status values - change if you add more statuses
$allowedStatuses = ['available', 'occupied', 'reserved'];

$fields = [];
$params = [':id' => $id];

// Validate and collect update fields
if ($status !== null) {
    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }
    $fields[] = "`status` = :status";
    $params[':status'] = $status;
}

if ($seats !== null) {
    if ($seats < 1 || $seats > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid seats (1-100)']);
        exit;
    }
    $fields[] = "`seats` = :seats";
    $params[':seats'] = $seats;
}

if ($guest !== null) {
    // allow empty string to clear guest
    if (strlen($guest) > 255) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Guest name too long']);
        exit;
    }
    $fields[] = "`guest` = :guest";
    $params[':guest'] = $guest;
}

if ($name !== null) {
    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Name too long']);
        exit;
    }
    $fields[] = "`name` = :name";
    $params[':name'] = $name;
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'No fields to update']);
    exit;
}

$sql = "UPDATE `tables` SET " . implode(', ', $fields) . " WHERE id = :id LIMIT 1";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}