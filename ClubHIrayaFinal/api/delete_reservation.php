<?php
// api/delete_reservation.php
// POST JSON { id: <reservation id> }
// Archives the reservation row into reservations_archive (with deleted_at, deleted_by, deletion_note)
// then deletes the reservation. After deletion, if the related table has no other active reservations (reserved/occupied),
// the table.status will be set to 'available' and guest cleared.
//
// This preserves an audit trail for deleted reservations.

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
$deletion_note = isset($input['note']) ? trim($input['note']) : null;
// Optionally accept who performed the deletion from client (e.g., user id or username)
$deleted_by_client = isset($input['deleted_by']) ? trim($input['deleted_by']) : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid reservation id']);
    exit;
}

// derive deleted_by from client if provided, otherwise from server env (IP + optional auth)
$deleted_by = $deleted_by_client ?: ($_SERVER['REMOTE_USER'] ?? ($_SERVER['HTTP_X_USER'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown')));

try {
    // Fetch reservation to know table_id and full row
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Reservation not found']);
        exit;
    }

    $tableId = (int)$res['table_id'];

    // Begin transaction so we archive + delete reservation and update table consistently
    $pdo->beginTransaction();

    // Insert into archive table (use explicit columns to be robust)
    // The archive table should be created with the migration provided alongside this script.
    $archiveSql = "
      INSERT INTO reservations_archive
        (reservation_id, table_id, `date`, start_time, end_time, `start`, `end`, guest, party_size, status, duration_minutes, total_price, created_at, updated_at, deleted_at, deleted_by, deletion_note)
      VALUES
        (:reservation_id, :table_id, :date, :start_time, :end_time, :start_dt, :end_dt, :guest, :party_size, :status, :duration_minutes, :total_price, :created_at, :updated_at, NOW(), :deleted_by, :deletion_note)
    ";
    $archStmt = $pdo->prepare($archiveSql);
    $archStmt->execute([
        ':reservation_id' => $res['id'],
        ':table_id' => $res['table_id'],
        ':date' => $res['date'] ?? null,
        ':start_time' => $res['start_time'] ?? null,
        ':end_time' => $res['end_time'] ?? null,
        ':start_dt' => $res['start'] ?? null,
        ':end_dt' => $res['end'] ?? null,
        ':guest' => $res['guest'] ?? null,
        ':party_size' => $res['party_size'] ?? null,
        ':status' => $res['status'] ?? null,
        ':duration_minutes' => $res['duration_minutes'] ?? null,
        ':total_price' => $res['total_price'] ?? null,
        ':created_at' => $res['created_at'] ?? null,
        ':updated_at' => $res['updated_at'] ?? null,
        ':deleted_by' => $deleted_by,
        ':deletion_note' => $deletion_note
    ]);
    $archived = ($archStmt->rowCount() > 0);

    if (!$archived) {
        // Archive failed for some reason; rollback and return error
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to archive reservation before deletion']);
        exit;
    }

    // Delete the reservation row
    $del = $pdo->prepare("DELETE FROM reservations WHERE id = :id");
    $del->execute([':id' => $id]);
    $deleted = $del->rowCount();

    // Check if the table still has any active reservations (reserved/occupied)
    $chk = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM reservations r
        WHERE r.table_id = :table_id
          AND r.status IN ('reserved','occupied')
    ");
    $chk->execute([':table_id' => $tableId]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    $hasActive = ($row && (int)$row['cnt'] > 0);

    // If no other active reservation, set table to available and clear guest
    if (!$hasActive) {
        $upd = $pdo->prepare("UPDATE `tables` SET `status` = 'available', `guest` = '' WHERE id = :id");
        $upd->execute([':id' => $tableId]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $id,
        'archived' => $archived,
        'deleted' => ($deleted > 0),
        'table_id' => $tableId,
        'table_still_has_active_reservation' => $hasActive
    ]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>