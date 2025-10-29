<?php
// api/create_reservation.php
// Create a new reservation. Accepts JSON POST body:
// {
//   "table_id": (optional) int,
//   "start": "YYYY-MM-DD HH:MM:SS",  // required
//   "duration": minutes (optional, default 90),
//   "party_size": int (required),
//   "guest": "Guest Name" (optional)
// }
//
// Behavior:
// - If table_id provided: check that table exists and is available for the interval, then insert.
// - If table_id not provided: find a suitable table with seats >= party_size that has no overlapping reservations,
//   pick the smallest suitable table (by seats) and insert.
// - Returns JSON: { success: true, reservation_id: X, table_id: Y } on success
//
// NOTE: This endpoint uses a simple transactional attempt to reduce race conditions but is not bulletproof at very high concurrency.
// For production: use stronger locking or application-level queueing.

header('Content-Type: application/json; charset=utf-8');

// Dev: show errors for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php'; // expects $pdo

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

// Read and validate inputs
$table_id = isset($input['table_id']) && $input['table_id'] !== '' ? (int)$input['table_id'] : null;
$start = isset($input['start']) ? trim($input['start']) : null;
$duration = isset($input['duration']) ? (int)$input['duration'] : 90;
$party_size = isset($input['party_size']) ? (int)$input['party_size'] : 0;
$guest = isset($input['guest']) ? trim($input['guest']) : '';

// Basic validation
if (!$start || $party_size <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: start and party_size are required']);
    exit;
}

// Parse start datetime
$dt = DateTime::createFromFormat('Y-m-d H:i:s', $start);
if ($dt === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid start datetime format. Use YYYY-MM-DD HH:MM:SS']);
    exit;
}
$start_dt = $dt->format('Y-m-d H:i:s');
$end_dt = $dt->modify("+{$duration} minutes")->format('Y-m-d H:i:s');

try {
    // Start transaction to reduce race (INSERT happens after selecting available table)
    $pdo->beginTransaction();

    // If table_id provided: validate it exists and has enough seats and is free in the interval
    if ($table_id !== null) {
        // Check table exists
        $stmt = $pdo->prepare("SELECT id, seats FROM `tables` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $table_id]);
        $tbl = $stmt->fetch();
        if (!$tbl) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Table not found']);
            exit;
        }
        if ($tbl['seats'] < $party_size) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Table does not have enough seats for this party']);
            exit;
        }
        // Check overlapping reservations
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM reservations WHERE table_id = :table_id AND status <> 'cancelled' AND start < :end_dt AND end > :start_dt");
        $stmt->execute([':table_id' => $table_id, ':start_dt' => $start_dt, ':end_dt' => $end_dt]);
        $row = $stmt->fetch();
        if ($row && $row['cnt'] > 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Table is already reserved/occupied for the requested time']);
            exit;
        }
        $assigned_table_id = $table_id;
    } else {
        // No table_id provided: find an available table with seats >= party_size
        // Prefer smallest table that fits (ORDER BY seats ASC)
        $sql = "
          SELECT t.id
          FROM `tables` t
          WHERE t.seats >= :party_size
            AND t.id NOT IN (
              SELECT r.table_id FROM reservations r
              WHERE r.status <> 'cancelled'
                AND r.start < :end_dt
                AND r.end > :start_dt
            )
          ORDER BY t.seats ASC, t.id ASC
          LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':party_size' => $party_size, ':start_dt' => $start_dt, ':end_dt' => $end_dt]);
        $row = $stmt->fetch();
        if (!$row) {
            // No available table found
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'No available table for the requested time and party size']);
            exit;
        }
        $assigned_table_id = (int)$row['id'];
    }

    // Insert reservation
    $insert = $pdo->prepare("INSERT INTO reservations (table_id, guest, party_size, start, end, status, created_at, updated_at) VALUES (:table_id, :guest, :party_size, :start, :end, 'booked', NOW(), NOW())");
    $insert->execute([
        ':table_id' => $assigned_table_id,
        ':guest' => $guest === '' ? null : $guest,
        ':party_size' => $party_size,
        ':start' => $start_dt,
        ':end' => $end_dt
    ]);
    $reservation_id = (int)$pdo->lastInsertId();

    $pdo->commit();

    // Optionally, you may update table status now (e.g., set to reserved) or rely on availability queries.
    echo json_encode(['success' => true, 'reservation_id' => $reservation_id, 'table_id' => $assigned_table_id]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}