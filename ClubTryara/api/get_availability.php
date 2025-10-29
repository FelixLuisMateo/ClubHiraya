<?php
// api/get_availability.php
// Returns a list of available tables for a requested date/time/duration and party size.
//
// Query params supported (GET):
// - date=YYYY-MM-DD (optional)
// - time=HH:MM (optional)
// - duration=minutes (optional, default 90)
// - seats=N (optional, default 1)
//
// Behavior:
// - If date+time provided: checks reservations table for overlaps and excludes booked tables.
// - If reservations table is missing or date/time omitted: falls back to selecting tables with status='available' and seats >= requested seats.
//
// Note: Adjust timezone handling as needed for your deployment.

header('Content-Type: application/json; charset=utf-8');

// DEV: show errors in development. Remove in production.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php'; // expects $pdo

// Read and validate inputs
$date = isset($_GET['date']) ? trim($_GET['date']) : null; // YYYY-MM-DD
$time = isset($_GET['time']) ? trim($_GET['time']) : null; // HH:MM
$duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 90;
$seats = isset($_GET['seats']) ? max(1, (int)$_GET['seats']) : 1;

try {
    // If date+time supplied, compute start/end datetimes (server local timezone)
    $hasDateTime = false;
    if ($date && $time) {
        // naive parsing: combine date and time; ensure it's a valid datetime
        $dtString = $date . ' ' . $time . ':00';
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dtString);
        if ($dt === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid date or time format']);
            exit;
        }
        // Optionally convert timezone here if you store reservations in UTC.
        // For now we assume server and DB use same timezone.
        $start = $dt->format('Y-m-d H:i:s');
        $end = (clone $dt)->modify("+{$duration} minutes")->format('Y-m-d H:i:s');
        $hasDateTime = true;
    }

    // Primary approach: query reservations to find overlapping bookings and exclude those table_ids
    if ($hasDateTime) {
        // Try the reservations-based availability query
        $sql = "
            SELECT t.id, t.name, t.seats, t.status
            FROM `tables` AS t
            WHERE t.seats >= :seats
              AND t.id NOT IN (
                SELECT r.table_id
                FROM reservations r
                WHERE r.status <> 'cancelled'
                  AND r.start < :end_dt
                  AND r.end > :start_dt
              )
            ORDER BY t.seats ASC, t.id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':seats' => $seats, ':start_dt' => $start, ':end_dt' => $end]);
        $rows = $stmt->fetchAll();
        // If rows returned, great — return them.
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    } else {
        // No date/time specified — fallback to tables currently marked as available (status)
        $stmt = $pdo->prepare("SELECT id, name, seats, status FROM `tables` WHERE seats >= :seats AND status = 'available' ORDER BY seats ASC, id ASC");
        $stmt->execute([':seats' => $seats]);
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }
} catch (PDOException $e) {
    // If reservations table doesn't exist or another DB error, try a graceful fallback:
    // - return tables where status = 'available' and seats >= requested seats
    try {
        $stmt = $pdo->prepare("SELECT id, name, seats, status FROM `tables` WHERE seats >= :seats AND status = 'available' ORDER BY seats ASC, id ASC");
        $stmt->execute([':seats' => $seats]);
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows, 'note' => 'Fallback used: reservations table unavailable or DB error']);
        exit;
    } catch (Exception $e2) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}