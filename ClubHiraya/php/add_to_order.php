<?php
session_start();
require_once "foods.php";
if (!isset($_SESSION['order'])) $_SESSION['order'] = [];
$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id']);
$found = false;
foreach ($_SESSION['order'] as &$item) {
    if ($item['id'] == $id) {
        $item['qty'] += 1;
        $found = true;
        break;
    }
}
if (!$found) {
    foreach ($foods as $food) {
        if ($food['id'] == $id) {
            $_SESSION['order'][] = ['id'=>$food['id'], 'name'=>$food['name'], 'price'=>$food['price'], 'image'=>$food['image'], 'qty'=>1];
            break;
        }
    }
}
echo json_encode(['order'=>$_SESSION['order']]);