<?php
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

/* ----------------------
   GET RAW POST DATA
----------------------- */
$cart     = json_decode($_POST['cart']     ?? '[]', true);
$totals   = json_decode($_POST['totals']   ?? '[]', true);
$reserved = json_decode($_POST['reserved'] ?? '[]', true);
$meta     = json_decode($_POST['meta']     ?? '[]', true);

/* No data = stop */
if (!$cart) {
  ?>
  <!doctype html><html><body><h1>No receipt data</h1><a href="../admin_dashboard.php">Back</a></body></html>
  <?php
  exit;
}

/* ----------------------
   NOTE HANDLING
----------------------- */
$note = '';
if (!empty($_POST['note'])) {
  $note = trim($_POST['note']);
} elseif (!empty($meta['note'])) {
  $note = trim($meta['note']);
}

/* ----------------------
   BASIC RECEIPT DATA
----------------------- */
$date = date('F d, Y h:i:s A');

$rawPaymentMethod = strtolower($meta['payment_method'] ?? 'cash');

/* Convert to clean human receipt label */
$paymentMethod = ($rawPaymentMethod === 'cash')
    ? 'Cash Payment'
    : ucfirst($rawPaymentMethod);

$discountType = $meta['discountType'] ?? ($totals['discountType'] ?? 'Regular');
$discountRate = isset($meta['discountRate'])
    ? floatval($meta['discountRate']) * 100
    : (isset($totals['discountRate']) ? floatval($totals['discountRate']) * 100 : 0);

/* ----------------------
   CABIN DETAILS
----------------------- */
$cabinName  = 'No Cabin Selected';
$cabinPrice = 0;

if (!empty($reserved)) {
    $cabinName  = $reserved['name']
              ?? $reserved['table']
              ?? $reserved['table_number']
              ?? 'Cabin';

    $cabinPrice = isset($reserved['price']) ? floatval($reserved['price']) : 0;
}

/* ----------------------
   PAYMENT DETAILS
----------------------- */
$paymentDetails = $meta['payment_details'] ?? [];

if ($rawPaymentMethod === 'cash') {
    // When using cash, payment_details is NOT used
    $cashGiven = floatval($meta['cashGiven'] ?? $paymentDetails['given'] ?? 0);
    $change    = floatval($meta['change']    ?? $paymentDetails['change'] ?? 0);
    $payerName = '';
    $refNumber = '';
} else {
    // For GCASH / BANK
    $cashGiven = 0;
    $change    = 0;
    $payerName = $paymentDetails['name'] ?? '';
    $refNumber = $paymentDetails['ref']  ?? '';
}

/* ----------------------
   CREATED BY (Admin / Staff)
----------------------- */
$createdBy = 'Unknown';

if (!empty($meta['sale_id'])) {
    $saleId = intval($meta['sale_id']);

    $q = $conn->query("SELECT created_by FROM sales_report WHERE id = $saleId LIMIT 1");
    if ($q && $r = $q->fetch_assoc()) {

        $uid = intval($r['created_by']);

        $u = $conn->query("SELECT role FROM users WHERE id = $uid LIMIT 1");
        if ($u && $ur = $u->fetch_assoc()) {
            $createdBy = ucfirst($ur['role']);
        }
    }
}

