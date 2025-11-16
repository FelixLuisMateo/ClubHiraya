<?php
// print_receipt_payment.php
// FINAL Version — Clean, Reliable, Fully Compatible with POS + Sales_Report

require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

/* ----------------------------------------------------
   RAW POST DATA
----------------------------------------------------- */
$cart     = json_decode($_POST['cart']     ?? '[]', true);
$totals   = json_decode($_POST['totals']   ?? '[]', true);
$reserved = json_decode($_POST['reserved'] ?? '[]', true);
$meta     = json_decode($_POST['meta']     ?? '[]', true);

if (!is_array($cart))     $cart = [];
if (!is_array($totals))   $totals = [];
if (!is_array($reserved)) $reserved = [];
if (!is_array($meta))     $meta = [];

if (empty($cart) && empty($meta)) {
    echo "<h2>No receipt data</h2>";
    exit;
}

/* ----------------------------------------------------
   BASIC INFO
----------------------------------------------------- */
$note = trim($meta['note'] ?? ($_POST['note'] ?? ''));

$orderId = htmlspecialchars(
      $meta['sale_id']
   ?? $meta['id']
   ?? $meta['saleId']
   ?? '—'
);

$date = date('F d, Y h:i:s A');

/* ----------------------------------------------------
   PAYMENT METHOD NORMALIZATION
   (Matches EXACT logic of GCash / Bank Transfer / Cash)
----------------------------------------------------- */
$rawMethod =
      $meta['payment_method']
   ?? $meta['paymentMethod']
   ?? $meta['method']
   ?? $meta['methodName']
   ?? 'cash';

$rawLower = strtolower(trim((string)$rawMethod));

$isCash = false;
$isGCash = false;
$isBank = false;

/* detect method */
if (strpos($rawLower, 'gcash') !== false) {
    $isGCash = true;
    $paymentLabel = "GCash";
}
elseif (strpos($rawLower, 'bank') !== false || strpos($rawLower, 'transfer') !== false) {
    $isBank = true;
    $paymentLabel = "Bank Transfer";
}
elseif (strpos($rawLower, 'cash') !== false) {
    $isCash = true;
    $paymentLabel = "Cash";
}
else {
    // fallback
    $paymentLabel = ucfirst($rawLower);
}

/* ----------------------------------------------------
   DISCOUNT
----------------------------------------------------- */
$discountType = $meta['discountType'] ?? $totals['discountType'] ?? 'Regular';
$lower = strtolower($discountType);
$discountRate = (in_array($lower, ['senior', 'senior citizen', 'pwd'])) ? 20 : 0;

/* ----------------------------------------------------
   CABIN / RESERVED
----------------------------------------------------- */
$cabinName  = 'No Cabin Selected';
$cabinPrice = 0.00;

if (!empty($reserved)) {
    $cabinName =
          $reserved['name']
       ?? $reserved['table']
       ?? $reserved['table_number']
       ?? $cabinName;

    $cabinPrice = floatval(
          $reserved['price']
       ?? $reserved['price_php']
       ?? $reserved['cabin_price']
       ?? 0
    );
} else {
    // fallback from meta
    $cabinName =
          $meta['cabin_name']
       ?? $meta['table_no']
       ?? $cabinName;

    $cabinPrice = floatval(
          $meta['cabin_price']
       ?? $meta['table_price']
       ?? 0
    );
}

/* ----------------------------------------------------
   PAYMENT DETAILS
----------------------------------------------------- */
$paymentDetails = $meta['payment_details']
               ?? $meta['paymentDetails']
               ?? $meta['payment']
               ?? [];

if (is_string($paymentDetails)) {
    $decoded = json_decode($paymentDetails, true);
    if (is_array($decoded)) $paymentDetails = $decoded;
}

if (!is_array($paymentDetails)) $paymentDetails = [];

/* cashGiven and change */
$cashGiven = null;
$change    = null;

$possibleCashKeys = [
    $meta['cashGiven'] ?? null,
    $meta['cash_given'] ?? null,
    $meta['cash'] ?? null,
    $paymentDetails['given'] ?? null,
    $paymentDetails['cash'] ?? null,
    $totals['cashGiven'] ?? null,
    $totals['cash_given'] ?? null,
];

foreach ($possibleCashKeys as $v) {
    if ($v !== null && $v !== '') { $cashGiven = floatval($v); break; }
}
if ($cashGiven === null) $cashGiven = 0;

$possibleChangeKeys = [
    $meta['change'] ?? null,
    $meta['change_amount'] ?? null,
    $paymentDetails['change'] ?? null,
    $totals['change'] ?? null
];
foreach ($possibleChangeKeys as $v) {
    if ($v !== null && $v !== '') { $change = floatval($v); break; }
}
if ($change === null) $change = 0;

/* payer info */
$payerName = $paymentDetails['name']
          ?? $paymentDetails['payer']
          ?? $meta['payer']
          ?? '';

$refNumber = $paymentDetails['ref']
          ?? $paymentDetails['reference']
          ?? $meta['ref']
          ?? $meta['reference']
          ?? '';

/* ----------------------------------------------------
   TOTALS
----------------------------------------------------- */
$subtotalVal   = floatval($totals['subtotal'] ?? 0);
$serviceVal    = floatval($totals['serviceCharge'] ?? 0);
$taxVal        = floatval($totals['tax'] ?? 0);
$discountVal   = floatval($totals['discountAmount'] ?? 0);
$tablePriceVal = floatval($totals['tablePrice'] ?? 0);
$payableVal    = floatval($totals['payable'] ?? 0);

