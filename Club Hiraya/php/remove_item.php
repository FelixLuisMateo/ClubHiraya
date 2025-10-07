<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id']);
foreach ($_SESSION['order'] as $k => $item) {
    if ($item['id'] == $id) {
        unset($_SESSION['order'][$k]);
        $_SESSION['order'] = array_values($_SESSION['order']); // Re-index
        break;
    }
}
echo json_encode(['order'=>$_SESSION['order']]);