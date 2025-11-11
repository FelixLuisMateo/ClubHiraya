<?php
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

$id = $_GET['id'] ?? 0;
$order = $conn->query("SELECT * FROM sales_report WHERE id = $id")->fetch_assoc();

if (!$order) {
  echo "<p>Order not found.</p>";
  exit;
}

$date = date("F d, Y h:i:s A", strtotime($order['datetime']));
?>
<h2>Order Details</h2>
<div class="buttons">
  <button onclick="window.print()">🖨️ Print</button>
  <button onclick="exportCSV(<?= $id ?>)">📦 Export CSV</button>
</div>
<p><strong>Date:</strong> <?= $date ?></p>
<p><strong>Payment:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
<p><strong>Discount:</strong> <?= htmlspecialchars($order['discount_type']) ?></p>
<p><strong>Cabin:</strong> <?= htmlspecialchars($order['cabin_name'] ?? '') ?></p>
<p><strong>Cabin Price:</strong> ₱<?= number_format($order['reserved_price'], 2) ?></p>

<table>
  <thead>
    <tr><th>Qty</th><th>Item</th><th>Unit</th><th>Total</th></tr>
  </thead>
  <tbody>
  <?php
  $items = $conn->query("SELECT * FROM sales_report_items WHERE sales_id = $id");
  while ($item = $items->fetch_assoc()) {
    echo "<tr>
            <td>{$item['qty']}</td>
            <td>{$item['item_name']}</td>
            <td>₱" . number_format($item['unit_price'], 2) . "</td>
            <td>₱" . number_format($item['total_price'], 2) . "</td>
          </tr>";
  }
  ?>
  </tbody>
</table>

<p><strong>Note:</strong> <?= htmlspecialchars($order['note'] ?? '') ?></p>

<div class="totals">
  <p>Subtotal: ₱<?= number_format($order['subtotal'], 2) ?></p>
  <p>Service: ₱<?= number_format($order['service_charge'], 2) ?></p>
  <p>Tax: ₱<?= number_format($order['tax'], 2) ?></p>
  <p>Discount: ₱<?= number_format($order['discount_amount'], 2) ?></p>
  <p>Total Payable: ₱<?= number_format($order['total'], 2) ?></p>
  <p>Cash: ₱<?= number_format($order['cash'], 2) ?></p>
  <p>Change: ₱<?= number_format($order['change'], 2) ?></p>
</div>

<script>
function exportCSV(id) {
  window.location.href = 'export_sales_csv.php?id=' + id;
}
</script>
