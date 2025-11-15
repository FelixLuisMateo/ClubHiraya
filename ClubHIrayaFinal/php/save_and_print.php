<?php
session_start();
$created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$raw = json_decode(file_get_contents('php://input'), true);
if (!$raw) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
}

/* ------------------------
   INPUT / NORMALIZE
-------------------------*/
$items  = $raw['items'] ?? [];
$totals = $raw['totals'] ?? [];

/* Raw payment method (used for database) */
$rawPaymentMethod = strtolower(trim($raw['payment_method'] ?? 'cash'));

/* Receipt-friendly label (for printing only) */
$paymentMethodLabel = ($rawPaymentMethod === 'cash') ? 'Cash Payment' : ucfirst($rawPaymentMethod);

/* Payment details JSON from client (may be object for gcash/bank or include 'given'/'change' for cash) */
$payment_details_raw = $raw['payment_details'] ?? [];

/* Table / cabin */
$table = $raw['table'] ?? null;
$note  = trim($raw['note'] ?? '');

/* Totals */
$subtotal       = floatval($totals['subtotal'] ?? 0);
$service_charge = floatval($totals['serviceCharge'] ?? 0);
$tax            = floatval($totals['tax'] ?? 0);
$discount       = floatval($totals['discountAmount'] ?? 0);
$payable        = floatval($totals['payable'] ?? 0);
$discount_type  = $totals['discountType'] ?? 'Regular';

/* Cabin details */
$cabin_name  = '';
$cabin_price = 0.0;
$table_no    = null;

if (is_array($table)) {
    $cabin_name  = strval($table['name'] ?? $table['table'] ?? $table['table_number'] ?? '');
    $cabin_price = floatval($table['price'] ?? $table['price_php'] ?? $table['table_price'] ?? 0);
    $table_no    = $cabin_name;
} else {
    if ($table !== null) $table_no = strval($table);
    $cabin_name = $cabin_name ?: '';
    $cabin_price = floatval($cabin_price);
}

/* Cash / change - ensure numeric values.
   Important: use $rawPaymentMethod to decide (this is the DB value).
*/
$cash_given = 0.0;
$change_amount = 0.0;
$payment_details_to_store = null;

if ($rawPaymentMethod === 'cash' || $rawPaymentMethod === 'cash_payment') {
    // When client sends cash, they should put given/change in payment_details or top-level fields
    $cash_given    = floatval($payment_details_raw['given'] ?? $raw['cash_given'] ?? $raw['cashGiven'] ?? 0);
    $change_amount = floatval($payment_details_raw['change'] ?? $raw['change_amount'] ?? $raw['change'] ?? 0);

    // Do not store cash info in payment_details JSON
    $payment_details_to_store = null;
} else {
    // For gcash/bank we keep the provided name/ref JSON (if present)
    if (is_array($payment_details_raw) && count($payment_details_raw)) {
        $payment_details_to_store = $payment_details_raw;
    } else {
        $payment_details_to_store = null;
    }
}

/* Prepare JSON (or empty string) - we'll store empty string when no JSON to avoid bind/NULL issues */
$payment_details_json = $payment_details_to_store === null ? '' : json_encode($payment_details_to_store, JSON_UNESCAPED_UNICODE);

/* Normalize values */
$cabin_name = $cabin_name ?? '';
$cabin_price = floatval($cabin_price);
$cash_given = floatval($cash_given);
$change_amount = floatval($change_amount);

/* Start transaction */
$conn->begin_transaction();

try {

    // -----------------------
    // INSERT sales_report
    // -----------------------
    $sql = "INSERT INTO sales_report 
        (table_no, created_by, total_amount, discount, service_charge, note,
         payment_method, payment_details, subtotal, tax, discount_type,
         cash_given, change_amount, cabin_name, cabin_price)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('prepare_failed_sales_report: ' . $conn->error);

    // Bind types (15 params):
    // s - table_no
    // i - created_by
    // d - total_amount
    // d - discount
    // d - service_charge
    // s - note
    // s - payment_method (raw)
    // s - payment_details_json (possibly empty string)
    // d - subtotal
    // d - tax
    // s - discount_type
    // d - cash_given
    // d - change_amount
    // s - cabin_name
    // d - cabin_price
    $types = 'sidddsssddsdds'; // we'll build correct below to avoid mistakes

    // Construct correct types string explicitly:
    $types = 'sidddsssddsddsd'; // s i d d d s s s d d s d d s d  (15 chars)

    // Variables to bind
    $b_table_no = $table_no;
    $b_created_by = $created_by !== null ? intval($created_by) : 0;
    $b_total_amount = $payable;
    $b_discount = $discount;
    $b_service_charge = $service_charge;
    $b_note = $note;
    $b_payment_method = $rawPaymentMethod; // store raw value 'cash'|'gcash'|'bank_transfer'
    $b_payment_details = $payment_details_json;
    $b_subtotal = $subtotal;
    $b_tax = $tax;
    $b_discount_type = $discount_type;
    $b_cash_given = $cash_given;
    $b_change_amount = $change_amount;
    $b_cabin_name = $cabin_name;
    $b_cabin_price = $cabin_price;

    if (!$stmt->bind_param(
        $types,
        $b_table_no,
        $b_created_by,
        $b_total_amount,
        $b_discount,
        $b_service_charge,
        $b_note,
        $b_payment_method,
        $b_payment_details,
        $b_subtotal,
        $b_tax,
        $b_discount_type,
        $b_cash_given,
        $b_change_amount,
        $b_cabin_name,
        $b_cabin_price
    )) {
        throw new Exception('bind_param_failed_sales_report: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('execute_failed_sales_report: ' . $stmt->error);
    }

    $sales_id = $stmt->insert_id;
    $stmt->close();

    // -----------------------
    // INSERT sales_items
    // -----------------------
    $withIdSql = "INSERT INTO sales_items (sales_id, menu_item_id, item_name, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)";
    $noIdSql   = "INSERT INTO sales_items (sales_id, item_name, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?)";

    $withIdStmt = $conn->prepare($withIdSql);
    if (!$withIdStmt) throw new Exception('prepare_failed_sales_items_with_id: ' . $conn->error);

    $noIdStmt = $conn->prepare($noIdSql);
    if (!$noIdStmt) throw new Exception('prepare_failed_sales_items_no_id: ' . $conn->error);

    foreach ($items as $it) {
        $menu_item_id = null;
        if (isset($it['menu_item_id']) && $it['menu_item_id'] !== '' && $it['menu_item_id'] !== null) {
            if (is_numeric($it['menu_item_id'])) $menu_item_id = intval($it['menu_item_id']);
        }

        $name = isset($it['item_name']) ? $it['item_name'] : ($it['name'] ?? '');
        $qty = isset($it['qty']) ? intval($it['qty']) : 1;
        $unit_price = isset($it['unit_price']) ? floatval($it['unit_price']) : (isset($it['price']) ? floatval($it['price']) : 0.0);
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

    // Commit
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
