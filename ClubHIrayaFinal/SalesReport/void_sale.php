<?php
require_once __DIR__ . '/../php/db_connect.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Manila');

$id = intval($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
    exit;
}

// ✅ Check if the sale exists
$stmt = $conn->prepare("SELECT id, is_voided FROM sales_report WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['ok' => false, 'error' => 'Order not found.']);
    exit;
}

// 🚫 Prevent double void
if (!empty($order['is_voided'])) {
    echo json_encode(['ok' => false, 'error' => 'Order already voided.']);
    exit;
}

// ✅ Mark sale as voided
$stmt = $conn->prepare("UPDATE sales_report SET is_voided = 1, voided_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    // ✅ Log this action
    $conn->query("
        CREATE TABLE IF NOT EXISTS sales_void_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sales_id INT NOT NULL,
            voided_by VARCHAR(100) DEFAULT 'System',
            voided_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $conn->query("INSERT INTO sales_void_log (sales_id, voided_by) VALUES ($id, 'Admin')");

    echo json_encode(['ok' => true, 'message' => "Order #$id has been voided successfully."]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Database update failed.']);
}
?>
