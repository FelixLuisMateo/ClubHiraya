<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id']);
$delta = intval($data['delta']);
foreach ($_SESSION['order'] as $k => &$item) {
    if ($item['id'] == $id) {
        $item['qty'] = $item['qty'] + $delta;
        if ($item['qty'] <= 0) {
            unset($_SESSION['order'][$k]); // Remove if qty is 0 or less
            $_SESSION['order'] = array_values($_SESSION['order']); // Re-index
        } else {
            $item['qty'] = max(1, $item['qty']); // Prevent negatives
        }
        break;
    }
}
echo json_encode(['order'=>$_SESSION['order']]);