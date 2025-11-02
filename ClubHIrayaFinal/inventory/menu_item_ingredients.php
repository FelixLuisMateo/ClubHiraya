<?php
// menu_item_ingredients.php
require 'db_connect.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
  header("Location: menu_items.php");
  exit;
}

$stmt = $conn->prepare("SELECT * FROM menu_item WHERE menu_item_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$menu = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt2 = $conn->prepare("
  SELECT mii.*, i.name AS ingredient_name, i.unit AS base_unit
  FROM menu_item_ingredient mii
  JOIN ingredient i ON mii.ingredient_id = i.ingredient_id
  WHERE mii.menu_item_id = ?
  ORDER BY mii.mii_id ASC
");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$rows = $stmt2->get_result();
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Recipe - <?=htmlspecialchars($menu['name'] ?? '')?></title>
  <link rel="stylesheet" href="inventory.css">
</head>
<body>
  <div class="topbar">
    <div class="brand"><h1>Recipe</h1></div>
    <div class="navlinks">
      <a href="menu_items.php">Back to Menu</a>
      <a href="ingredients.php">Ingredients</a>
      <a href="inventory_transaction.php">Transactions</a>
    </div>
  </div>

    <div class="panel">
      <h2><?=htmlspecialchars($menu['name'])?> — Ingredients</h2>
      <?php if ($rows->num_rows === 0): ?>
        <div class="empty-state">No recipe defined for this menu item.</div>
      <?php else: ?>
        <div class="recipe-box">
          <?php while($r = $rows->fetch_assoc()): ?>
            <div class="recipe-row">
              <div>
                <strong><?=htmlspecialchars($r['ingredient_name'])?></strong>
                <div class="small-muted"><?=htmlspecialchars($r['unit'])?> per serving</div>
              </div>
              <div style="text-align:right">
                <div style="font-weight:700;"><?=number_format($r['quantity'],4)?> <?=htmlspecialchars($r['unit'])?></div>
                <div class="small-muted">base unit: <?=htmlspecialchars($r['base_unit'])?></div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php endif; ?>
      <div style="margin-top:10px;">
        <a class="btn" href="menu_items.php">Back</a>
      </div>
    </div>
  </div>
</body>
</html>
