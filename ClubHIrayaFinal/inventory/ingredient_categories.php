<?php
// ingredient_categories.php
require 'db_connect.php';

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add or update
    $name = trim($_POST['category_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if (!empty($_POST['category_id'])) {
        $cid = (int)$_POST['category_id'];
        $stmt = $conn->prepare("UPDATE ingredient_category SET category_name=?, description=? WHERE category_id=?");
        $stmt->bind_param("ssi", $name, $desc, $cid);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO ingredient_category (category_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $desc);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: ingredient_categories.php");
    exit;
}

if ($action === 'delete' && $id) {
    $stmt = $conn->prepare("DELETE FROM ingredient_category WHERE category_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ingredient_categories.php");
    exit;
}

$categories = $conn->query("SELECT * FROM ingredient_category ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Ingredient Categories</title>
  <link rel="stylesheet" href="inventory.css">
</head>
<body>
  <div class="topbar">
    <div class="brand"><h1>Categories</h1></div>
    <div class="navlinks">
      <a href="ingredients.php">Ingredients</a>
      <a href="menu_items.php">Menu Items</a>
      <a href="inventory_transaction.php">Transactions</a>
    </div>
  </div>

    <div class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h2>Ingredient Categories</h2>
        <button class="btn" onclick="document.getElementById('formbox').style.display='block'">+ Add Category</button>
      </div>

      <div style="margin-top:12px">
        <?php while($c = $categories->fetch_assoc()): ?>
          <div class="table-row" style="grid-template-columns:1fr 220px;">
            <span><strong><?=htmlspecialchars($c['category_name'])?></strong><div class="small-muted"><?=htmlspecialchars($c['description'])?></div></span>
            <div class="action-buttons">
              <a class="btn small" href="ingredient_categories.php?action=edit&id=<?=$c['category_id']?>" onclick="editCategory(<?=htmlspecialchars(json_encode($c))?>);return false">Edit</a>
              <a class="btn small secondary" href="ingredient_categories.php?action=delete&id=<?=$c['category_id']?>" onclick="return confirm('Delete category?')">Delete</a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>

      <div id="formbox" class="form-card" style="display:none;margin-top:12px;">
        <form method="post" id="categoryForm">
          <input type="hidden" name="category_id" id="category_id">
          <div class="form-group">
            <label>Category name</label>
            <input name="category_name" id="category_name" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <input name="description" id="description">
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
function editCategory(obj) {
  document.getElementById('formbox').style.display='block';
  document.getElementById('category_id').value = obj.category_id;
  document.getElementById('category_name').value = obj.category_name;
  document.getElementById('description').value = obj.description || '';
}
</script>
</body>
</html>
