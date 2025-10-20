<?php
// get_products.php
// Place this in ClubTryara/php/get_products.php
// Configure DB credentials below or include a separate config.php with constants.
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // default for local installations like XAMPP; change if needed
$DB_NAME = 'restaurant';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    // If accessed by browser, it's helpful to return JSON error for the ajax caller
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database connection failed: ' . $mysqli->connect_error]);
    exit;
}


// Allow CORS for local dev if needed (adjust in production)
header("Access-Control-Allow-Origin: *");

// Accept optional query params: category, q (search)
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Prepare base query
$sql = "SELECT id, name, price, category, image, description FROM foods WHERE 1=1";
$params = [];
$types = "";

// Filter by category if provided and not "All"
if ($category !== '' && strtolower($category) !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Search by name or description
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql .= " ORDER BY category, name";

// Prepare statement
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
    // Adjust image path if you store filename only. Assume images are under ../assets/foods/
    $img = $row['image'];
    if ($img && !preg_match('/^https?:\\/\\//', $img)) {
        // relative path to project's assets
        $img = '/ClubTryara/assets/' . ltrim($img, '/'); // adjust path if needed
    }

    $food = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'price' => (float)$row['price'],
        'category' => $row['category'],
        'image' => $img,
        'description' => $row['description']
    ];
    $foods[] = $food;
    $categories_set[$row['category']] = true;
}

$stmt->close();

// Return categories as an array (unique). Order will be applied on client.
$categories = array_keys($categories_set);

echo json_encode([
    'categories' => $categories,
    'foods' => $foods
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$mysqli->close();