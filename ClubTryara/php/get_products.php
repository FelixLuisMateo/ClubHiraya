<?php
// get_products.php
// Located at ClubTryara/php/get_products.php

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // default for XAMPP
$DB_NAME = 'restaurant';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database connection failed: ' . $mysqli->connect_error]);
    exit;
}

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT id, name, price, category, image, description FROM foods WHERE 1=1";
$params = [];
$types = "";

// Filter by category if provided
if ($category !== '' && strtolower($category) !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Search filter
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql .= " ORDER BY category, name";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'DB prepare failed: ' . $mysqli->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$foods = [];
$categories_set = [];

while ($row = $result->fetch_assoc()) {
    // ---- FIXED IMAGE PATH HANDLING ----
    $img = trim($row['image']);

    // if it’s only the filename (e.g., "lechon_baka.jpg")
    if ($img && !preg_match('/^https?:\\/\\//', $img)) {
        // use relative path from index.php (one level up from /php/)
        $img = '../assets/' . ltrim($img, '/');
    }

    // fallback image if missing or file not found
    if (!file_exists(__DIR__ . '/../assets/' . basename($img))) {
        $img = '../assets/placeholder.png';
    }

    $foods[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'price' => (float)$row['price'],
        'category' => $row['category'],
        'image' => $img,
        'description' => $row['description']
    ];
    $categories_set[$row['category']] = true;
}

$stmt->close();

$categories = array_keys($categories_set);

// final JSON output
echo json_encode([
    'categories' => $categories,
    'foods' => $foods
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$mysqli->close();
?>
