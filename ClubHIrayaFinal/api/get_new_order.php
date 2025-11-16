<?php
require_once __DIR__ . '/../php/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$since = isset($_GET['since']) ? intval($_GET['since']) : 0;
if ($since < 0) $since = 0;

$out = ['ok' => true, 'newest_id' => $since, 'new_orders' => []];

$q = $conn->prepare("SELECT id, created_at, total_amount, table_no, cabin_name FROM sales_report WHERE id > ? AND status = 'completed' ORDER BY id ASC LIMIT 200");
$q->bind_param('i', $since);
$q->execute();
$res = $q->get_result();
$maxId = $since;
while ($r = $res->fetch_assoc()) {
    $out['new_orders'][] = $r;
    $maxId = max($maxId, intval($r['id']));
}
$out['newest_id'] = $maxId;
echo json_encode($out);
