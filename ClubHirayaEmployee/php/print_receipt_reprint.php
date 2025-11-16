<?php
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

/* -----------------------------
   RECEIPT ID
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
$date          = date('F d, Y h:i:s A', strtotime($order['created_at']));
$paymentMethod = ucfirst($order['payment_method'] ?? 'Cash');
$note          = trim($order['note'] ?? '');

$discountType  = $order['discount_type'] ?? 'Regular';
$discountRate  = floatval($order['discount_rate'] ?? 0) * 100;

/* Cabin */
$cabinName     = $order['table_no'] ?: 'No Cabin Selected';
$cabinPrice    = floatval($order['table_price'] ?? 0);

/* Totals */
$subtotal      = floatval($order['subtotal'] ?? 0);
$service       = floatval($order['service_charge'] ?? 0);
$tax           = floatval($order['tax'] ?? 0);
$discount      = floatval($order['discount'] ?? 0);
$total         = floatval($order['total_amount'] ?? 0);

/* Payment Details JSON */
$paymentDetails = json_decode($order['payment_details'] ?? '{}', true);

$cash    = $paymentDetails['given'] ?? ($order['cash_given'] ?? 0);
$change  = $paymentDetails['change'] ?? ($order['change_amount'] ?? 0);

$gcashName = $paymentDetails['name'] ?? '';
$gcashRef  = $paymentDetails['ref']  ?? '';

/* Created By (get role) */
$createdBy = 'Unknown';
if (!empty($order['created_by'])) {
    $uid = intval($order['created_by']);
    $u = $conn->query("SELECT role FROM users WHERE id = $uid LIMIT 1");
    if ($u && $ur = $u->fetch_assoc()) {
        $createdBy = ucfirst($ur['role']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Club Hiraya — Receipt Reprint</title>

<style>
/* 80MM THERMAL RECEIPT PRINTING */
body {
  font-family: "Arial", sans-serif;
  margin: 0;
  padding: 0;
  background: #fff;
  color: #000;
  width: 80mm;            /* 🔥 Very important */
  max-width: 80mm;
  font-size: 14px;        /* Receipt font size */
  line-height: 1.25;
}

@page {
  size: 80mm auto;        /* 🔥 Forces thermal printer size */
  margin: 0;              /* 🔥 Remove page margins */
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

.right {
  text-align: right;
}

.summary td {
  padding: 3px 0;
}

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

/* 🔥 MAKE RECEIPT LOOK PERFECT WHEN PRINTING */
@media print {
  .no-print { display: none !important; }
  body {
    width: 80mm !important;
    max-width: 80mm !important;
    margin: 0 !important;
    padding: 0 !important;
  }
}
</style>

</head>

<body>

<h2>Club Hiraya</h2>
<h3>Hanin Town Subd., Friendship, Angeles City<br>Opening Hours: 4:00PM - 1:00AM</h3>
<hr>

<table width="100%">
  <tr><td>Date:</td><td class="right"><?= htmlspecialchars($date) ?></td></tr>
  <tr><td>Payment:</td><td class="right"><?= htmlspecialchars($paymentMethod) ?></td></tr>
  <tr><td>Created By:</td><td class="right"><?= htmlspecialchars($createdBy) ?></td></tr>

  <tr><td>Discount:</td>
      <td class="right"><?= htmlspecialchars($discountType) ?> (<?= number_format($discountRate) ?>%)</td></tr>

  <tr><td>Cabin:</td><td class="right"><?= htmlspecialchars($cabinName) ?></td></tr>

  <?php if ($cabinPrice > 0): ?>
  <tr><td>Cabin Price:</td><td class="right"><?= fmt($cabinPrice) ?></td></tr>
  <?php endif; ?>
</table>

<?php if ($paymentMethod !== 'Cash'): ?>
<div style="margin-top:6px;font-size:16px;">
  <strong>Payer:</strong> <?= htmlspecialchars($gcashName) ?><br>
  <strong>Reference:</strong> <?= htmlspecialchars($gcashRef) ?>
</div>
<?php endif; ?>

<hr>

<table class="items">
<thead>
  <tr><th>Qty</th><th>Item</th><th class="right">Unit</th><th class="right">Total</th></tr>
</thead>
<tbody>

<?php if (!$items): ?>
  <tr><td colspan="4" align="center">(No items)</td></tr>
<?php else: foreach ($items as $it): ?>
  <tr>
    <td><?= htmlspecialchars($it['qty']) ?></td>
    <td><?= htmlspecialchars($it['item_name']) ?></td>
    <td class="right"><?= fmt($it['unit_price']) ?></td>
    <td class="right"><?= fmt($it['line_total']) ?></td>
  </tr>
<?php endforeach; endif; ?>

</tbody>
</table>

<?php if (!empty($note)): ?>
<div style="margin-top:12px;padding:8px 12px;border-left:3px solid #999;background:#fafafa;color:#444;">
  <strong>Note:</strong> <?= nl2br(htmlspecialchars($note)) ?>
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
  <tr><td>Cash</td><td class="right"><?= fmt($cash) ?></td></tr>
  <tr><td>Change</td><td class="right"><?= fmt($change) ?></td></tr>
</table>

<div class="no-print" style="margin-top:14px;text-align:right;">
  <button class="print-btn" onclick="window.print()">🖨️ Print</button>
  <button class="close-btn" onclick="window.close()">✖ Close</button>
</div>

<footer>
  Thank you for dining at Club Hiraya!<br>Please come again.
</footer>

</body>
</html>