/* Peso format */
function fmt($n){ return '₱' . number_format((float)$n, 2); }

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Receipt - Club Hiraya</title>

<style>
body {
  font-family: "Arial", sans-serif;
  margin:0;
  padding:0;
  background:#fff;
  color:#000;
  width:80mm;
  max-width:80mm;
  font-size:14px;
  line-height:1.25;
}
@page { size:80mm auto; margin:0; }
h2,h3 { text-align:center; margin:0; padding:0; }
h2 { font-size:18px; }
h3 { font-size:12px; margin-bottom:8px; }
hr { border:none; border-top:1px dashed #000; margin:6px 0; }
table { width:100%; border-collapse:collapse; }
.items th, .items td {
  font-size:14px;
  padding:3px 0;
  border-bottom:1px dotted #bbb;
}
.right { text-align:right; }
.summary td { padding:3px 0; }
.summary tr.total td {
  border-top:2px solid #000;
  font-weight:bold;
  font-size:15px;
}
.no-print { margin-top:8px; text-align:center; }
button {
  padding:6px 16px;
  font-size:14px;
  border-radius:5px;
  border:none;
  cursor:pointer;
}
.print-btn { background:#2563eb; color:#fff; }
.close-btn { background:#6b7280; color:#fff; }
footer {
  text-align:center;
  margin-top:10px;
  font-size:12px;
}
@media print {
  .no-print { display:none !important; }
  body { width:80mm !important; max-width:80mm !important; }
}
</style>
</head>

<body>

<h2>Club Hiraya</h2>
<h3>Hanin Town Subd., Cauyan, Friendship, Angeles City, Pampanga<br>
Opening Hours: 4:00PM - 1:00AM</h3>
<hr>

<table width="100%">
  <tr><td>Order #:</td><td class="right"><?= $orderId ?></td></tr>
  <tr><td>Date:</td><td class="right"><?= htmlspecialchars($date) ?></td></tr>
  <tr><td>Payment:</td><td class="right"><?= htmlspecialchars($paymentLabel) ?></td></tr>
  <tr><td>Discount:</td><td class="right"><?= htmlspecialchars($discountType) ?> (<?= $discountRate ?>%)</td></tr>
  <tr><td>Cabin:</td><td class="right"><?= htmlspecialchars($cabinName) ?></td></tr>

  <?php if ($cabinPrice > 0): ?>
    <tr><td>Cabin Price:</td><td class="right"><?= fmt($cabinPrice) ?></td></tr>
  <?php elseif ($tablePriceVal > 0): ?>
    <tr><td>Reserved:</td><td class="right"><?= fmt($tablePriceVal) ?></td></tr>
  <?php endif; ?>
</table>

<?php if (!$isCash): ?>
<div style="margin-top:8px; font-size:15px;">
  <?php if ($payerName): ?><strong>Payer Name:</strong> <?= htmlspecialchars($payerName) ?><br><?php endif; ?>
  <?php if ($refNumber): ?><strong>Reference No:</strong> <?= htmlspecialchars($refNumber) ?><br><?php endif; ?>
</div>
<?php endif; ?>

<hr>

<table class="items">
<thead>
  <tr><th>Qty</th><th>Item</th><th class="right">Unit</th><th class="right">Total</th></tr>
</thead>
<tbody>
<?php foreach ($cart as $it):
    $name = htmlspecialchars($it['item_name'] ?? $it['name'] ?? 'Item');
    $qty  = intval($it['qty'] ?? $it['quantity'] ?? 0);
    $unit = floatval($it['unit_price'] ?? $it['price'] ?? 0);
    $line = floatval($it['line_total'] ?? ($qty * $unit));
?>
<tr>
  <td><?= $qty ?></td>
  <td><?= $name ?></td>
  <td class="right"><?= fmt($unit) ?></td>
  <td class="right"><?= fmt($line) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php if (!empty($note)): ?>
<div style="margin:12px 0; padding:8px 12px; border-left:3px solid #999; background:#fafafa;">
  <strong>Note:</strong> <?= nl2br(htmlspecialchars($note)) ?>
</div>
<?php endif; ?>

<hr>

<table class="summary">
  <tr><td>Subtotal</td><td class="right"><?= fmt($subtotalVal) ?></td></tr>
  <tr><td>Service</td><td class="right"><?= fmt($serviceVal) ?></td></tr>
  <tr><td>Tax</td><td class="right"><?= fmt($taxVal) ?></td></tr>
  <tr><td>Discount</td><td class="right"><?= fmt($discountVal) ?></td></tr>

  <?php if ($tablePriceVal > 0 && $cabinPrice <= 0): ?>
  <tr><td>Reserved</td><td class="right"><?= fmt($tablePriceVal) ?></td></tr>
  <?php endif; ?>

  <tr class="total"><td>Total Payable</td><td class="right"><?= fmt($payableVal) ?></td></tr>

  <?php if ($isCash): ?>
    <tr><td>Cash</td><td class="right"><?= fmt($cashGiven) ?></td></tr>
    <tr><td>Change</td><td class="right"><?= fmt($change) ?></td></tr>
  <?php endif; ?>
</table>

<div class="no-print">
  <button class="print-btn" onclick="window.print()">Print</button>
  <button class="close-btn" onclick="window.location.href='../admin_dashboard.php'">Close</button>
</div>

<footer>
  Thank you for dining at Club Hiraya!<br>Please come again.
</footer>

</body>
</html>
