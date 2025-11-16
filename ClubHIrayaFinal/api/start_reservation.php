<?php
// api/start_reservation.php
// POST JSON { id: <reservation id> }
// Marks a reservation as started now: sets start = NOW(), end = NOW() + duration_minutes, status = 'occupied'
// Computes total_price using table price (falls back to `price` or 3000) and updates table.status -> 'occupied'
// This modified behavior will archive the reservation row into reservations_archive before removing it from reservations,
// per request: when a reserved card is turned occupied, reservation is archived and then deleted.
//
// Returns { success: true, id: ..., table_id: ..., total_price: 1234.56, start: "...", end: "...", reservation_archived: true }

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input)) $input = $_POST;

$id = isset($input['id']) ? (int)$input['id'] : 0;
$deleted_by_client = isset($input['deleted_by']) ? trim($input['deleted_by']) : null;
$deletion_note = isset($input['note']) ? trim($input['note']) : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid reservation id']);
    exit;
}

try {
    // Load reservation
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Reservation not found']);
        exit;
    }

    // If it's already occupied, return success (idempotent)
    if ($res['status'] === 'occupied') {
        echo json_encode([
            'success' => true,
            'id' => $id,
            'note' => 'Already occupied'
        ]);
        exit;
    }

    // Decide duration_minutes
    $duration = (int)$res['duration_minutes'];
    if ($duration <= 0) {
        // try compute from stored start/end if available
        if (!empty($res['start']) && !empty($res['end'])) {
            $dtStart = new DateTime($res['start']);
            $dtEnd = new DateTime($res['end']);
            $diff = $dtEnd->getTimestamp() - $dtStart->getTimestamp();
            $duration = (int)round($diff / 60);
            if ($duration <= 0) $duration = 90;
        } else {
            // fallback default
            $duration = 90;
        }
    }

    // Begin transaction to update table atomically and archive+remove reservation
    $pdo->beginTransaction();

    // Lock table row and get price (use price_per_hour or price if present)
    $price = 3000.00;
    $stmtT = $pdo->prepare("SELECT IFNULL(NULLIF(price_per_hour,0), IFNULL(NULLIF(price,0), 3000.00)) AS price FROM `tables` WHERE id = :tid LIMIT 1");
    $tableId = (int)$res['table_id'];
    $stmtT->execute([':tid' => $tableId]);
    $trow = $stmtT->fetch(PDO::FETCH_ASSOC);
    if ($trow && isset($trow['price'])) {
        $price = (float)$trow['price'];
    }

    // Compute start & end datetimes (start = now)
    $now = new DateTime('now');
    $start_dt = $now->format('Y-m-d H:i:00');
    $end_dt_obj = clone $now;
    $end_dt_obj->modify('+' . max(1, $duration) . ' minutes');
    $end_dt = $end_dt_obj->format('Y-m-d H:i:00');

    // Compute time-only fields
    $start_time_only = $now->format('H:i:00');
    $end_time_only = $end_dt_obj->format('H:i:00');

    // Compute total_price (rounded)
    $hours = $duration / 60.0;
    $total_price = round($price * $hours, 2);

    // Update table status and guest (set guest if reservation has guest)
    $guest = isset($res['guest']) ? $res['guest'] : '';
    $updT = $pdo->prepare("UPDATE `tables` SET `status` = 'occupied', `guest` = :guest WHERE id = :id");
    $updT->execute([':guest' => $guest, ':id' => $tableId]);

    // Archive the reservation row into reservations_archive
    $deleted_by = $deleted_by_client ?: ($_SERVER['REMOTE_USER'] ?? ($_SERVER['HTTP_X_USER'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown')));
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
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to archive reservation before starting']);
        exit;
    }

    // Remove the reservation row now that it is archived
    $del = $pdo->prepare("DELETE FROM reservations WHERE id = :id");
    $del->execute([':id' => $id]);
    $deletedCount = $del->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $id,
        'table_id' => $tableId,
        'total_price' => $total_price,
        'start' => $start_dt,
        'end' => $end_dt,
        'reservation_archived' => ($archived),
        'reservation_deleted' => ($deletedCount > 0),
        'note' => ($archived ? 'Reservation archived and removed' : 'Table marked occupied; reservation archival failed')
    ]);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>