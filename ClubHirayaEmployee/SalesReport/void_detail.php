<?php
require_once __DIR__ . '/../php/db_connect.php';
date_default_timezone_set('Asia/Manila');

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo "<div>Invalid ID.</div>";
    exit;
}

$stmt = $conn->prepare("SELECT * FROM sales_report WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "<div>No details found for this voided order.</div>";
    exit;
}

$total = number_format($order['total_amount'], 2);
$date = date('M d, Y h:i A', strtotime($order['created_at']));
$payment = ucfirst($order['payment_method'] ?? 'Cash');
$table = htmlspecialchars($order['table_no'] ?? '—');
?>

<div style="padding:16px;">
  <h2>Order #<?= $id ?> <span style="background:#dc2626;color:#fff;padding:4px 8px;border-radius:6px;font-size:12px;">VOIDED</span></h2>
  <p><strong>Date Created:</strong> <?= $date ?></p>
  <p><strong>Payment Method:</strong> <?= $payment ?></p>
  <p><strong>Cabin/Table:</strong> <?= $table ?></p>
  <hr>
  <p><strong>Total Amount:</strong> ₱<?= $total ?></p>
  <hr>

  <h4>Items:</h4>
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <tr style="background:#f0f0f0;">
      <th align="left">Item</th>
      <th align="center">Qty</th>
      <th align="right">Price</th>
      <th align="right">Line Total</th>
    </tr>
    <?php
    $items = [];
    $stmt = $conn->prepare("SELECT * FROM sales_items WHERE sales_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        echo "<tr>
          <td>".htmlspecialchars($r['item_name'])."</td>
          <td align='center'>".$r['qty']."</td>
          <td align='right'>₱".number_format($r['unit_price'],2)."</td>
          <td align='right'>₱".number_format($r['line_total'],2)."</td>
        </tr>";
    }
    $stmt->close();
    ?>
  </table>
</div>
