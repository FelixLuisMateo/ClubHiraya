<?php
// api/get_table_status_by_date.php
// GET parameter: date=YYYY-MM-DD
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$date = isset($_GET['date']) ? trim($_GET['date']) : '';
if (!$date) {
    echo json_encode(['success' => false, 'error' => 'Missing date']);
    exit;
}

// Return one (the nearest) reservation for each table on that date (if any).
// We alias fields to the names the frontend expects and include duration_minutes and total_price.
$sql = "
SELECT
  t.id AS id,
  t.id AS table_id,
  t.name,
  t.seats,
  IFNULL(r.status, 'available') AS status,
  IFNULL(r.guest, '') AS guest,
  r.start_time,
  r.end_time,
  r.id AS reservation_id,
  r.`start` AS start_dt,
  r.`end` AS end_dt,
  CASE WHEN r.`start` IS NOT NULL AND r.`end` IS NOT NULL
       THEN TIMESTAMPDIFF(MINUTE, r.`start`, r.`end`)
       ELSE r.duration_minutes
  END AS duration_minutes,
  IFNULL(r.total_price, NULL) AS total_price,
  IFNULL(t.price_per_hour, 3000.00) AS price_per_hour
FROM `tables` t
LEFT JOIN (
    -- pick one reservation for the table on the requested date (if any).
    SELECT r1.*
    FROM reservations r1
    WHERE r1.date = :date AND r1.status IN ('reserved','occupied')
    ORDER BY r1.start ASC
) r ON r.table_id = t.id
ORDER BY t.id ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':date' => $date]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>