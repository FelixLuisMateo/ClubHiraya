<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection not available']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

/* --------------------------
   READ / NORMALIZE INPUT
--------------------------- */
$table_no        = $data['table_no']        ?? '';
$created_by      = isset($data['created_by']) ? intval($data['created_by']) : 0;
$total_amount    = floatval($data['total_amount']    ?? 0);
$discount        = floatval($data['discount']        ?? 0);
$service_charge  = floatval($data['service_charge']  ?? 0);
$payment_method  = strtolower($data['payment_method'] ?? '');
$note            = trim($data['note']       ?? '');

$subtotal        = floatval($data['subtotal']       ?? 0);
$tax             = floatval($data['tax']            ?? 0);
$discount_type   = $data['discount_type']           ?? 'Regular';

$cash_given      = floatval($data['cash_given']     ?? 0);
$change_amount   = floatval($data['change_amount']  ?? 0);

$cabin_name      = $data['cabin_name']              ?? '';
$cabin_price     = floatval($data['cabin_price']    ?? 0);

$payment_details = $data['payment_details'] ?? null;
$items           = (isset($data['items']) && is_array($data['items'])) ? $data['items'] : [];

// convert JSON to string if not null
$payment_details_json = $payment_details ? json_encode($payment_details, JSON_UNESCAPED_UNICODE) : '';

/* --------------------------
   DB TRANSACTION
--------------------------- */
$conn->begin_transaction();

try {

    /* ------------------------------------
       INSERT INTO sales_report (15 columns)
    ------------------------------------- */
    $sql = "INSERT INTO sales_report
        (table_no, created_by, total_amount, discount, service_charge, note,
         payment_method, payment_details, subtotal, tax, discount_type,
         cash_given, change_amount, cabin_name, cabin_price)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    $types = "sidddsssddsddsd"; 
    //  s i d d d s s s d d s d d s d = 15 params

    $stmt->bind_param(
        $types,
        $table_no,
        $created_by,
        $total_amount,
        $discount,
        $service_charge,
        $note,
        $payment_method,
        $payment_details_json,
        $subtotal,
        $tax,
        $discount_type,
        $cash_given,
        $change_amount,
        $cabin_name,
        $cabin_price
    );

    if (!$stmt->execute()) {
        throw new Exception("Insert sale failed: " . $stmt->error);
    }

    $saleId = $stmt->insert_id;
    $stmt->close();

    /* --------------------------
       INSERT sale items
    --------------------------- */
    if (!empty($items)) {
        $stmtItem = $conn->prepare(
            "INSERT INTO sales_items 
            (sales_id, menu_item_id, item_name, qty, unit_price, line_total)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$stmtItem)
            throw new Exception('Prepare sale item failed: ' . $conn->error);

        foreach ($items as $it) {

            $menu_item_id = isset($it['menu_item_id']) && $it['menu_item_id'] !== null
                ? intval($it['menu_item_id'])
                : 0;

            $item_name = $it['item_name'] ?? $it['name'] ?? '';
            $qty = intval($it['qty'] ?? 1);
            $unit_price = floatval($it['unit_price'] ?? 0);
            $line_total = floatval($it['line_total'] ?? ($unit_price * $qty));

            $stmtItem->bind_param(
                "iisidd",
                $saleId,
                $menu_item_id,
                $item_name,
                $qty,
                $unit_price,
                $line_total
            );

            if (!$stmtItem->execute()) {
                throw new Exception("Insert item failed: " . $stmtItem->error);
            }
        }

        $stmtItem->close();
    }

    $conn->commit();
    echo json_encode(['ok' => true, 'id' => $saleId]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

?>
