<?php
// save_sale.php
// Save a sale and its line items into sales_report and sales_items tables.
// Expects JSON POST body with:
// {
//   table_no, created_by, total_amount, discount, service_charge, payment_method, note, items: [{ menu_item_id, item_name, qty, unit_price, line_total }, ...], payment_details: {...}
// }

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php'; // must expose $conn (mysqli)
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

// sanitize and default values
$table_no = isset($data['table_no']) ? $data['table_no'] : null;
$created_by = isset($data['created_by']) ? intval($data['created_by']) : null;
$total_amount = isset($data['total_amount']) ? floatval($data['total_amount']) : 0.0;
$discount = isset($data['discount']) ? floatval($data['discount']) : 0.0;
$service_charge = isset($data['service_charge']) ? floatval($data['service_charge']) : 0.0;
$payment_method = isset($data['payment_method']) ? $data['payment_method'] : null;
$note = isset($data['note']) ? $data['note'] : null;
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
$payment_details = isset($data['payment_details']) ? $data['payment_details'] : null;

// Basic server-side calculation (sum line totals) for sanity check
$items_sum = 0.0;
foreach ($items as $it) {
    $lt = isset($it['line_total']) ? floatval($it['line_total']) : (isset($it['qty'], $it['unit_price']) ? floatval($it['qty']) * floatval($it['unit_price']) : 0);
    $items_sum += $lt;
}
// We keep what client sent as total_amount but you could validate:
// $computed_payable = $items_sum + $service_charge - $discount;

// Insert using transaction
$conn->begin_transaction();

try {
    // Insert into sales_report
    $stmt = $conn->prepare("INSERT INTO sales_report (table_no, created_by, total_amount, discount, service_charge, note, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    // bind params - convert nulls to proper types
    $t_table_no = $table_no !== null ? $table_no : null;
    $t_created_by = $created_by !== null ? $created_by : null;
    $t_total = $total_amount;
    $t_discount = $discount;
    $t_service = $service_charge;
    $t_note = $note !== null ? $note : null;
    $t_payment_method = $payment_method !== null ? $payment_method : null;

    // Use 's' for table_no/note/payment_method, 'i' for created_by, 'd' for decimals
    $stmt->bind_param('siddsss', $t_table_no, $t_created_by, $t_total, $t_discount, $t_service, $t_note, $t_payment_method);
    if (!$stmt->execute()) throw new Exception('Insert sale failed: ' . $stmt->error);
    $saleId = $stmt->insert_id;
    $stmt->close();

    // Insert items
    if (!empty($items)) {
        $stmtItem = $conn->prepare("INSERT INTO sales_items (sales_id, menu_item_id, item_name, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmtItem) throw new Exception('Prepare items failed: ' . $conn->error);

        foreach ($items as $it) {
            $menu_item_id = isset($it['menu_item_id']) && $it['menu_item_id'] !== null ? intval($it['menu_item_id']) : null;
            $item_name = isset($it['item_name']) ? $it['item_name'] : (isset($it['name']) ? $it['name'] : null);
            $qty = isset($it['qty']) ? intval($it['qty']) : 1;
            $unit_price = isset($it['unit_price']) ? floatval($it['unit_price']) : 0.0;
            $line_total = isset($it['line_total']) ? floatval($it['line_total']) : ($qty * $unit_price);

            // Bind types: i (saleId), i (menu_item_id nullable -> convert to 0 if null),
            // s (item_name), i (qty), d (unit_price), d (line_total)
            $menu_item_id_bind = $menu_item_id !== null ? $menu_item_id : 0;
            $item_name_bind = $item_name !== null ? $item_name : '';
            $stmtItem->bind_param('iisdid', $saleId, $menu_item_id_bind, $item_name_bind, $qty, $unit_price, $line_total);
            if (!$stmtItem->execute()) throw new Exception('Insert item failed: ' . $stmtItem->error);
        }
        $stmtItem->close();
    }

    // Optionally store payment_details in a JSON column or separate table. For now we skip.
    // Commit
    $conn->commit();

    echo json_encode(['ok' => true, 'id' => $saleId]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}