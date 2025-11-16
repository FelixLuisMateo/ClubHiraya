<?php
require_once __DIR__ . '/../php/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$threshold = 5; // default threshold
$out = ['ok' => true, 'low' => []];

// Replace 'inventory' and columns below with your inventory table names
$q = $conn->prepare("SELECT id, name, qty FROM inventory WHERE qty <= ? ORDER BY qty ASC LIMIT 100");
$q->bind_param('i', $threshold);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) {
    $out['low'][] = $r;
}
echo json_encode($out);
