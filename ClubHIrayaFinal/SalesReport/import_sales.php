<?php
// import_sales.php
// Import JSON exported by export_sales.php
// Safe to run multiple times: will skip exact IDs already present in sales_report and sales_items.
// Place into same folder as admin PHP files.

require_once __DIR__ . '/php/db_connect.php';
date_default_timezone_set('Asia/Manila');

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection not available']);
    exit;
}

session_start();
// optionally restrict to admin
// if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { ... }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><title>Import Sales</title></head><body>
      <h3>Import sales JSON</h3>
      <form method="post" enctype="multipart/form-data">
        <input type="file" name="salesfile" accept=".json,application/json" required>
        <button type="submit">Upload & Import</button>
      </form>
    </body></html>
    <?php
    exit;
}

if (!isset($_FILES['salesfile']) || $_FILES['salesfile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$raw = file_get_contents($_FILES['salesfile']['tmp_name']);
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON file']);
    exit;
}

// Start transaction
$conn->begin_transaction();
try {
    $importedSales = 0;
    $importedItems = 0;
    $idMap = []; // oldSaleId => newSaleId (if changed)

    // Insert sales_report
    if (!empty($data['sales_report']) && is_array($data['sales_report'])) {
        foreach ($data['sales_report'] as $sr) {
            // If row has id and that id already exists, skip
            $oldId = isset($sr['id']) ? intval($sr['id']) : 0;
            if ($oldId) {
                $check = $conn->prepare("SELECT id FROM sales_report WHERE id = ?");
                $check->bind_param('i', $oldId);
                $check->execute();
                $r = $check->get_result();
                if ($r && $r->num_rows) {
                    // existing — map to same id, skip insertion
                    $idMap[$oldId] = $oldId;
                    $check->close();
                    continue;
                }
                $check->close();
            }

            // Build insert for sales_report (only columns present in your schema)
            $stmt = $conn->prepare("INSERT INTO sales_report
                (id, created_at, table_no, created_by, total_amount, discount, service_charge, note, payment_method, payment_details, subtotal, tax, discount_type, cash_given, change_amount, cabin_name, cabin_price, status, voided_at, voided_by, is_voided)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

            // prepare variables (set defaults when missing)
            $idVal = $oldId ?: null;
            $created_at = $sr['created_at'] ?? date('Y-m-d H:i:s');
            $table_no = $sr['table_no'] ?? null;
            $created_by = isset($sr['created_by']) ? intval($sr['created_by']) : null;
            $total_amount = $sr['total_amount'] ?? 0;
            $discount = $sr['discount'] ?? 0;
            $service_charge = $sr['service_charge'] ?? 0;
            $note = $sr['note'] ?? null;
            $payment_method = $sr['payment_method'] ?? null;
            $payment_details = $sr['payment_details'] ?? null;
            $subtotal = $sr['subtotal'] ?? null;
            $tax = $sr['tax'] ?? null;
            $discount_type = $sr['discount_type'] ?? null;
            $cash_given = $sr['cash_given'] ?? 0;
            $change_amount = $sr['change_amount'] ?? 0;
            $cabin_name = $sr['cabin_name'] ?? null;
            $cabin_price = $sr['cabin_price'] ?? 0;
            $status = $sr['status'] ?? 'completed';
            $voided_at = $sr['voided_at'] ?? null;
            $voided_by = $sr['voided_by'] ?? null;
            $is_voided = isset($sr['is_voided']) ? intval($sr['is_voided']) : 0;

            // If id provided, attempt to insert with that id (MySQL allows inserting explicit id if not present)
            // Use "i" type for id if provided, else null (but bind_param requires var — we pass as int or NULL)
            $stmt->bind_param(
                "issidddssssdssdssdssi",
                $idVal,
                $created_at,
                $table_no,
                $created_by,
                $total_amount,
                $discount,
                $service_charge,
                $note,
                $payment_method,
                $payment_details,
                $subtotal,
                $tax,
                $discount_type,
                $cash_given,
                $change_amount,
                $cabin_name,
                $cabin_price,
                $status,
                $voided_at,
                $voided_by,
                $is_voided
            );

            if (!$stmt->execute()) {
                // If inserting with explicit id fails (e.g. because id was NULL), try insert without id
                // But to be robust, re-prepare without id column
                $stmt->close();
                $stmt2 = $conn->prepare("INSERT INTO sales_report
                    (created_at, table_no, created_by, total_amount, discount, service_charge, note, payment_method, payment_details, subtotal, tax, discount_type, cash_given, change_amount, cabin_name, cabin_price, status, voided_at, voided_by, is_voided)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt2) throw new Exception('Prepare2 failed: ' . $conn->error);
                $stmt2->bind_param(
                    "sisdddssssdssdssdssi",
                    $created_at,
                    $table_no,
                    $created_by,
                    $total_amount,
                    $discount,
                    $service_charge,
                    $note,
                    $payment_method,
                    $payment_details,
                    $subtotal,
                    $tax,
                    $discount_type,
                    $cash_given,
                    $change_amount,
                    $cabin_name,
                    $cabin_price,
                    $status,
                    $voided_at,
                    $voided_by,
                    $is_voided
                );
                if (!$stmt2->execute()) throw new Exception('Insert sales_report failed: ' . $stmt2->error);
                $newId = $stmt2->insert_id;
                $idMap[$oldId] = $newId;
                $importedSales++;
                $stmt2->close();
            } else {
                $newId = $idVal ?: $stmt->insert_id;
                $idMap[$oldId] = $newId;
                $importedSales++;
                $stmt->close();
            }
        }
    }

    // Insert sales_items and map sales_id using $idMap if necessary
    if (!empty($data['sales_items']) && is_array($data['sales_items'])) {
        foreach ($data['sales_items'] as $si) {
            $oldSalesId = isset($si['sales_id']) ? intval($si['sales_id']) : (isset($si['sale_id']) ? intval($si['sale_id']) : 0);
            $targetSalesId = $oldSalesId && isset($idMap[$oldSalesId]) ? $idMap[$oldSalesId] : $oldSalesId;

            // If the target sales id does not exist now in DB, skip item
            if ($targetSalesId) {
                $chk = $conn->prepare("SELECT id FROM sales_report WHERE id = ?");
                $chk->bind_param('i', $targetSalesId);
                $chk->execute();
                $rr = $chk->get_result();
                if (!$rr || $rr->num_rows === 0) {
                    $chk->close();
                    continue;
                }
                $chk->close();
            } else {
                // skip items without a mapped sale
                continue;
            }

            // Avoid duplicate items: basic check by identical row (sales_id + item_name + qty + line_total)
            $dupChk = $conn->prepare("SELECT id FROM sales_items WHERE sales_id = ? AND (item_name = ? OR item_name = ?) AND qty = ? AND line_total = ? LIMIT 1");
            $itemName = $si['item_name'] ?? ($si['name'] ?? '');
            $dupChk->bind_param('issid', $targetSalesId, $itemName, $itemName, $si['qty'] ?? 0, $si['line_total'] ?? 0);
            $dupChk->execute();
            $dr = $dupChk->get_result();
            if ($dr && $dr->num_rows) {
                $dupChk->close();
                continue;
            }
            $dupChk->close();

            $stmtItem = $conn->prepare("INSERT INTO sales_items (sales_id, menu_item_id, item_name, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmtItem) throw new Exception('Prepare item failed: ' . $conn->error);

            $menu_item_id = isset($si['menu_item_id']) ? intval($si['menu_item_id']) : 0;
            $qty = isset($si['qty']) ? intval($si['qty']) : 1;
            $unit_price = isset($si['unit_price']) ? (float)$si['unit_price'] : 0;
            $line_total = isset($si['line_total']) ? (float)$si['line_total'] : ($unit_price * $qty);

            $stmtItem->bind_param('iisiid', $targetSalesId, $menu_item_id, $itemName, $qty, $unit_price, $line_total);
            if (!$stmtItem->execute()) {
                $stmtItem->close();
                throw new Exception('Insert item failed: ' . $conn->error . ' / ' . $stmtItem->error);
            }
            $importedItems++;
            $stmtItem->close();
        }
    }

    $conn->commit();
    echo json_encode(['ok' => true, 'imported_sales' => $importedSales, 'imported_items' => $importedItems]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
