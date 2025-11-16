<?php
// api/create_table.php
// Create a new table row. Accepts JSON or form POST, returns { success: true, id: <new id> }.
//
// Required fields: name (string)
// Optional: status ('available'|'occupied'|'reserved'), seats (int), guest (string), price_per_hour (decimal)

header('Content-Type: application/json; charset=utf-8');

// DEV: show errors (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

// Read input (JSON preferred)
$raw = file_get_contents('php://input');
$data = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $data = $decoded;
    }
}

// If no JSON, fall back to form data
if (empty($data)) {
    $data = $_POST;
}

$name = isset($data['name']) ? trim($data['name']) : '';
$status = isset($data['status']) ? trim($data['status']) : 'reserved';
$seats = isset($data['seats']) ? (int)$data['seats'] : 2;
$guest = isset($data['guest']) ? trim($data['guest']) : '';
$price = isset($data['price_per_hour']) ? (float)$data['price_per_hour'] : 3000.00;

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing table name']);
    exit;
}

// Basic validation for status
$allowed_status = ['available','reserved','occupied'];
if (!in_array($status, $allowed_status, true)) {
    $status = 'reserved';
}

try {
    $stmt = $pdo->prepare("INSERT INTO `tables` (`name`, `status`, `seats`, `guest`, `price_per_hour`, `updated_at`) VALUES (:name, :status, :seats, :guest, :price, NOW())");
    $stmt->execute([
        ':name' => $name,
        ':status' => $status,
        ':seats' => max(1, $seats),
        ':guest' => $guest,
        ':price' => round(max(0, $price), 2)
    ]);
    $newId = (int)$pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $newId]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>