<?php
require_once "db_connect.php";
require_once "order.php";

$order_id = intval($_GET['order_id']);
$order = $conn->query("SELECT * FROM orders WHERE id = $order_id")->fetch_assoc();
$discount = floatval($order['discount']);
$note = htmlspecialchars($order['note']);

$items = [];
$sql = "SELECT f.name, f.price, i.qty 
        FROM order_items i
        JOIN foods f ON i.food_id = f.id
        WHERE i.order_id = $order_id";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $items[] = ['name'=>$row['name'], 'price'=>$row['price'], 'qty'=>$row['qty']];
}
$totals = compute_order($items, $discount);
?>

<div class="compute-actions">
    <button class="compute-btn add" id="addManualItemBtn">Add</button>
    <button class="compute-btn discount" id="discountBtn">Discount</button>
    <button class="compute-btn note" id="noteBtn">Note</button>
</div>
<div class="row"><span>Subtotal:</span><span>₱<?= number_format($totals['subtotal'],2) ?></span></div>
<div class="row"><span>Service Charge:</span><span>₱<?= number_format($totals['service_charge'],2) ?></span></div>
<div class="row"><span>Tax:</span><span>₱<?= number_format($totals['tax'],2) ?></span></div>
<div class="row"><span>Discount:</span>
    <span>
        ₱<?= number_format($totals['discount'],2) ?>
        <?php if($discount > 0): ?>
            <span class="discount-applied">(<?= intval($discount*100) ?>% Off)</span>
        <?php endif; ?>
    </span>
</div>
<div class="row final"><span>Payable Amount:</span><span>₱<?= number_format($totals['total'],2) ?></span></div>
<?php if($note): ?>
<div class="row" style="font-size:13px; color:#3a3ac7;"><strong>Note:</strong> <?= $note ?></div>
<?php endif; ?>
