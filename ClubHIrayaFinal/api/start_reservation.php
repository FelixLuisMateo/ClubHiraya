<?php
// api/start_reservation.php
// POST JSON { id: <reservation id> }
// Marks a reservation as started now: sets start = NOW(), end = NOW() + duration_minutes, status = 'occupied'
// Computes total_price using table price (falls back to `price` or 3000) and updates table.status -> 'occupied'
// Returns { success: true, id: ..., total_price: 1234.56, start: "...", end: "..." }

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
            $duration = (int)$dtEnd->diff($dtStart)->days * 24 * 60
                        + (int)$dtEnd->diff($dtStart)->h * 60
                        + (int)$dtEnd->diff($dtStart)->i;
        } else {
            // fallback default
            $duration = 90;
        }
    }

    // Begin transaction to update reservation + table atomically
    $pdo->beginTransaction();

    // Lock table row and get price (use price_per_hour or price if present)
    // We'll attempt to fetch price_per_hour first; if missing use price column
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

    // Update reservation
    $upd = $pdo->prepare("
        UPDATE reservations
        SET `start` = :start_dt,
            `end` = :end_dt,
            `start_time` = :start_time,
            `end_time` = :end_time,
            `duration_minutes` = :duration,
            `total_price` = :total_price,
            `status` = 'occupied',
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $upd->execute([
        ':start_dt' => $start_dt,
        ':end_dt' => $end_dt,
        ':start_time' => $start_time_only,
        ':end_time' => $end_time_only,
        ':duration' => $duration,
        ':total_price' => $total_price,
        ':id' => $id
    ]);

    // Update table status and guest (set guest if reservation has guest)
    $guest = isset($res['guest']) ? $res['guest'] : '';
    $updT = $pdo->prepare("UPDATE `tables` SET `status` = 'occupied', `guest` = :guest WHERE id = :id");
    $updT->execute([':guest' => $guest, ':id' => $tableId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $id,
        'table_id' => $tableId,
        'total_price' => $total_price,
        'start' => $start_dt,
        'end' => $end_dt
    ]);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>