<?php
require_once "../php/db_connect.php"; // adjust path if needed
header("Content-Type: application/json; charset=utf-8");

$sql = "SELECT id, category FROM menu_category ORDER BY id ASC";
$result = mysqli_query($conn, $sql);

$categories = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = [
            "id" => (int)$row["id"],
            "category" => $row["category"]
        ];
    }
}

echo json_encode($categories);
exit;
?>
