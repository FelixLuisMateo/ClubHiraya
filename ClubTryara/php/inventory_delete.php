<?php
include 'db_connect.php';
$id = $_POST['id'];
$conn->query("DELETE FROM foods WHERE id=$id");
header("Location: inventory.php");
?>
