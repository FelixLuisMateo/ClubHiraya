<?php
// print_receipt_reprint.php
// Uses DB sales_report row — ensure it prints Reserved, payer/ref, cash/change consistently.
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

/* -----------------------------
   RECEIPT ID (from GET)
------------------------------*/
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "<h2>Invalid Order ID</h2>";
    exit;
}

/* -----------------------------
   FETCH ORDER
------------------------------*/
$stmt = $conn->prepare("SELECT * FROM sales_report WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "<h2>Order not found.</h2>";
    exit;
}

/* -----------------------------
   FETCH ORDER ITEMS
------------------------------*/
$items = [];
$stmt = $conn->prepare("SELECT * FROM sales_items WHERE sales_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $items[] = $row;
$stmt->close();

/* PHP PESO FORMAT */
function fmt($n) { return '₱' . number_format((float)$n, 2); }

/* -----------------------------
   REFORMAT ORDER DATA
------------------------------*/
$date = date('F d, Y h:i:s A', strtotime($order['created_at'] ?? ''));

// payment method normalization
$rawPm = strtolower((string)($order['payment_method'] ?? $order['method'] ?? 'cash'));
if (strpos($rawPm, 'gcash') !== false) $paymentMethod = 'GCash';
elseif (strpos($rawPm, 'bank') !== false || strpos($rawPm, 'transfer') !== false) $paymentMethod = 'Bank Transfer';
else $paymentMethod = 'Cash';

// discount type + rate
$discountType = $order['discount_type'] ?? 'Regular';
$lower = strtolower((string)$discountType);
$discountRate = (in_array($lower, ['senior','senior citizen','pwd'])) ? 20 : 0;

// cabin / reserved detection (DB columns may vary)
$cabinName  = $order['cabin_name'] ?? $order['table_no'] ?? 'No Cabin Selected';
$cabinPrice = floatval($order['cabin_price'] ?? $order['table_price'] ?? $order['reserved_price'] ?? 0);

/* Totals from DB — prefer stored columns */
$subtotal = floatval($order['subtotal'] ?? $order['sub_total'] ?? 0);
$service  = floatval($order['service_charge'] ?? $order['service'] ?? 0);
$tax      = floatval($order['tax'] ?? 0);
$discount = floatval($order['discount'] ?? 0);
$total    = floatval($order['total_amount'] ?? $order['payable'] ?? 0);

// Payment details stored as JSON or text
$paymentDetailsRaw = $order['payment_details'] ?? null;
$paymentDetails = [];
if (is_string($paymentDetailsRaw) && $paymentDetailsRaw !== '') {
    $decoded = json_decode($paymentDetailsRaw, true);
    if (is_array($decoded)) $paymentDetails = $decoded;
}
// fallback: some systems store payer/ref inside 'note' as "Payment Details: {...}"
if (empty($paymentDetails) && !empty($order['note'])) {
    if (preg_match('/Payment Details:\s*(\{.*\})/s', $order['note'], $m)) {
        $d = json_decode($m[1], true);
        if (is_array($d)) $paymentDetails = $d;
    }
}

// cash & change — reading multiple possible columns
$cash = floatval($paymentDetails['given'] ?? $order['cash_given'] ?? $order['cash'] ?? 0);
$change = floatval($paymentDetails['change'] ?? $order['change_amount'] ?? $order['change'] ?? 0);

// payer & ref for non-cash
$payer = $paymentDetails['name'] ?? $paymentDetails['payer'] ?? $order['payer_name'] ?? '';
$ref   = $paymentDetails['ref'] ?? $paymentDetails['reference'] ?? $order['payment_ref'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Club Hiraya — Receipt Reprint</title>
<style>
/* 80MM style — same as payment print */
body { font-family: "Arial", sans-serif; margin:0; padding:0; background:#fff; color:#000; width:80mm; max-width:80mm; font-size:14px; line-height:1.25; }
@page { size:80mm auto; margin:0; }
h2,h3 { text-align:center; margin:0; padding:0; }
h2{font-size:18px} h3{font-size:12px; margin-bottom:8px}
hr{border:none;border-top:1px dashed #000;margin:6px 0}
table{width:100%;border-collapse:collapse}
.items th,.items td{font-size:14px;padding:3px 0;border-bottom:1px dotted #bbb}
.right{text-align:right}
.summary td{padding:3px 0}
.summary tr.total td{border-top:2px solid #000;font-weight:bold;font-size:15px}
.no-print{margin-top:8px;text-align:center}
button{padding:6px 16px;font-size:14px;border-radius:5px;border:none;cursor:pointer}
.print-btn{background:#2563eb;color:#fff}.close-btn{background:#6b7280;color:#fff}
footer{text-align:center;margin-top:10px;font-size:12px}
@media print{.no-print{display:none!important} body{width:80mm!important;max-width:80mm!important}}
</style>
</head>
<body>

<h2>Club Hiraya</h2>
<h3>Hanin Town Subd., Cauyan, Friendship, Angeles City, Pampanga<br>Opening Hours: 4:00PM - 1:00AM</h3>
<hr>

<table width="100%">
  <tr><td>Order #:</td><td class="right"><?= htmlspecialchars($id) ?></td></tr>
  <tr><td>Date:</td><td class="right"><?= htmlspecialchars($date) ?></td></tr>
  <tr><td>Payment:</td><td class="right"><?= htmlspecialchars($paymentMethod) ?></td></tr>
  <tr><td>Discount:</td><td class="right"><?= htmlspecialchars($discountType) ?> (<?= number_format($discountRate,0) ?>%)</td></tr>
  <tr><td>Cabin:</td><td class="right"><?= htmlspecialchars($cabinName) ?></td></tr>
  <?php if ($cabinPrice > 0): ?>
    <tr><td>Cabin Price:</td><td class="right"><?= fmt($cabinPrice) ?></td></tr>
  <?php endif; ?>
</table>

<?php if ($paymentMethod !== 'Cash' && (strlen(trim($payer)) || strlen(trim($ref)))): ?>
<div style="margin-top:6px;font-size:16px;">
  <?php if (strlen(trim($payer))): ?><strong>Payer:</strong> <?= htmlspecialchars($payer) ?><br><?php endif; ?>
  <?php if (strlen(trim($ref))): ?><strong>Reference:</strong> <?= htmlspecialchars($ref) ?><br><?php endif; ?>
</div>
<?php endif; ?>

<hr>

<table class="items">
<thead><tr><th>Qty</th><th>Item</th><th class="right">Unit</th><th class="right">Total</th></tr></thead>
<tbody>
<?php if (empty($items)): ?>
  <tr><td colspan="4" align="center">(No items)</td></tr>
<?php else: foreach ($items as $it): ?>
  <tr>
    <td><?= htmlspecialchars($it['qty'] ?? $it['quantity'] ?? 0) ?></td>
    <td><?= htmlspecialchars($it['item_name'] ?? $it['name'] ?? '') ?></td>
    <td class="right"><?= fmt($it['unit_price'] ?? $it['price'] ?? 0) ?></td>
    <td class="right"><?= fmt($it['line_total'] ?? ($it['qty'] * ($it['unit_price'] ?? $it['price'] ?? 0))) ?></td>
  </tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<?php if (!empty($order['note'])): ?>
<div style="margin-top:12px;padding:8px 12px;border-left:3px solid #999;background:#fafafa;color:#444;">
  <strong>Note:</strong> <?= nl2br(htmlspecialchars($order['note'])) ?>
</div>
<?php endif; ?>

<hr>

<table class="summary" width="100%">
  <tr><td>Subtotal</td><td class="right"><?= fmt($subtotal) ?></td></tr>
  <tr><td>Service</td><td class="right"><?= fmt($service) ?></td></tr>
  <tr><td>Tax</td><td class="right"><?= fmt($tax) ?></td></tr>
  <tr><td>Discount</td><td class="right"><?= fmt($discount) ?></td></tr>

  <?php if ($cabinPrice > 0): ?>
    <tr><td>Reserved</td><td class="right"><?= fmt($cabinPrice) ?></td></tr>
  <?php endif; ?>

  <tr class="total"><td>Total Payable</td><td class="right"><?= fmt($total) ?></td></tr>

  <?php if ($paymentMethod === 'Cash'): ?>
    <tr><td>Cash</td><td class="right"><?= fmt($cash) ?></td></tr>
    <tr><td>Change</td><td class="right"><?= fmt($change) ?></td></tr>
  <?php endif; ?>
</table>

<div class="no-print">
  <button class="print-btn" onclick="window.print()">🖨️ Reprint</button>
  <button class="close-btn" onclick="window.close()">✖ Close</button>
</div>

<footer>
  Thank you for dining at Club Hiraya!<br>Please come again.
</footer>

</body>
</html>
