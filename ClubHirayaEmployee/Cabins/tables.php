<?php
// tables.php - summary of reserved/occupied items posted via hidden payload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Cabins.html');
    exit;
}
$raw = $_POST['payload'] ?? '[]';
$data = json_decode($raw, true);
if (!is_array($data)) $data = [];

function priceOf($type, $days) {
    if ($type === 'cabin') return 5000 * max(1, intval($days));
    if ($type === 'hut') return 1000 * max(1, intval($days));
    return 0;
}
$total = 0;
foreach ($data as $d) {
    $total += priceOf($d['type'] ?? '', $d['days'] ?? 0);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>Reserved Summary</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f6;color:#111;padding:24px}
    .card{background:#fff;padding:18px;border-radius:8px;max-width:900px;margin:0 auto;box-shadow:0 6px 0 rgba(0,0,0,0.08)}
    h1{margin-top:0}
    .row{display:flex;justify-content:space-between;padding:10px;border-bottom:1px solid #eee}
    .total{font-weight:900;text-align:right;padding:12px}
    a.button{display:inline-block;margin-top:16px;padding:10px 16px;background:#2b2fc4;color:#fff;border-radius:8px;text-decoration:none}
  </style>
</head>
<body>
  <div class="card">
    <h1>Reserved / Occupied Items</h1>
    <?php if (count($data) === 0): ?>
      <p>No items in payload.</p>
      <a href="index.html" class="button">Back to Map</a>
    <?php else: ?>
      <?php foreach ($data as $i => $item): 
        $id = htmlspecialchars($item['id'] ?? '');
        $type = htmlspecialchars($item['type'] ?? '');
        $cust = htmlspecialchars($item['customer'] ?? '');
        $days = intval($item['days'] ?? 0);
        $price = priceOf($type, $days);
      ?>
        <div class="row">
          <div><?php echo ($i+1) . ". " . $id . " (" . strtoupper($type) . ")"; ?></div>
          <div><?php echo $cust ?: '-'; ?></div>
          <div><?php echo $days ? $days . " day(s)" : '-'; ?></div>
          <div><?php echo $price ? '₱' . number_format($price, 2) : '—'; ?></div>
        </div>
      <?php endforeach; ?>
      <div class="total">Total: <?php echo '₱' . number_format($total, 2); ?></div>
      <a href="index.html" class="button">Back to Map</a>
    <?php endif; ?>
  </div>
</body>
</html>