<?php
session_start();
require_once "order.php";
$order_items = isset($_SESSION['order']) ? $_SESSION['order'] : [];
$discount = isset($_SESSION['discount']) ? floatval($_SESSION['discount']) : 0; // percent, e.g. 0.20 for 20%
$note = isset($_SESSION['order_note']) ? htmlspecialchars($_SESSION['order_note']) : "";
$totals = compute_order($order_items, $discount);
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