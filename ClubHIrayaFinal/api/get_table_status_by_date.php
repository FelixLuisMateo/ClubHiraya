<?php
    // api/get_table_status_by_date.php
// GET parameters: date=YYYY-MM-DD

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$date = isset($_GET['date']) ? $_GET['date'] : '';
if (!$date) {
    echo json_encode(['success'=>false, 'error'=>'Missing date']);
    exit;
}

// For each table, try to get a reservation for the selected date
// Include reservation id and start/end datetimes so frontend can notify precisely.
$sql = "
SELECT t.id AS table_id, t.name, t.seats,
       IFNULL(r.status, 'available') AS status,
       r.guest,
       r.start_time,
       r.end_time,
       r.id AS reservation_id,
       r.`start` AS start_dt,
       r.`end` AS end_dt
FROM tables t
LEFT JOIN reservations r
  ON t.id = r.table_id AND r.date = :date AND r.status IN ('reserved','occupied')
ORDER BY t.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':date' => $date]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success'=>true, 'data'=>$data]);