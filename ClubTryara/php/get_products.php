<?php
// Returns products in JSON. Supports GET parameters:
// - category (exact match). Use 'All' or omit for all categories.
// - q (search query; matches name or description)
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$params = [];
$types = '';
$where = [];

$sql = "SELECT id, name, description, price, category, image FROM products";

if ($category !== '' && strtolower($category) !== 'all') {
    $where[] = "category = ?";
    $params[] = $category;
    $types .= 's';
}

if ($q !== '') {
    $where[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $types .= 'ss';
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY name ASC';

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}

if ($params) {
    // bind_param requires variables passed by reference
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

// If images are stored as filenames, prefix them with assets path for client convenience
foreach ($rows as &$r) {
    if (!empty($r['image'])) {
        // If the image path already contains '/', assume it's a path; otherwise look in assets/products/
        if (strpos($r['image'], '/') === false) {
            $r['image'] = 'assets/products/' . $r['image'];
        }
    } else {
        // placeholder image if none provided
        $r['image'] = 'assets/no-image.png';
    }
}

echo json_encode(['data' => $rows]);