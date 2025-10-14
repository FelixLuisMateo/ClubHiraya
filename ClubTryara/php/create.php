<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $image = $_POST['image']; // or handle upload here

    $sql = "INSERT INTO foods (name, price, category, image) VALUES ('$name', '$price', '$category', '$image')";
    if ($conn->query($sql) === TRUE) {
        echo "Food added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
$conn->close();
?>
