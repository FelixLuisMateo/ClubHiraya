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
//   status (optional) - 'reserved' or 'occupied' (default 'reserved')
// This patched version returns a richer 409 payload with conflicting reservation(s).

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
if (empty($input)) {
    $input = $_POST;
}

$table_id   = isset($input['table_id']) ? (int)$input['table_id'] : 0;
$date       = isset($input['date']) ? trim($input['date']) : '';
$start_time = isset($input['start_time']) ? trim($input['start_time']) : '';
$guest      = isset($input['guest']) ? trim($input['guest']) : '';
$duration   = isset($input['duration']) ? (int)$input['duration'] : 90;
$party_size = isset($input['party_size']) ? (int)$input['party_size'] : null;
$status     = isset($input['status']) ? trim($input['status']) : 'reserved';

$allowedStatuses = ['reserved','occupied','cancelled','completed'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'reserved';
}

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

// validate date
$dtDate = DateTime::createFromFormat('Y-m-d', $date);
if ($dtDate === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

// parse start datetime (accept "HH:MM" 24h)
$dtStart = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $start_time);
if ($dtStart === false) {
    // try fallback formats
    $dtStart = DateTime::createFromFormat('Y-m-d g:i A', $date . ' ' . $start_time);
    if ($dtStart === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid start_time format. Use HH:MM (24-hour)']);
        exit;
    }
}

$dtEnd = clone $dtStart;
$dtEnd->modify('+' . max(1, $duration) . ' minutes');

$start_dt = $dtStart->format('Y-m-d H:i:00'); // datetime
$end_dt   = $dtEnd->format('Y-m-d H:i:00');
$start_time_only = $dtStart->format('H:i:00');
$end_time_only   = $dtEnd->format('H:i:00');
$duration_minutes = max(1, (int)$duration);

try {
    // ensure table exists
    $stmt = $pdo->prepare("SELECT id FROM `tables` WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $table_id]);
    $tbl = $stmt->fetch();
    if (!$tbl) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Table not found']);
        exit;
    }

    // check overlapping reservations
    $sqlCheck = "
        SELECT r.id, r.table_id, r.guest, r.start, r.end, r.start_time, r.end_time, r.status
        FROM reservations r
        WHERE r.table_id = :table_id
          AND NOT (r.end <= :start_dt OR r.start >= :end_dt)
          AND r.status IN ('reserved','occupied')
        ORDER BY r.start ASC
    ";
    $stmt = $pdo->prepare($sqlCheck);
    $stmt->execute([
        ':table_id' => $table_id,
        ':start_dt' => $start_dt,
        ':end_dt'   => $end_dt
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows && count($rows) > 0) {
        // Conflict -> return 409 with details about conflicting reservations
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Table is already reserved for that time slot',
            'conflicts' => array_map(function($r) {
              return [
                'id' => (int)$r['id'],
                'table_id' => (int)$r['table_id'],
                'guest' => $r['guest'],
                'start' => $r['start'],
                'end' => $r['end'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time'],
                'status' => $r['status']
              ];
            }, $rows)
        ]);
        exit;
    }

    // compute total price if price column exists for table (optional)
    $price_per_hour = 3000.00;
    try {
      $pstmt = $pdo->prepare("SELECT IFNULL(NULLIF(price_per_hour,0), IFNULL(NULLIF(price,0), 3000.00)) AS price FROM `tables` WHERE id = :id LIMIT 1");
      $pstmt->execute([':id' => $table_id]);
      $prow = $pstmt->fetch(PDO::FETCH_ASSOC);
      if ($prow && isset($prow['price'])) $price_per_hour = (float)$prow['price'];
    } catch (Exception $e) {
      // ignore; fallback used
    }
    $hours = $duration_minutes / 60.0;
    $total_price = round($price_per_hour * $hours, 2);

    // insert (populate both time-only and datetime columns and duration_minutes)
    $sqlInsert = "
        INSERT INTO reservations
          (table_id, date, start_time, end_time, `start`, `end`, guest, party_size, status, duration_minutes, created_at)
        VALUES
          (:table_id, :date, :start_time, :end_time, :start_dt, :end_dt, :guest, :party_size, :status, :duration_minutes, NOW())
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
        ':status'     => $status,
        ':duration_minutes' => $duration_minutes
    ]);

    $newId = (int)$pdo->lastInsertId();

    // optional: if status == 'occupied', update table.status immediately
    if ($status === 'occupied') {
        $upd = $pdo->prepare("UPDATE `tables` SET `status` = 'occupied', `guest` = :guest WHERE id = :id");
        $upd->execute([':id' => $table_id, ':guest' => $guest]);
    }

    // Return success and include computed total_price (helpful for UI)
    echo json_encode(['success' => true, 'id' => $newId, 'total_price' => $total_price]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>