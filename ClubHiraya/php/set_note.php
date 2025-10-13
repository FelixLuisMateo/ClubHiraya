<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);
$_SESSION['order_note'] = isset($data['note']) ? trim($data['note']) : '';
echo json_encode(['ok'=>true]);