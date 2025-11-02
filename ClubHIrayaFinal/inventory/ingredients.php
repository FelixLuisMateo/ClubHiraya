<?php
// ingredients.php
require 'db_connect.php';

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle delete via GET
if ($action === 'delete' && $id) {
    $stmt = $conn->prepare("DELETE FROM ingredient WHERE ingredient_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ingredients.php");
    exit;
}

// Handle add / edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $category = (int)($_POST['category_id'] ?? 0);
    $unit = $_POST['unit'] ?? '';
    $stock = $_POST['current_stock'] ?? 0;
    $cost = $_POST['cost_per_unit'] ?? 0;
    $supplier = $_POST['supplier'] ?? '';
    $par = $_POST['par_level'] ?? 0;
    $ing_id = isset($_POST['ingredient_id']) && $_POST['ingredient_id'] !== '' ? (int)$_POST['ingredient_id'] : 0;

    if ($ing_id) {
        $stmt = $conn->prepare("UPDATE ingredient SET category_id=?, name=?, unit=?, par_level=?, current_stock=?, cost_per_unit=?, supplier=? WHERE ingredient_id=?");
        $stmt->bind_param("issdddsi", $category, $name, $unit, $par, $stock, $cost, $supplier, $ing_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO ingredient (ingredient_id, category_id, name, unit, par_level, current_stock, cost_per_unit, supplier) VALUES (NULL,?,?,?,?,?,?,?)");
        // We set ingredient_id to NULL to allow user to use auto numbering if desired.
        $stmt->bind_param("issddds", $category, $name, $unit, $par, $stock, $cost, $supplier);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: ingredients.php");
    exit;
}

// Fetch lists
$categories = $conn->query("SELECT * FROM ingredient_category ORDER BY category_name");
$result = $conn->query("SELECT i.*, ic.category_name FROM ingredient i JOIN ingredient_category ic ON i.category_id=ic.category_id ORDER BY i.ingredient_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Ingredients</title>
  <link rel="stylesheet" href="inventory.css">
</head>
<body>
  <div class="topbar">
    <div class="brand"><h1>Ingredients</h1></div>
    <div class="navlinks">
      <a href="ingredient_categories.php">Categories</a>
      <a href="menu_items.php">Menu Items</a>
      <a href="inventory_transaction.php">Transactions</a>
    </div>
  </div>

    <div class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h2>Ingredients</h2>
        <button class="btn" onclick="document.getElementById('formbox').style.display='block'">+ Add Ingredient</button>
      </div>

      <div class="table-header" style="margin-top:12px;">
        <span>ID</span><span>Name</span><span>Category</span><span>Stock</span><span>Unit</span><span>Cost</span><span>Actions</span>
      </div>

      <?php if ($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
          <div class="table-row">
            <span><?= $row['ingredient_id'] ?></span>
            <span><?= htmlspecialchars($row['name']) ?></span>
            <span><?= htmlspecialchars($row['category_name']) ?></span>
            <span><?= number_format($row['current_stock'],2) ?></span>
            <span><?= htmlspecialchars($row['unit']) ?></span>
            <span>₱<?= number_format($row['cost_per_unit'],2) ?></span>
            <div class="action-buttons">
              <a class="btn small" href="ingredients.php?action=edit&id=<?=$row['ingredient_id']?>" onclick="editIngredient(<?=htmlspecialchars(json_encode($row))?>);return false">Edit</a>
              <a class="btn small secondary" href="ingredients.php?action=delete&id=<?=$row['ingredient_id']?>" onclick="return confirm('Delete this ingredient?')">Delete</a>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-state">No ingredients yet</div>
      <?php endif; ?>

      <div id="formbox" class="form-card" style="display:none;margin-top:12px;">
        <form method="post">
          <input type="hidden" name="ingredient_id" id="ingredient_id">
          <div class="form-grid">
            <div class="form-group">
              <label>Name</label>
              <input type="text" name="name" id="name" required>
            </div>
            <div class="form-group">
              <label>Category</label>
              <select name="category_id" id="category_id" required>
                <?php
                $categories->data_seek(0);
                while($c = $categories->fetch_assoc()): ?>
                  <option value="<?=$c['category_id']?>"><?=htmlspecialchars($c['category_name'])?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Unit</label>
              <input name="unit" id="unit" required>
            </div>
            <div class="form-group">
              <label>Current Stock</label>
              <input type="number" step="0.01" name="current_stock" id="current_stock" required>
            </div>
            <div class="form-group">
              <label>Cost per Unit (₱)</label>
              <input type="number" step="0.01" name="cost_per_unit" id="cost_per_unit" required>
            </div>
            <div class="form-group">
              <label>Supplier</label>
              <input name="supplier" id="supplier">
            </div>
            <div class="form-group">
              <label>Par Level</label>
              <input type="number" step="0.01" name="par_level" id="par_level" value="0">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn" type="submit">Save</button>
            <button class="btn secondary" type="button" onclick="document.getElementById('formbox').style.display='none'">Cancel</button>
          </div>
        </form>
      </div>

    </div>
  </div>

<script>
function editIngredient(obj) {
  document.getElementById('formbox').style.display='block';
  document.getElementById('ingredient_id').value = obj.ingredient_id;
  document.getElementById('name').value = obj.name;
  // category select
  var sel = document.getElementById('category_id');
  for (var i=0;i<sel.options.length;i++){
    if (sel.options[i].value == obj.category_id) { sel.selectedIndex = i; break; }
  }
  document.getElementById('unit').value = obj.unit;
  document.getElementById('current_stock').value = obj.current_stock;
  document.getElementById('cost_per_unit').value = obj.cost_per_unit;
  document.getElementById('supplier').value = obj.supplier;
  document.getElementById('par_level').value = obj.par_level;
}
</script>
</body>
</html>
