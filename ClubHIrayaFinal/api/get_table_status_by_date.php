<?php
// api/get_table_status_by_date.php
// Robust version that checks for price columns and returns one reservation per table for a given date.
//
// GET parameter: date=YYYY-MM-DD
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

$date = isset($_GET['date']) ? trim($_GET['date']) : '';
if (!$date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing date']);
    exit;
}

try {
    // Inspect information_schema to see which pricing columns exist in `tables`
    $colStmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'tables'
          AND COLUMN_NAME IN ('price_per_hour', 'price')
    ");
    $colStmt->execute();
    $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $has_price_per_hour = in_array('price_per_hour', $cols, true);
    $has_price = in_array('price', $cols, true);

    // Build a safe price expression depending on which columns exist.
    // Use COALESCE so if first is NULL we fall back to next.
    if ($has_price_per_hour && $has_price) {
        // prefer price_per_hour, fall back to price, then 3000.00
        $price_expr = "COALESCE(t.price_per_hour, t.price, 3000.00)";
    } elseif ($has_price_per_hour) {
        $price_expr = "COALESCE(t.price_per_hour, 3000.00)";
    } elseif ($has_price) {
        $price_expr = "COALESCE(t.price, 3000.00)";
    } else {
        // neither column exists — return a constant default
        $price_expr = "3000.00";
    }

    // Use a correlated subquery to pick a single reservation per table (if any) for the requested date.
    // This ensures we don't accidentally join to multiple reservation rows per table.
    $sql = "
    SELECT
      t.id AS id,
      t.name,
      t.seats,
      -- Prefer the table row status if it's occupied (persistent), otherwise use reservation status when present.
      CASE
        WHEN t.status = 'occupied' THEN 'occupied'
        WHEN r.status IS NOT NULL THEN r.status
        ELSE 'available'
      END AS status,
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
      {$price_expr} AS price_per_hour
    FROM `tables` t
    LEFT JOIN reservations r ON r.id = (
        SELECT r2.id FROM reservations r2
        WHERE r2.table_id = t.id
          AND r2.date = :date
          AND r2.status IN ('reserved','occupied')
        ORDER BY r2.start ASC
        LIMIT 1
    )
    ORDER BY t.id ASC
    ";

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