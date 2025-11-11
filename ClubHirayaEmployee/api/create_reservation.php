<?php
// api/create_reservation.php
// Create a new reservation for a table.
// Accepts JSON or form fields:
//   table_id (int, required)
//   date (YYYY-MM-DD, required)
//   start_time (HH:MM, required)
//   guest (string, optional)
//   duration (minutes, optional, default 90)
//   party_size (int, optional)
//
// Response:
//   { success: true, id: <reservation id> }
// or
//   { success: false, error: "message" }

header('Content-Type: application/json; charset=utf-8');

// DEV: show errors (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

// Read input (JSON preferred)
$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}

// Fallback to form data
if (empty($input)) {
    $input = $_POST;
}

$table_id   = isset($input['table_id']) ? (int)$input['table_id'] : 0;
$date       = isset($input['date']) ? trim($input['date']) : '';
$start_time = isset($input['start_time']) ? trim($input['start_time']) : '';
$guest      = isset($input['guest']) ? trim($input['guest']) : '';
$duration   = isset($input['duration']) ? (int)$input['duration'] : 90;
$party_size = isset($input['party_size']) ? (int)$input['party_size'] : null;

// Basic validation
if ($table_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid table_id']);
    exit;
}
if ($date === '' || $start_time === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing date or start_time']);
    exit;
}

// Validate date format YYYY-MM-DD
$dtDate = DateTime::createFromFormat('Y-m-d', $date);
if ($dtDate === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

// Parse start datetime
$dtStart = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $start_time);
if ($dtStart === false) {
    // Try common alternative formats
    $dtStart = DateTime::createFromFormat('Y-m-d g:i A', $date . ' ' . $start_time);
    if ($dtStart === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid start_time format. Use HH:MM (24-hour)']);
        exit;
    }
}

$dtEnd = clone $dtStart;
$dtEnd->modify('+' . max(1, $duration) . ' minutes');

$start_dt = $dtStart->format('Y-m-d H:i:00'); // for datetime fields
$end_dt   = $dtEnd->format('Y-m-d H:i:00');
$start_time_only = $dtStart->format('H:i:00');
$end_time_only   = $dtEnd->format('H:i:00');

try {
    // Check table exists (optional but helpful)
    $stmt = $pdo->prepare("SELECT id FROM `tables` WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $table_id]);
    $tbl = $stmt->fetch();
    if (!$tbl) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Table not found']);
        exit;
    }

    // Check for overlapping reservations for same table:
    // Overlap if NOT (r.end <= requested_start OR r.start >= requested_end)
    $sqlCheck = "
        SELECT COUNT(*) AS cnt
        FROM reservations r
        WHERE r.table_id = :table_id
          AND NOT (r.end <= :start_dt OR r.start >= :end_dt)
          AND r.status IN ('reserved','occupied')
    ";
    $stmt = $pdo->prepare($sqlCheck);
    $stmt->execute([
        ':table_id' => $table_id,
        ':start_dt' => $start_dt,
        ':end_dt'   => $end_dt
    ]);
    $row = $stmt->fetch();
    if ($row && (int)$row['cnt'] > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Table is already reserved for that time slot']);
        exit;
    }

    // Insert reservation
    // The reservations table in your project appears to use both date/start_time/end_time and start/end (datetimes).
    // This insert attempts to populate both sets of columns. Adjust column names if your schema differs.
    $sqlInsert = "
        INSERT INTO reservations
          (table_id, date, start_time, end_time, `start`, `end`, guest, party_size, status, created_at)
        VALUES
          (:table_id, :date, :start_time, :end_time, :start_dt, :end_dt, :guest, :party_size, :status, NOW())
    ";
    $stmt = $pdo->prepare($sqlInsert);
    $stmt->execute([
        ':table_id'   => $table_id,
        ':date'       => $date,
        ':start_time' => $start_time_only,
        ':end_time'   => $end_time_only,
        ':start_dt'   => $start_dt,
        ':end_dt'     => $end_dt,
        ':guest'      => $guest,
        ':party_size' => $party_size ?: null,
        ':status'     => 'reserved'
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