<?php
require_once 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['order'])) {
    http_response_code(400);
    exit("Invalid data");
}

$order = $data['order'];
$totals = $data['totals'];
$payment = $data['payment'];

$stmt = $conn->prepare("INSERT INTO sales (payment_method, subtotal, service_charge, tax, discount, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    "sddddi",
    $payment['method'],
    $totals['subtotal'],
    $totals['serviceCharge'],
    $totals['tax'],
    $totals['discountAmount'],
    $totals['payable']
);
$stmt->execute();
$sale_id = $stmt->insert_id;

// Insert items
$item_stmt = $conn->prepare("INSERT INTO sales_items (sale_id, item_name, quantity, line_total) VALUES (?, ?, ?, ?)");
foreach ($order as $item) {
    $name = $item['name'];
    $qty = $item['qty'];
    $total = $item['price'] * $qty;
    $item_stmt->bind_param("isid", $sale_id, $name, $qty, $total);
    $item_stmt->execute();
}

echo json_encode(['success' => true, 'sale_id' => $sale_id]);
