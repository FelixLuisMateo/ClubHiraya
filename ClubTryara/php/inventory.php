<?php
include 'db_connect.php';
$result = $conn->query("SELECT * FROM foods ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Management</title>
<link rel="stylesheet" href="css/inventory.css">
</head>
<body>
<h1>Inventory Management</h1>

<!-- Add Food Form -->
<form action="inventory_create.php" method="POST" enctype="multipart/form-data">
    <h2>Add New Food</h2>
    <input type="text" name="name" placeholder="Food Name" required><br>
    <input type="number" name="price" placeholder="Price" required><br>
    <input type="text" name="category" placeholder="Category" required><br>
    <input type="number" name="stock" placeholder="Stock (e.g. 100)" required><br>
    <input type="text" name="image" placeholder="Image path (e.g. assets/lechon.jpg)" required><br>
    <button type="submit">Add Food</button>
</form>

<hr>

<!-- Inventory Table -->
<h2>Food List</h2>
<table border="1" cellpadding="8" cellspacing="0">
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Price</th>
    <th>Category</th>
    <th>Stock</th>
    <th>Image</th>
    <th>Actions</th>
</tr>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td>₱<?= number_format($row['price'], 2) ?></td>
    <td><?= htmlspecialchars($row['category']) ?></td>
    <td><?= $row['stock'] ?></td>
    <td><img src="<?= $row['image'] ?>" alt="" width="50"></td>
    <td>
        <form action="inventory_edit.php" method="POST" style="display:inline;">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <button type="submit">Edit</button>
        </form>
        <form action="inventory_delete.php" method="POST" style="display:inline;">
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <button type="submit" onclick="return confirm('Delete this item?')">Delete</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>
