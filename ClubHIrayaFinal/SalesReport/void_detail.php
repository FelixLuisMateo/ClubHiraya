<?php
require_once __DIR__ . '/../php/db_connect.php';
date_default_timezone_set('Asia/Manila');

// Validate ID
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "<div class='order-item-empty'>Invalid order ID.</div>";
    exit;
}

// Fetch order
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

// Fetch line items
$items = [];
$stmt = $conn->prepare("SELECT * FROM sales_items WHERE sales_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $items[] = $row;
$stmt->close();

// Helper format
function fmt($n) {
    return '₱' . number_format((float)$n, 2);
}

// Extract fields
$date = date('F d, Y h:i:s A', strtotime($order['created_at']));
$payment = ucfirst($order['payment_method'] ?? 'Cash');
$discountType = $order['discount_type'] ?? 'Regular';
$cabin = $order['cabin_name'] ?: ($order['table_no'] ?: '');
$cabinPrice = $order['cabin_price'] ?? 0;
$note = trim($order['note'] ?? '');

$subtotal = $order['subtotal'] ?? 0;
$service = $order['service_charge'] ?? 0;
$tax = $order['tax'] ?? 0;
$discount = $order['discount'] ?? 0;
$total = $order['total_amount'] ?? 0;
$cash = $order['cash_given'] ?? 0;
$change = $order['change_amount'] ?? 0;

// Payment Details (JSON)
$paymentDetailsHTML = '';
if (!empty($order['payment_details'])) {
    $json = json_decode($order['payment_details'], true);

    if (is_array($json)) {
        if ($payment === 'Gcash') {
            $paymentDetailsHTML = "
                <div><strong>GCash:</strong> " . htmlspecialchars($json['name']) . " (" . htmlspecialchars($json['ref']) . ")</div>";
        } elseif ($payment === 'Bank_transfer') {
            $paymentDetailsHTML = "
                <div><strong>Bank Transfer:</strong> " . htmlspecialchars($json['name']) . " (" . htmlspecialchars($json['ref']) . ")</div>";
        } elseif ($payment === 'Cash') {
            $paymentDetailsHTML = "
                <div><strong>Cash Given:</strong> " . fmt($json['given']) . "</div>
                 <div><strong>Change:</strong> " . fmt($json['change']) . "</div>";
        }
    }
}
?>

<div style="font-size:16px;">

  <h2 style="margin-bottom:6px;">
    Order #<?= $id ?>
    <span style="background:#dc2626;color:#fff;padding:3px 8px;border-radius:6px;font-size:12px;margin-left:6px;">VOIDED</span>
  </h2>

  <div><strong>Date:</strong> <?= htmlspecialchars($date) ?></div>
  <div><strong>Payment Method:</strong> <?= htmlspecialchars($payment) ?></div>
  <?= $paymentDetailsHTML ?>
  <div><strong>Discount Type:</strong> <?= htmlspecialchars($discountType) ?></div>
  <div><strong>Cabin:</strong> <?= htmlspecialchars($cabin ?: '—') ?></div>
  <div><strong>Cabin Price:</strong> <?= fmt($cabinPrice) ?></div>

  <?php if (!empty($note)): ?>
    <div style="margin-top:8px;"><strong>Note:</strong> <?= htmlspecialchars($note) ?></div>
  <?php endif; ?>

  <hr style="margin:12px 0;">

  <h3 style="margin-bottom:8px;">Items</h3>

  <table style="width:100%; border-collapse:collapse; font-size:15px;">
    <thead>
      <tr style="border-bottom:1px solid #ddd;">
        <th align="left">Item</th>
        <th align="center">Qty</th>
        <th align="right">Unit</th>
        <th align="right">Total</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($items)): ?>
      <tr><td colspan="4" align="center">No items found.</td></tr>
    <?php else: foreach ($items as $it): ?>
      <tr style="border-bottom:1px dashed #eee;">
        <td><?= htmlspecialchars($it['item_name']) ?></td>
        <td align="center"><?= $it['qty'] ?></td>
        <td align="right"><?= fmt($it['unit_price']) ?></td>
        <td align="right"><?= fmt($it['line_total']) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <hr style="margin:14px 0;">

  <h3 style="margin-bottom:8px;">Summary</h3>

  <table style="width:100%; font-size:15px;">
    
    <tr><td>Subtotal</td><td align="right"><?= fmt($subtotal) ?></td></tr>
    <tr><td>Service Charge</td><td align="right"><?= fmt($service) ?></td></tr>
    <tr><td>Tax</td><td align="right"><?= fmt($tax) ?></td></tr>
    <tr><td>Discount</td><td align="right"><?= fmt($discount) ?></td></tr>
    <tr>
      <td><strong>Total Payable</strong></td>
      <td align="right"><strong><?= fmt($total) ?></strong></td>
    </tr>
    <tr><td>Cash</td><td align="right"><?= fmt($cash) ?></td></tr>
    <tr><td>Change</td><td align="right"><?= fmt($change) ?></td></tr>
  </table>

</div>
