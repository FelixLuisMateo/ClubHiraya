<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];
    $image = $_POST['image'];

    $sql = "INSERT INTO foods (name, price, category, stock, image)
            VALUES ('$name', '$price', '$category', '$stock', '$image')";
    if ($conn->query($sql) === TRUE) {
        header("Location: inventory.php");
        exit;
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
