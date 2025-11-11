<?php
// php/print_receipt.php
// Printable receipt. When the user clicks "Close" or "Back to POS" this page will NOT automatically update stock by default.
// Instead it will attempt to call opener.appPayments.openPaymentModal() or rely on the POS opener to perform any needed updates.
// If opener is not present, it will redirect back to the POS without performing any automatic stock update.

$cartJson   = $_POST['cart']   ?? null;
$totalsJson = $_POST['totals'] ?? null;
$metaJson   = $_POST['meta'] ?? null;

// If no POST data, show info page with a back link
if ($cartJson === null) {
    header('X-Robots-Tag: noindex, nofollow', true);
    ?>
    <!doctype html>
    <html>
    <head>
      <meta charset="utf-8" />
      <title>Receipt - Club Hiraya</title>
      <meta name="viewport" content="width=device-width,initial-scale=1" />
      <style>
        body { font-family: Arial, sans-serif; padding: 28px; color: #222; background:#f7f7fb; }
        .card { max-width:720px; margin:40px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 8px 30px rgba(0,0,0,0.06); }
        h1 { margin:0 0 8px 0; font-size:20px; }
        p { color:#444; }
        a.button { display:inline-block; margin-top:16px; padding:10px 16px; background:#d51ecb; color:#fff; border-radius:8px; text-decoration:none; font-weight:700; }
      </style>
    </head>
    <body>
      <div class="card">
        <h1>No receipt data</h1>
        <p>This page was opened without receipt data. Open the Bill Out/Print Receipt function from the POS (index.php) to print a receipt.</p>
        <p><a class="button" href="admin_dashboard.php">Back to POS</a></p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$cart = json_decode($cartJson, true);
$totals = json_decode($totalsJson, true);
$meta = $metaJson ? json_decode($metaJson, true) : null;
if (!is_array($cart)) $cart = [];
if (!is_array($totals)) $totals = [];
$date = date('Y-m-d H:i:s');

function fmt($n) {
    return '₱' . number_format((float)$n, 2);
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Receipt - Club Hiraya</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;padding:20px;color:#111;background:#fff;}
    header{text-align:center;margin-bottom:10px}
    .items{width:100%;border-collapse:collapse;margin-top:10px}
    .items th,.items td{border-bottom:1px solid #eee;padding:10px;text-align:left}
    .right{text-align:right}
    .controls{margin-top:18px;display:flex;gap:10px;justify-content:center}
    .btn{padding:10px 16px;border-radius:8px;border:none;font-weight:700;color:#fff;text-decoration:none;cursor:pointer}
    .btn-print{background:#2b6cb0}
    .btn-back{background:#d51ecb}
    .btn-close{background:#6b7280}
    @media print{ .no-print{display:none} }
  </style>
</head>
<body>
  <header>
    <h2>Club Hiraya</h2>
    <div style="color:#666;font-size:13px;margin-top:4px">Receipt</div>
    <div style="color:#666;font-size:13px"><?= htmlspecialchars($date, ENT_QUOTES) ?></div>
  </header>

  <table class="items" aria-label="Receipt items">
    <thead>
      <tr><th style="width:56%;">Item</th><th style="width:12%;">Qty</th><th style="width:16%;" class="right">Price</th><th style="width:16%;" class="right">Total</th></tr>
    </thead>
    <tbody>
      <?php if (empty($cart)): ?>
        <tr><td colspan="4" style="padding:18px;text-align:center;color:#666;">(No items)</td></tr>
      <?php else: foreach ($cart as $it):
        $name = htmlspecialchars($it['name'] ?? 'Item', ENT_QUOTES);
        $qty = (int)($it['qty'] ?? 0);
        $price = (float)($it['price'] ?? 0.0);
        $line = $price * $qty;
      ?>
        <tr>
          <td><?= $name ?></td>
          <td><?= $qty ?></td>
          <td class="right"><?= fmt($price) ?></td>
          <td class="right"><?= fmt($line) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <table class="totals" style="margin-top:12px;width:100%">
    <tr><td style="color:#444">Subtotal</td><td style="text-align:right;font-weight:700"><?= fmt($totals['subtotal'] ?? 0) ?></td></tr>
    <tr><td style="color:#444">Service Charge</td><td style="text-align:right;font-weight:700"><?= fmt($totals['serviceCharge'] ?? 0) ?></td></tr>
    <tr><td style="color:#444">Tax</td><td style="text-align:right;font-weight:700"><?= fmt($totals['tax'] ?? 0) ?></td></tr>
    <tr><td style="color:#444">Discount</td><td style="text-align:right;font-weight:700"><?= fmt($totals['discountAmount'] ?? 0) ?></td></tr>
    <tr style="border-top:2px solid #eee;"><td><strong>Payable</strong></td><td style="text-align:right;font-weight:900"><strong><?= fmt($totals['payable'] ?? 0) ?></strong></td></tr>
  </table>

  <div style="margin-top:8px;color:#333">
    <strong>Reservation:</strong>
    <?php if (!empty($meta['reserved'])): ?>
      <?php $r = $meta['reserved']; ?>
      <div>Table: <?= htmlspecialchars($r['table_number'] ?? ($r['id'] ?? '—')) ?> — <?= htmlspecialchars($r['name'] ?? '—') ?></div>
      <div>Party size: <?= htmlspecialchars($r['party_size'] ?? '—') ?></div>
      <div>Price: <?= '₱' . number_format(floatval($r['price'] ?? 0),2) ?></div>
    <?php else: ?>
      <div>No reservation</div>
    <?php endif; ?>
  </div>

  <div class="controls no-print">
    <button id="printBtn" class="btn btn-print" type="button" onclick="window.print();">Print</button>
    <a id="backToPos" class="btn btn-back" href="admin_dashboard.php" onclick="event.preventDefault(); returnToPOS();">Back to POS</a>
    <a id="closeBtn" class="btn btn-close" href="#" onclick="event.preventDefault(); returnToPOS();">Close</a>
  </div>

  <script>
    const __receipt_cart = <?= json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const __receipt_totals = <?= json_encode($totals, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const __receipt_meta = <?= json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;

    function returnToPOS(){
      try {
        if (window.opener && window.opener.appPayments && typeof window.opener.appPayments.openPaymentModal === 'function'){
          try { window.close(); } catch(e) { window.location.href = 'index.php'; }
          return;
        }
      } catch(e){ console.error('opener communication failed', e); }
      window.location.href = 'index.php';
    }

    window.addEventListener('load', function() {
      if (window.opener) {
        setTimeout(function() { window.print(); }, 400);
      }
    });
  </script>
</body>