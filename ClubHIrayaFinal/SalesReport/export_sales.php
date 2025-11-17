<?php
session_start();
require_once __DIR__ . '/../php/db_connect.php';
date_default_timezone_set('Asia/Manila');

// ---- GET FILTERS ----
$range = $_GET['range'] ?? 'week';
$start = $_GET['start'] ?? null;
$end   = $_GET['end'] ?? null;
$filterPayment = $_GET['payment'] ?? '';
$filterTable   = $_GET['table'] ?? '';

if (!$conn) {
    die("Database not connected.");
}

// ---- DATE RANGE HANDLING ----
if (!$start || !$end) {
    // fallback: whole table
    $start = '2000-01-01 00:00:00';
    $end   = '2100-01-01 23:59:59';
}

// ---- SQL QUERY ----
$sql = "SELECT * FROM sales_report WHERE created_at BETWEEN ? AND ?";

$params = [$start, $end];
$types  = "ss";

// optional payment filter
if ($filterPayment !== '') {
    $sql .= " AND payment_method = ?";
    $params[] = $filterPayment;
    $types .= "s";
}

// optional table/cabin filter
if ($filterTable !== '') {
    $sql .= " AND (table_no = ? OR cabin_name = ?)";
    $params[] = $filterTable;
    $params[] = $filterTable;
    $types .= "ss";
}

$sql .= " ORDER BY created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// ---- CSV SETUP ----
$filename = "sales_export_" . date("Ymd_His") . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// echo UTF-8 BOM so Excel opens cleanly
echo "\xEF\xBB\xBF";

$output = fopen("php://output", "w");

// ---- HEADER ROW (bold in Excel automatically) ----
fputcsv($output, [
    'ID','Date','Table','Cabin','Total Amount','Subtotal','Tax','Discount',
    'Discount Type','Payment Method','Payment Details','Cash Given',
    'Change Amount','Status','Voided','Voided At','Voided By','Note'
]);

$totalOverall = 0;

// ---- WRITE ROWS ----
while ($row = $res->fetch_assoc()) {
    $totalOverall += floatval($row['total_amount']);

    fputcsv($output, [
        $row['id'],
        $row['created_at'],
        $row['table_no'],
        $row['cabin_name'],
        $row['total_amount'],
        $row['subtotal'],
        $row['tax'],
        $row['discount'],
        $row['discount_type'],
        $row['payment_method'],
        $row['payment_details'],
        $row['cash_given'],
        $row['change_amount'],
        $row['status'],
        $row['is_voided'],
        $row['voided_at'],
        $row['voided_by'],
        $row['note']
    ]);
}

// ---- SUMMARY SECTION ----
fputcsv($output, []);
fputcsv($output, ["TOTAL SALES:", number_format($totalOverall, 2)]);

fclose($output);
exit;
