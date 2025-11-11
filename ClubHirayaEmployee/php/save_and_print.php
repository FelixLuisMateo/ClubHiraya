<?php
// php/save_and_print.php
// Receives JSON payload and writes into sales_report and sales_items.
// Returns JSON: { ok: true, id: <sales_report_id> } or { ok:false, error: ... }.

require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$raw = json_decode(file_get_contents('php://input'), true);
if (!$raw) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
}

$items = $raw['items'] ?? [];
$totals = $raw['totals'] ?? [];
$payment_method = $raw['payment_method'] ?? 'cash';
$payment_details = $raw['payment_details'] ?? null;
$table = $raw['table'] ?? null;
$created_by = $raw['created_by'] ?? null;
$note = $raw['note'] ?? '';

$subtotal = isset($totals['subtotal']) ? floatval($totals['subtotal']) : 0.0;
$service_charge = isset($totals['serviceCharge']) ? floatval($totals['serviceCharge']) : 0.0;
$tax = isset($totals['tax']) ? floatval($totals['tax']) : 0.0;
$discount = isset($totals['discountAmount']) ? floatval($totals['discountAmount']) : 0.0;
$payable = isset($totals['payable']) ? floatval($totals['payable']) : 0.0;

$table_no = null;
if (is_array($table)) {
    if (isset($table['table'])) $table_no = $table['table'];
    elseif (isset($table['table_number'])) $table_no = $table['table_number'];
    elseif (isset($table['name'])) $table_no = $table['name'];
} elseif (is_scalar($table)) {
    $table_no = $table;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Insert into sales_report
    $stmt = $conn->prepare(
        "INSERT INTO sales_report (table_no, created_by, total_amount, discount, service_charge, note, payment_method)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) throw new Exception('prepare_failed_sales_report: ' . $conn->error);

    $bind_table_no = $table_no;
    $bind_created_by = $created_by;
    $bind_total_amount = $payable;
    $bind_discount = $discount;
    $bind_service_charge = $service_charge;
    $bind_note = $note;
    $bind_payment_method = $payment_method;

    // types: s (table_no), i (created_by), d (total_amount), d (discount), d (service_charge), s (note), s (payment_method)
    if (!$stmt->bind_param('sidddss',
        $bind_table_no,
        $bind_created_by,
        $bind_total_amount,
        $bind_discount,
        $bind_service_charge,
        $bind_note,
        $bind_payment_method
    )) {
        throw new Exception('bind_failed_sales_report: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('execute_failed_sales_report: ' . $stmt->error);
    }
    $sales_id = $stmt->insert_id;
    $stmt->close();

    // Prepare item insert statements
    $withIdSql = "INSERT INTO sales_items (sales_id, menu_item_id, item_name, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)";
    $withIdStmt = $conn->prepare($withIdSql);
    if (!$withIdStmt) throw new Exception('prepare_failed_sales_items_with_id: ' . $conn->error);

    $noIdSql = "INSERT INTO sales_items (sales_id, item_name, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?)";
    $noIdStmt = $conn->prepare($noIdSql);
    if (!$noIdStmt) throw new Exception('prepare_failed_sales_items_no_id: ' . $conn->error);

    foreach ($items as $it) {
        $menu_item_id = null;
        if (isset($it['menu_item_id']) && $it['menu_item_id'] !== '' && $it['menu_item_id'] !== null) {
            if (is_numeric($it['menu_item_id'])) $menu_item_id = intval($it['menu_item_id']);
            else $menu_item_id = null;
        }
        $name = isset($it['item_name']) ? $it['item_name'] : ($it['name'] ?? '');
        $qty = isset($it['qty']) ? intval($it['qty']) : 1;
        $unit_price = isset($it['unit_price']) ? floatval($it['unit_price']) : 0.0;
        $line_total = isset($it['line_total']) ? floatval($it['line_total']) : ($qty * $unit_price);

        if ($menu_item_id === null) {
            if (!$noIdStmt->bind_param('isidd', $sales_id, $name, $qty, $unit_price, $line_total)) {
                throw new Exception('bind_failed_sales_items_no_id: ' . $noIdStmt->error);
            }
            if (!$noIdStmt->execute()) {
                throw new Exception('execute_failed_sales_items_no_id: ' . $noIdStmt->error);
            }
        } else {
            if (!$withIdStmt->bind_param('iisidd', $sales_id, $menu_item_id, $name, $qty, $unit_price, $line_total)) {
                throw new Exception('bind_failed_sales_items_with_id: ' . $withIdStmt->error);
            }
            if (!$withIdStmt->execute()) {
                throw new Exception('execute_failed_sales_items_with_id: ' . $withIdStmt->error);
            }
        }
    }

    $withIdStmt->close();
    $noIdStmt->close();

    // Append payment details to note for traceability
    if (!empty($payment_details)) {
        $j = json_encode($payment_details, JSON_UNESCAPED_UNICODE);
        $upd = $conn->prepare("UPDATE sales_report SET note = CONCAT(IFNULL(note,''), ?) WHERE id = ?");
        if ($upd) {
            $append = "\nPayment Details: " . $j;
            $upd->bind_param('si', $append, $sales_id);
            $upd->execute();
            $upd->close();
        }
    }

    $conn->commit();
    echo json_encode(['ok' => true, 'id' => $sales_id]);
    exit;
} catch (Exception $ex) {
    $conn->rollback();
    error_log("save_and_print error: " . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
    exit;
}
?>