<?php
// api/delete_reservation.php
// POST JSON { id: <reservation id> }
// Marks reservation as 'cancelled' and frees the table if there are no other active reservations.

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input)) {
    $input = $_POST;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid reservation id']);
    exit;
}

try {
    // Fetch reservation to know table_id
    $stmt = $pdo->prepare("SELECT id, table_id, status FROM reservations WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Reservation not found']);
        exit;
    }

    // If already cancelled/completed, return success (idempotent)
    if (in_array($res['status'], ['cancelled','completed'], true)) {
        echo json_encode(['success' => true, 'id' => $id, 'note' => 'Already cancelled or completed']);
        exit;
    }

    // Mark reservation cancelled
    $u = $pdo->prepare("UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = :id LIMIT 1");
    $u->execute([':id' => $id]);

    $tableId = (int)$res['table_id'];

    // Check if the table still has any active reservations (reserved/occupied)
    $chk = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM reservations r
        WHERE r.table_id = :table_id
          AND r.id != :id
          AND r.status IN ('reserved','occupied')
    ");
    $chk->execute([':table_id' => $tableId, ':id' => $id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    $hasActive = ($row && (int)$row['cnt'] > 0);

    // If no other active reservation, set table to available and clear guest
    if (!$hasActive) {
        $upd = $pdo->prepare("UPDATE `tables` SET `status` = 'available', `guest` = '' WHERE id = :id");
        $upd->execute([':id' => $tableId]);
    }

    echo json_encode(['success' => true, 'id' => $id, 'table_id' => $tableId, 'table_still_has_active_reservation' => $hasActive]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>