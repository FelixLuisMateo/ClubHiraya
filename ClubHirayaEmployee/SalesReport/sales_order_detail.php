<?php
require_once __DIR__ . '/../php/db_connect.php';
date_default_timezone_set('Asia/Manila');

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "<div class='order-item-empty'>Invalid order ID.</div>";
    exit;
}

$stmt = $conn->prepare("SELECT * FROM sales_report WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "<div class='order-item-empty'>Order not found.</div>";
    exit;
}

// 🔹 Fetch items from sales_items
$items = [];
$itemQuery = "SELECT * FROM sales_items WHERE sales_id = ?";
$stmt = $conn->prepare($itemQuery);
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $items[] = $row;
$stmt->close();

function fmt($n) {
    return '₱' . number_format((float)$n, 2);
}

// 🔹 Extract data safely
$date = date('F d, Y h:i:s A', strtotime($order['created_at']));
$payment = ucfirst($order['payment_method'] ?? 'Cash');
$discountType = $order['discount_type'] ?? 'Regular';
$discountRate = $order['discount_rate'] ?? 0;
$cabin = $order['table_no'] ?? '';
$cabinPrice = $order['cabin_price'] ?? 0;
$note = trim($order['note'] ?? '');
// Remove "Payment Details: {...}" line from the note before showing
$note = preg_replace('/Payment Details:\s*\{.*?\}\s*/s', '', $note);
$subtotal = $order['subtotal'] ?? 0;
$service = $order['service_charge'] ?? 0;
$tax = $order['tax'] ?? 0;
$discount = $order['discount'] ?? 0;
$reserved = $order['table_price'] ?? 0;
$total = $order['total_amount'] ?? 0;
$cash = $order['cash_given'] ?? 0;
$change = $order['change_amount'] ?? 0;

// 🔹 Try to extract payment details JSON from note
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
?>

<div style="font-size:16px;">
  <div><strong>Date:</strong> <?= htmlspecialchars($date) ?></div>
  <div><strong>Payment:</strong> <?= htmlspecialchars($payment) ?></div>
  <?php if ($paymentDetailsHTML): ?>
    <?= $paymentDetailsHTML ?>
  <?php endif; ?>
  <div><strong>Discount:</strong> <?= htmlspecialchars($discountType) ?> (<?= htmlspecialchars($discountRate) ?>%)</div>
  <div><strong>Cabin:</strong> <?= htmlspecialchars($cabin) ?></div>
  <div><strong>Cabin Price:</strong> <?= fmt($cabinPrice) ?></div>
  <hr>
  <table style="width:100%; border-collapse:collapse; font-size:15px;">
    <thead>
      <tr><th align="left">Qty</th><th align="left">Item</th><th align="right">Unit</th><th align="right">Total</th></tr>
    </thead>
    <tbody>
    <?php if (empty($items)): ?>
      <tr><td colspan="4" align="center">No items found.</td></tr>
    <?php else: foreach ($items as $it): ?>
      <tr>
        <td><?= htmlspecialchars($it['qty']) ?></td>
        <td><?= htmlspecialchars($it['item_name'] ?? $it['name']) ?></td>
        <td align="right"><?= fmt($it['unit_price']) ?></td>
        <td align="right"><?= fmt($it['line_total']) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <?php if (!empty($note)): ?>
  <div style="margin-top:10px;padding:8px;border-left:3px solid #888;">
    <strong>Note:</strong> <?= nl2br(htmlspecialchars($note)) ?>
  </div>
  <?php endif; ?>

  <hr>
  <table style="width:100%; font-size:15px;">
    <tr><td>Subtotal</td><td align="right"><?= fmt($subtotal) ?></td></tr>
    <tr><td>Service</td><td align="right"><?= fmt($service) ?></td></tr>
    <tr><td>Tax</td><td align="right"><?= fmt($tax) ?></td></tr>
    <tr><td>Discount</td><td align="right"><?= fmt($discount) ?></td></tr>
    <tr><td>Reserved</td><td align="right"><?= fmt($reserved) ?></td></tr>
    <tr><td><strong>Total Payable</strong></td><td align="right"><strong><?= fmt($total) ?></strong></td></tr>
    <tr><td>Cash</td><td align="right"><?= fmt($cash) ?></td></tr>
    <tr><td>Change</td><td align="right"><?= fmt($change) ?></td></tr>
  </table>

  <div style="margin-top:16px; display:flex; justify-content:flex-end; gap:10px;">
    <button style="padding:8px 14px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer;"
      onclick="window.open('print_receipt_payment.php?id=<?= $id ?>', '_blank')">🧾 Reprint</button>
    <button style="padding:8px 14px; background:#dc2626; color:#fff; border:none; border-radius:6px; cursor:pointer;"
      onclick="alert('Void action for Order #<?= $id ?> (to be implemented)')">❌ Void</button>
  </div>
</div>
