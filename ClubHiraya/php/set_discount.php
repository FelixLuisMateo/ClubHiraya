<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);
$_SESSION['discount'] = isset($data['discount']) ? floatval($data['discount']) : 0;
echo json_encode(['ok'=>true]);