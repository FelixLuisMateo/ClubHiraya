<?php
include 'db_connect.php';
$sql = "SELECT t.transaction_id, i.name AS ingredient, t.change_amount, t.unit, t.reason, t.transaction_date
        FROM inventory_transaction t
        JOIN ingredient i ON t.ingredient_id = i.ingredient_id
        ORDER BY t.transaction_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Transactions</title>
<link rel="stylesheet" href="inventory.css">
</head>
<body>
<div class="topbar">
  <h2>Inventory Transactions</h2>
  <a href="ingredients.php" class="btn-nav">Ingredients</a>
  <a href="menu_items.php" class="btn-nav">Menu</a>
</div>

<div class="table-container">
  <table>
    <thead>
      <tr><th>ID</th><th>Ingredient</th><th>Change</th><th>Unit</th><th>Reason</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php while($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $row['transaction_id'] ?></td>
        <td><?= htmlspecialchars($row['ingredient']) ?></td>
        <td class="<?= $row['change_amount'] < 0 ? 'negative' : 'positive' ?>">
          <?= number_format($row['change_amount'], 2) ?>
        </td>
        <td><?= htmlspecialchars($row['unit']) ?></td>
        <td><?= htmlspecialchars($row['reason']) ?></td>
        <td><?= $row['transaction_date'] ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
