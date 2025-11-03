<?php
// api/create_order.php
// Accepts JSON { items: [{id, qty, price?}], discount?, note?, idempotency_key?, currency?, exchange_rate? }
// Returns { success:true, order_id, already_exists:false } or error
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php'; // expect $pdo

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload (items required)']);
    exit;
}

$items = $data['items'];
$note = isset($data['note']) ? trim($data['note']) : '';
$discount = isset($data['discount']) ? floatval($data['discount']) : 0.0;
$idempotency = isset($data['idempotency_key']) ? trim($data['idempotency_key']) : '';
$currency = isset($data['currency']) ? trim($data['currency']) : 'PHP';
$exchange_rate = isset($data['exchange_rate']) ? $data['exchange_rate'] : null; // expect array like ['code'=>'USD','rate'=>0.017]

// idempotency: return existing order_id if the same key exists
try {
    if ($idempotency !== '') {
        $stmt = $pdo->prepare('SELECT id FROM orders WHERE idempotency_key = :k LIMIT 1');
        $stmt->execute([':k' => $idempotency]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) {
            echo json_encode(['success' => true, 'order_id' => (int)$row['id'], 'already_exists' => true]);
            exit;
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO orders (idempotency_key, currency, exchange_rate, discount, note, status, created_at) VALUES (:k, :currency, :exchange_rate, :discount, :note, :status, NOW())');
    $stmt->execute([
        ':k' => $idempotency ?: null,
        ':currency' => $currency,
        ':exchange_rate' => $exchange_rate ? json_encode($exchange_rate, JSON_UNESCAPED_UNICODE) : null,
        ':discount' => $discount,
        ':note' => $note,
        ':status' => 'pending'
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, food_id, qty, unit_price) VALUES (:order_id, :food_id, :qty, :unit_price)');
    foreach ($items as $it) {
        $fid = isset($it['id']) ? (int)$it['id'] : 0;
        $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
        $unitPrice = isset($it['price']) ? floatval($it['price']) : 0.0; // price in PHP captured at order time
        if ($fid <= 0 || $qty <= 0) continue;
        $insertItem->execute([':order_id' => $orderId, ':food_id' => $fid, ':qty' => $qty, ':unit_price' => $unitPrice]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'order_id' => $orderId, 'already_exists' => false]);
    exit;
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>