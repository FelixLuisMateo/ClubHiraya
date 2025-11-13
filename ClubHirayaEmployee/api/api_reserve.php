<?php
include '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table_no = $_POST['table_no'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO reservations (table_no, status) VALUES (?, ?)");
    $stmt->bind_param("is", $table_no, $status);

    if ($stmt->execute()) {
        echo "Reservation saved.";
    } else {
        echo "Error.";
    }
}
?>
