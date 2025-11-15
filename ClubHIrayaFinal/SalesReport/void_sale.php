<?php
session_start();
header('Content-Type: application/json');

// FIXED PATH
require_once __DIR__ . '/../php/db_connect.php';

if (!isset($_POST['id'])) {
    echo json_encode(['ok' => false, 'error' => 'Missing ID']);
    exit;
}

$id = intval($_POST['id']);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
    exit;
}

$voided_by = $_SESSION['user_name'] ?? 'Admin';

$conn->begin_transaction();

try {

    // 1. UPDATE the main sales_report table
    $stmt = $conn->prepare("
        UPDATE sales_report
        SET is_voided = 1,
            voided_at = NOW(),
            voided_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param("si", $voided_by, $id);

    if (!$stmt->execute()) {
        throw new Exception("Update failed: " . $stmt->error);
    }
    $stmt->close();

    // 2. INSERT a void log entry
    $stmt2 = $conn->prepare("
        INSERT INTO sales_void_log (sales_id, voided_by)
        VALUES (?, ?)
    ");
    $stmt2->bind_param("is", $id, $voided_by);

    if (!$stmt2->execute()) {
        throw new Exception("Insert void log failed: " . $stmt2->error);
    }
    $stmt2->close();

    // Finalize
    $conn->commit();
    echo json_encode(['ok' => true, 'message' => 'Order voided successfully']);

} catch (Exception $e) {

    $conn->rollback();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
