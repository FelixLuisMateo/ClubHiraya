<?php
// php/print_receipt_payment.php
// Printable receipt for a saved sales_report entry (includes payment details and reservation).
// Fixes:
// - Render items using the sales_items rows (name, qty, unit_price, line_total).
// - Show reservation / table_no from sales_report.table_no.
// - Show payment details (parsed JSON) and payment method clearly.

require_once __DIR__ . '/db_connect.php';
$sales_id = isset($_GET['sales_id']) ? intval($_GET['sales_id']) : 0;
if (!$sales_id) die('Invalid sales id.');

$stmt = $conn->prepare("SELECT * FROM sales_report WHERE id = ?");
$stmt->bind_param('i', $sales_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sale) die('Sale not found.');

// fetch items
$items = [];
$itq = $conn->prepare("SELECT * FROM sales_items WHERE sales_id = ? ORDER BY id ASC");
$itq->bind_param('i', $sales_id);
$itq->execute();
$res = $itq->get_result();
while ($r = $res->fetch_assoc()) $items[] = $r;
$itq->close();

// helper
function fmt($n) { return '₱' . number_format((float)$n, 2); }

$date = htmlspecialchars($sale['created_at'] ?? date('Y-m-d H:i:s'));
$payment_method = htmlspecialchars($sale['payment_method'] ?? 'unknown');
$note = $sale['note'] ?? '';
$table_no = htmlspecialchars($sale['table_no'] ?? '');
$service = floatval($sale['service_charge'] ?? 0);
$discount = floatval($sale['discount'] ?? 0);
$total_amount = floatval($sale['total_amount'] ?? 0);

// Attempt to extract payment details JSON from note (if appended there)
$payment_details = null;
if (preg_match('/Payment Details:\s*(\{.*\})/s', $note, $m)) {
    $jsonStr = trim($m[1]);
    $decoded = json_decode($jsonStr, true);
    if (is_array($decoded)) $payment_details = $decoded;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt #<?= htmlspecialchars($sales_id) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; padding:18px; color:#111; max-width:820px; margin:0 auto; }
    header { text-align:center; margin-bottom:10px }
    .items { width:100%; border-collapse:collapse; margin-top:6px }
    .items th, .items td { padding:8px 6px; border-bottom:1px solid #eee; text-align:left; }
    .items th { font-weight:700; }
    .right { text-align:right; }
    .totals { margin-top:10px; border-top:2px solid #eee; padding-top:8px; font-weight:700; max-width:420px; margin-left:auto; }
    .payment-meta { margin-top:12px; color:#333; }
    .controls { margin-top:18px; text-align:center; }
    .btn { padding:8px 12px; border-radius:8px; border:none; background:#2b6cb0; color:#fff; text-decoration:none; cursor:pointer; }
    @media print { .no-print { display:none; } }
  </style>
</head>
<body>
  <header>
    <h2>Club Hiraya</h2>
    <div style="color:#666;font-size:13px">Receipt</div>
    <div style="color:#666;font-size:13px"><?= $date ?></div>
    <div style="margin-top:8px; font-weight:700;">Payment: <?= $payment_method ?></div>
    <?php if (!empty($table_no)): ?>
      <div style="margin-top:6px;color:#666;font-size:13px;">Table / Cabin: <?= $table_no ?></div>
    <?php endif; ?>
  </header>

  <table class="items" aria-label="Receipt items">
    <thead>
      <tr><th style="width:56%;">Item</th><th style="width:12%;">Qty</th><th style="width:16%;" class="right">Price</th><th style="width:16%;" class="right">Total</th></tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="4" style="padding:18px;text-align:center;color:#666;">(No items recorded)</td></tr>
      <?php else:
        foreach ($items as $it):
          $name = htmlspecialchars($it['item_name'] ?? $it['name'] ?? 'Item');
          $qty = intval($it['qty'] ?? 1);
          $unit = floatval($it['unit_price'] ?? 0);
          $line = floatval($it['line_total'] ?? ($unit * $qty));
      ?>
      <tr>
        <td><?= $name ?></td>
        <td><?= $qty ?></td>
        <td class="right"><?= fmt($unit) ?></td>
        <td class="right"><?= fmt($line) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <div class="totals">
    <?php
      // If sale row contains explicit fields for subtotal/tax/service compute a reasonable breakdown:
      // We'll compute subtotal from items if available, otherwise rely on stored total_amount - service - discount.
      $computed_subtotal = 0;
      foreach ($items as $it) {
          $computed_subtotal += floatval($it['line_total'] ?? (floatval($it['unit_price'] ?? 0) * intval($it['qty'] ?? 1)));
      }
      if ($computed_subtotal <= 0) {
          // fallback: attempt to compute using total_amount-service-discount
          $computed_subtotal = max(0, $total_amount - $service + $discount);
      }
    ?>
    <div style="display:flex;justify-content:space-between;"><div>Subtotal</div><div><?= fmt($computed_subtotal) ?></div></div>
    <div style="display:flex;justify-content:space-between;"><div>Service Charge</div><div><?= fmt($service) ?></div></div>
    <div style="display:flex;justify-content:space-between;"><div>Discount</div><div><?= fmt($discount) ?></div></div>
    <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:18px;font-weight:900;"><div>Total</div><div><?= fmt($total_amount) ?></div></div>
  </div>

  <?php if (!empty($payment_details)): ?>
    <div class="payment-meta">
      <strong>Payment Details</strong>
      <div style="margin-top:6px;">
        <?php foreach ($payment_details as $k => $v): ?>
          <div><?= htmlspecialchars(ucfirst($k)) ?>: <?= htmlspecialchars(is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE)) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="controls no-print">
    <button onclick="window.print();" class="btn">Print</button>
    <button onclick="returnToPOS();" class="btn" style="background:#6b7280;margin-left:8px;">Close</button>
  </div>

  <script>
    function returnToPOS() {
      try {
        if (window.opener && !window.opener.closed) {
          window.close();
          return;
        }
      } catch (e) {}
      window.location.href = '/index.php';
    }
    // print automatically for convenience but slight delay to ensure render
    window.addEventListener('load', function(){ setTimeout(()=>{ window.print(); }, 300); });
  </script>
</body>
</html>