<?php
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "<h2>Invalid Order ID</h2>";
    exit;
}

// 🔹 Fetch order data
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

// 🔹 Fetch sales items
$items = [];
$stmt = $conn->prepare("SELECT * FROM sales_items WHERE sales_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $items[] = $row;
$stmt->close();

function fmt($n) { return '₱' . number_format((float)$n, 2); }

$date = date('F d, Y h:i:s A', strtotime($order['created_at']));
$payment = ucfirst($order['payment_method'] ?? 'Cash');
$note = trim($order['note'] ?? '');
$discountType = $order['discount_type'] ?? 'Regular';
$discountRate = $order['discount_rate'] ?? 0;
$cabin = $order['table_no'] ?? '';
$cabinPrice = $order['cabin_price'] ?? 0;
$subtotal = $order['subtotal'] ?? 0;
$service = $order['service_charge'] ?? 0;
$tax = $order['tax'] ?? 0;
$discount = $order['discount'] ?? 0;
$reserved = $order['table_price'] ?? 0;
$total = $order['total_amount'] ?? 0;
$cash = $order['cash_given'] ?? 0;
$change = $order['change_amount'] ?? 0;

// 🔹 Extract Payment Details JSON from note
$paymentDetailsHTML = '';
if (preg_match('/Payment Details:\s*(\{.*\})/s', $note, $m)) {
    $json = json_decode($m[1], true);
    if (is_array($json)) {
        if (strtolower($payment) === 'gcash') {
            $name = htmlspecialchars($json['name'] ?? '');
            $ref  = htmlspecialchars($json['ref'] ?? '');
            $paymentDetailsHTML = "<div><strong>GCash:</strong> {$name}".($ref ? " ({$ref})" : "")."</div>";
        } elseif (strtolower($payment) === 'bank_transfer') {
            $name = htmlspecialchars($json['name'] ?? '');
            $ref  = htmlspecialchars($json['ref'] ?? '');
            $paymentDetailsHTML = "<div><strong>Bank Transfer:</strong> {$name}".($ref ? " ({$ref})" : "")."</div>";
        } elseif (strtolower($payment) === 'cash') {
            $given = isset($json['given']) ? fmt($json['given']) : '—';
            $chng  = isset($json['change']) ? fmt($json['change']) : '—';
            $paymentDetailsHTML = "<div><strong>Cash Given:</strong> {$given}<br><strong>Change:</strong> {$chng}</div>";
        }
    }
}
$note = preg_replace('/Payment Details:\s*\{.*?\}\s*/s', '', $note);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Club Hiraya — Receipt Reprint</title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #fff;
  color: #111;
  width: 100%;
  margin: 0;
  padding: 24px;
  font-size: 18px;
  box-sizing: border-box;
}
h2{text-align:center;margin:0;font-size:26px;}
h3{text-align:center;margin:4px 0 12px;font-size:16px;color:#555;}
hr{border:none;border-top:1px dashed #aaa;margin:10px 0;}
.items{width:100%;border-collapse:collapse;margin-top:10px;}
.items th,.items td{padding:4px 0;font-size:17px;border-bottom:1px solid #eee;}
.right{text-align:right;}
.summary td{padding:3px 0;font-size:17px;}
.summary tr.total td{border-top:2px solid #000;font-weight:bold;font-size:19px;}
footer{text-align:center;margin-top:12px;font-size:15px;color:#555;}
button{margin:4px;padding:8px 16px;border:none;border-radius:6px;cursor:pointer;color:#fff;}
.print-btn{background:#2563eb;}
.close-btn{background:#6b7280;}
@media print{.no-print{display:none}}
</style>
</head>
<body>
<h2>Club Hiraya</h2>
<h3>Hanin Town Subd., Friendship, Angeles City<br>Opening Hours: 4:00PM - 1:00AM</h3>
<hr>
<table width="100%">
  <tr><td>Date:</td><td class="right"><?= htmlspecialchars($date) ?></td></tr>
  <tr><td>Payment:</td><td class="right"><?= htmlspecialchars($payment) ?></td></tr>
  <?php if ($paymentDetailsHTML): ?><tr><td colspan="2"><?= $paymentDetailsHTML ?></td></tr><?php endif; ?>
  <tr><td>Discount:</td><td class="right"><?= htmlspecialchars($discountType) ?> (<?= htmlspecialchars($discountRate) ?>%)</td></tr>
  <tr><td>Cabin:</td><td class="right"><?= htmlspecialchars($cabin) ?></td></tr>
  <?php if ($cabinPrice > 0): ?><tr><td>Cabin Price:</td><td class="right"><?= fmt($cabinPrice) ?></td></tr><?php endif; ?>
</table>
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
    <td><?= htmlspecialchars($it['item_name'] ?? $it['name']) ?></td>
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
  <tr><td>Reserved</td><td class="right"><?= fmt($reserved) ?></td></tr>
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
