<?php
include "db_connect.php"; // include database connection

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name']; 
    $price = $_POST['price'];
    $category = $_POST['category']; 
    $stock = $_POST['stock']; 

     // ✅ Check if product number already exists
     $check_sql = "SELECT name FROM foods WHERE name = '$name'";
     $check_result = mysqli_query($conn, $check_sql);

     if (mysqli_num_rows($check_result) > 0) {
        echo "<div class='alert alert-warning text-center'>
                name  <b>$name</b> already exists!
              </div>";
    } else {
        // ✅ Insert query
        $sql = "INSERT INTO foods (name, price, category, stock) 
                VALUES ('$name', '$price', '$category', '$stock')";

        if (mysqli_query($conn, $sql)) {
            echo "<div class='alert alert-success text-center'>New Item added successfully!</div>";
        } else {
            echo "<div class='alert alert-danger text-center'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow-lg">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0" style="text-align:center">Add New Items</h4>
        </div>
        <div class="card-body">
            <form method="POST">
                <!-- Name -->
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Product Name" required>
                </div>

                <!-- Price -->
                <div class="mb-3">
                    <label class="form-label">Price</label>
                    <input type="text" name="price" class="form-control" placeholder="Product Price"required>
                </div>

                <!-- Category -->
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" placeholder="Main Course, Appetizer, Soup, Salad, Seafoods, Pasta & Noodles, Sides, Drinks" required>
                </div>

                 <!-- Stock -->
                <div class="mb-3">
                    <label class="form-label">Stock</label>
                    <input type="text" name="stock" class="form-control" placeholder="Product Quantity" required>
                </div>

                <!-- Buttons -->
                <div class="d-flex justify-content-between">
                    <a href="inventory.php" class="btn btn-secondary">Back</a>
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>