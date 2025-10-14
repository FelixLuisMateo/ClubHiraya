<?php
include 'db_connect.php';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];
    $image = $_POST['image'];

    $sql = "UPDATE foods 
            SET name='$name', price='$price', category='$category', stock='$stock', image='$image' 
            WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        header("Location: inventory.php");
        exit;
    } else {
        echo "Error updating record: " . $conn->error;
    }
}
?>
