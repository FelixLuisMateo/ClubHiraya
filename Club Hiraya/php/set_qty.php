<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id']);
$qty = max(1, intval($data['qty']));
foreach ($_SESSION['order'] as &$item) {
    if ($item['id'] == $id) {
        $item['qty'] = $qty;
        break;
    }
}
echo json_encode(['order'=>$_SESSION['order']]);