/* Peso Format Helper */
function fmt($n){ return '₱' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Receipt - Club Hiraya</title>
<style>
/* 80MM THERMAL RECEIPT PRINTING */
body {
  font-family: "Arial", sans-serif;
  margin: 0;
  padding: 0;
  background: #fff;
  color: #000;
  width: 80mm;
  max-width: 80mm;
  font-size: 14px;
  line-height: 1.25;
}

@page {
  size: 80mm auto;
  margin: 0;
}

h2, h3 {
  text-align: center;
  margin: 0;
  padding: 0;
}

h2 { font-size: 18px; }
h3 { font-size: 12px; margin-bottom: 8px; }

hr {
  border: none;
  border-top: 1px dashed #000;
  margin: 6px 0;
}

table {
  width: 100%;
  border-collapse: collapse;
}

.items th,
.items td {
  font-size: 14px;
  padding: 3px 0;
  border-bottom: 1px dotted #bbb;
}

.right { text-align: right; }

.summary td { padding: 3px 0; }

.summary tr.total td {
  border-top: 2px solid #000;
  font-weight: bold;
  font-size: 15px;
}

footer {
  text-align: center;
  margin-top: 10px;
  font-size: 12px;
}

.no-print {
  margin-top: 8px;
  text-align: center;
}

button {
  padding: 6px 16px;
  font-size: 14px;
  border-radius: 5px;
  border: none;
  cursor: pointer;
}

.print-btn { background: #2563eb; color: #fff; }
.close-btn { background: #6b7280; color: #fff; }

@media print {
  .no-print { display: none !important; }
  body {
    width: 80mm !important;
    max-width: 80mm !important;
  }
}
</style>

</head>
<body>

<h2>Club Hiraya</h2>
<h3>Hanin Town Subd., Cauyan, Friendship, Angeles City, Pampanga<br>Opening Hours: 4:00PM - 1:00AM</h3>
<hr>

<table width="100%">
  <tr><td>Date:</td><td class="right"><?= htmlspecialchars($date) ?></td></tr>
  <tr><td>Payment:</td><td class="right"><?= htmlspecialchars($paymentMethod) ?></td></tr>
  <tr><td>Discount:</td><td class="right"><?= htmlspecialchars($discountType) ?> (<?= number_format($discountRate, 0) ?>%)</td></tr>
  <tr><td>Cabin:</td><td class="right"><?= htmlspecialchars($cabinName) ?></td></tr>

  <?php if ($cabinPrice > 0): ?>
  <tr><td>Cabin Price:</td><td class="right"><?= fmt($cabinPrice) ?></td></tr>
  <?php endif; ?>

  <tr><td>Created By:</td><td class="right"><?= htmlspecialchars($createdBy) ?></td></tr>
</table>

<hr>

<table class="items">
  <thead>
    <tr><th>Qty</th><th>Item</th><th class="right">Unit</th><th class="right">Total</th></tr>
  </thead>
  <tbody>
  <?php foreach ($cart as $it):
    $name = htmlspecialchars($it['item_name'] ?? $it['name'] ?? 'Item');
    $qty  = intval($it['qty'] ?? 0);
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

<hr>

<?php if (!empty($note)): ?>
<div style="
    margin:10px 0;
    padding:6px 10px;
    border-left:3px solid #999;
    font-size:15px;
    font-style:italic;
    color:#444;
    background:#f9f9f9;">
  <strong>Note:</strong> <?= nl2br(htmlspecialchars($note)) ?>
</div>
<?php endif; ?>

<?php if ($rawPaymentMethod !== 'cash'): ?>
<div style="margin:8px 0;font-size:16px;">
  <strong>Payer Name:</strong> <?= htmlspecialchars($payerName) ?><br>
  <strong>Reference No:</strong> <?= htmlspecialchars($refNumber) ?>
</div>
<?php endif; ?>

<table class="summary" width="100%">
  <tr><td>Subtotal</td><td class="right"><?= fmt($totals['subtotal'] ?? 0) ?></td></tr>
  <tr><td>Service</td><td class="right"><?= fmt($totals['serviceCharge'] ?? 0) ?></td></tr>
  <tr><td>Tax</td><td class="right"><?= fmt($totals['tax'] ?? 0) ?></td></tr>
  <tr><td>Discount</td><td class="right"><?= fmt($totals['discountAmount'] ?? 0) ?></td></tr>

  <?php if (!empty($totals['tablePrice'])): ?>
  <tr><td>Reserved</td><td class="right"><?= fmt($totals['tablePrice']) ?></td></tr>
  <?php endif; ?>

  <tr class="total"><td>Total Payable</td><td class="right"><?= fmt($totals['payable'] ?? 0) ?></td></tr>

  <?php if ($rawPaymentMethod === 'cash'): ?>
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
