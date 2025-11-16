<?php
// export_sales.php
// Exports sales_report + sales_items as JSON download
// Place in your admin php directory (same place as Sales_Report.php)

require_once __DIR__ . '/php/db_connect.php';
date_default_timezone_set('Asia/Manila');

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection not available']);
    exit;
}

// Optional: restrict to admin (uncomment if you use sessions)
session_start();
// if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
//     http_response_code(403);
//     echo json_encode(['ok' => false, 'error' => 'Forbidden']);
//     exit;
// }

set_time_limit(300);

try {
    $out = ['meta' => ['exported_at' => date('c'), 'by' => $_SESSION['user_name'] ?? 'unknown'], 'sales_report' => [], 'sales_items' => []];

    // Fetch sales_report rows
    $q = "SELECT * FROM sales_report ORDER BY id ASC";
    if ($res = $conn->query($q)) {
        while ($r = $res->fetch_assoc()) {
            // convert numeric fields to strings/numbers consistently
            $out['sales_report'][] = $r;
        }
        $res->free();
    }

    // Fetch sales_items rows
    $q2 = "SELECT * FROM sales_items ORDER BY id ASC";
    if ($res2 = $conn->query($q2)) {
        while ($r2 = $res2->fetch_assoc()) {
            $out['sales_items'][] = $r2;
        }
        $res2->free();
    }

    // Output as downloadable file
    $fn = 'sales_export_' . date('Ymd_His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
