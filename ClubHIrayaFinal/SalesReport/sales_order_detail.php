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

// Fetch items (from sales_items)
$items = [];
$stmt = $conn->prepare("SELECT * FROM sales_items WHERE sales_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $items[] = $row;
$stmt->close();

// Format helper
function fmt($n) {
    return '₱' . number_format((float)$n, 2);
}

// Extract order data based on your SQL columns
$date = date('F d, Y h:i:s A', strtotime($order['created_at']));
$payment = ucfirst($order['payment_method'] ?? 'Cash');
$discountType = $order['discount_type'] ?? 'Regular';
$cabin = $order['cabin_name'] ?: ($order['table_no'] ?: '');
$cabinPrice = $order['cabin_price'] ?? 0;
$note = trim($order['note'] ?? '');

// Financials — MATCHING DATABASE
$subtotal = $order['subtotal'] ?? 0;
$service = $order['service_charge'] ?? 0;
$tax = $order['tax'] ?? 0;
$discount = $order['discount'] ?? 0;
$total = $order['total_amount'] ?? 0;
$cash = $order['cash_given'] ?? 0;
$change = $order['change_amount'] ?? 0;
$isVoided = $order['is_voided'] ?? 0;

// PAYMENT DETAILS JSON
$paymentDetailsHTML = '';
if (!empty($order['payment_details'])) {
    $json = json_decode($order['payment_details'], true);
    if (is_array($json)) {
        if ($payment === 'Gcash') {
            $paymentDetailsHTML = "<div><strong>GCash:</strong> {$json['name']} ({$json['ref']})</div>";
        } elseif ($payment === 'Bank_transfer') {
            $paymentDetailsHTML = "<div><strong>Bank Transfer:</strong> {$json['name']} ({$json['ref']})</div>";
        } elseif ($payment === 'Cash') {
            $paymentDetailsHTML = "<div><strong>Cash Given:</strong> " . fmt($json['cashgiven']) .
                                  "<br><strong>Change:</strong> " . fmt($json['change']) . "</div>";
        }
    }
}
?>

<div style="font-size:16px;">
  <div><strong>Date:</strong> <?= htmlspecialchars($date) ?></div>
  <div><strong>Payment:</strong> <?= htmlspecialchars($payment) ?></div>
  <?= $paymentDetailsHTML ?>
  <div><strong>Discount:</strong> <?= htmlspecialchars($discountType) ?></div>
  <div><strong>Cabin:</strong> <?= htmlspecialchars($cabin) ?></div>
  <div><strong>Cabin Price:</strong> <?= fmt($cabinPrice) ?></div>

  <hr>

  <table style="width:100%; border-collapse:collapse; font-size:15px;">
    <thead>
      <tr><th>Qty</th><th>Item</th><th align="right">Unit</th><th align="right">Total</th></tr>
    </thead>
    <tbody>
    <?php if (empty($items)): ?>
      <tr><td colspan="4" align="center">No items found.</td></tr>
    <?php else: foreach ($items as $it): ?>
      <tr>
        <td><?= $it['qty'] ?></td>
        <td><?= htmlspecialchars($it['item_name'] ?? $it['name']) ?></td>
        <td align="right"><?= fmt($it['unit_price']) ?></td>
        <td align="right"><?= fmt($it['line_total']) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <hr>

  <table style="width:100%; font-size:15px;">
    <tr><td>Subtotal</td><td align="right"><?= fmt($subtotal) ?></td></tr>
    <tr><td>Service</td><td align="right"><?= fmt($service) ?></td></tr>
    <tr><td>Tax</td><td align="right"><?= fmt($tax) ?></td></tr>
    <tr><td>Discount</td><td align="right"><?= fmt($discount) ?></td></tr>
    <tr><td><strong>Total Payable</strong></td><td align="right"><strong><?= fmt($total) ?></strong></td></tr>
    <tr><td>Cash</td><td align="right"><?= fmt($cash) ?></td></tr>
    <tr><td>Change</td><td align="right"><?= fmt($change) ?></td></tr>
  </table>

  <div style="margin-top:16px; display:flex; justify-content:flex-end; gap:10px;">
    <button
      style="padding:8px 14px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer;"
      onclick="window.open('../php/print_receipt_reprint.php?id=<?= $id ?>', '_blank')"
      <?= $isVoided ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
      🧾 Reprint<?= $isVoided ? ' (Voided)' : '' ?>
    </button>

    <button id="voidBtn" data-id="<?= $id ?>"
      style="padding:8px 14px; background:#dc2626; color:#fff; border:none; border-radius:6px; cursor:pointer;"
      <?= $isVoided ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
      ❌ Void<?= $isVoided ? 'ed' : '' ?>
    </button>
  </div>
</div>

<script>
document.addEventListener('click', async function(e) {
  if (e.target && e.target.id === 'voidBtn') {
    const id = e.target.getAttribute('data-id');
    if (!confirm('Void order ' + id + '?')) return;

    const res = await fetch('void_sale.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + id
    });
    const data = await res.json();
    if (data.ok) {
      alert('Order voided.');
      location.reload();
    } else {
      alert('Error: ' + data.error);
    }
  }
});
</script>
