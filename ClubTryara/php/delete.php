<?php
include 'db_connect.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "DELETE FROM foods WHERE id='$id'";
    if ($conn->query($sql) === TRUE) {
        echo "Food deleted successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
$conn->close();
?>
