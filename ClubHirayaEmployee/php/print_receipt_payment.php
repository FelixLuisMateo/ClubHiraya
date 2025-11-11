<?php
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

$cartJson     = $_POST['cart']     ?? null;
$totalsJson   = $_POST['totals']   ?? null;
$reservedJson = $_POST['reserved'] ?? null;
$metaJson     = $_POST['meta']     ?? null;

if ($cartJson === null) {
  header('X-Robots-Tag: noindex, nofollow', true);
  ?>
  <!doctype html><html><body><h1>No receipt data</h1><a href="../employee_dashboard.php">Back</a></body></html>
  <?php exit;
}

$cart     = json_decode($cartJson, true)     ?: [];
$totals   = json_decode($totalsJson, true)   ?: [];
$reserved = json_decode($reservedJson, true) ?: [];
$meta     = json_decode($metaJson, true)     ?: [];

/* --------------------------
   SAFE DATA EXTRACTION
--------------------------- */
$date = date('F d, Y h:i:s A');
$paymentMethod = ucfirst($meta['payment_method'] ?? 'Cash');

# ✅ Discount (get from meta first, fallback to totals, then default)
$discountType = $meta['discountType'] 
  ?? ($totals['discountType'] ?? 'Regular');
$discountRate = 0;
if (isset($meta['discountRate'])) {
  $discountRate = floatval($meta['discountRate']) * 100;
} elseif (isset($totals['discountRate'])) {
  $discountRate = floatval($totals['discountRate']) * 100;
}

# ✅ Cabin/Reservation
$cabinName  = $reserved['name'] ?? ($reserved['table'] ?? ($reserved['table_number'] ?? 'N/A'));
$cabinPrice = isset($reserved['price']) ? floatval($reserved['price']) : 0;

# ✅ Payment Details (cash/change) — check both direct and nested keys
$cashGiven = 0;
if (isset($meta['cashGiven'])) $cashGiven = floatval($meta['cashGiven']);
elseif (isset($meta['payment_details']['given'])) $cashGiven = floatval($meta['payment_details']['given']);
elseif (isset($meta['payment_details']['cashGiven'])) $cashGiven = floatval($meta['payment_details']['cashGiven']);

$change = 0;
if (isset($meta['change'])) $change = floatval($meta['change']);
elseif (isset($meta['payment_details']['change'])) $change = floatval($meta['payment_details']['change']);

# ✅ Helper for formatting PHP Peso
function fmt($n){ return '₱' . number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Receipt - Club Hiraya</title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #fff;
  color: #111;
  width: 100%;
  max-width: none;
  margin: 0;
  padding: 20px;
  font-size: 18px;
  box-sizing: border-box;
}
h2{text-align:center;margin:0;font-size:25px;}
h3{text-align:center;margin:4px 0 10px;font-size:17px;color:#555;}
hr{border:none;border-top:1px dashed #aaa;margin:8px 0;}
.items{width:100%;border-collapse:collapse;}
.items th,.items td{border-bottom:1px solid #eee;padding:4px 0;font-size:18px;}
.right{text-align:right;}
.summary td{padding:3px 0;font-size:18px;}
.summary tr.total td{border-top:2px solid #000;font-weight:bold;font-size:19px;}
footer{text-align:center;margin-top:12px;font-size:16px;color:#555;}
button{margin:4px;padding:8px 16px;border:none;border-radius:6px;cursor:pointer;color:#fff;}
.print-btn{background:#2563eb;}
.close-btn{background:#6b7280;}
@media print{.no-print{display:none}}
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
  <tr><td>Cabin Price:</td><td class="right"><?= fmt($cabinPrice) ?></td></tr>
</table>
<hr>

<table class="items">
  <thead>
    <tr><th>Qty</th><th>Item</th><th class="right">Unit</th><th class="right">Total</th></tr>
  </thead>
  <tbody>
  <?php if (!$cart): ?>
    <tr><td colspan="4" style="text-align:center;">(No items)</td></tr>
  <?php else: foreach ($cart as $it):
    $n = htmlspecialchars($it['item_name'] ?? $it['name'] ?? 'Item');
    $q = (int)($it['qty'] ?? 0);
    $p = (float)($it['unit_price'] ?? $it['price'] ?? 0);
    $l = (float)($it['line_total'] ?? ($p * $q));
  ?>
    <tr>
      <td><?= $q ?></td>
      <td><?= $n ?></td>
      <td class="right"><?= fmt($p) ?></td>
      <td class="right"><?= fmt($l) ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<hr>

<table class="summary" width="100%">
  <tr><td>Subtotal</td><td class="right"><?= fmt($totals['subtotal'] ?? 0) ?></td></tr>
  <tr><td>Service</td><td class="right"><?= fmt($totals['serviceCharge'] ?? 0) ?></td></tr>
  <tr><td>Tax</td><td class="right"><?= fmt($totals['tax'] ?? 0) ?></td></tr>
  <tr><td>Discount</td><td class="right"><?= fmt($totals['discountAmount'] ?? 0) ?></td></tr>
  <?php if (!empty($totals['tablePrice'])): ?>
  <tr><td>Reserved</td><td class="right"><?= fmt($totals['tablePrice']) ?></td></tr>
  <?php endif; ?>
  <tr class="total"><td>Total Payable</td><td class="right"><?= fmt($totals['payable'] ?? 0) ?></td></tr>
  <tr><td>Cash</td><td class="right"><?= fmt($cashGiven) ?></td></tr>
  <tr><td>Change</td><td class="right"><?= fmt($change) ?></td></tr>
</table>

<div class="no-print">
  <button class="print-btn" onclick="window.print()">Print</button>
  <button class="close-btn" onclick="window.location.href='../employee_dashboard.php'">Close</button>
</div>

<footer>
  Thank you for dining at Club Hiraya!<br>Please come again.
</footer>
</body>
</html>
