<?php
session_start();
$_SESSION['order'] = [];
echo json_encode(['ok'=>1]);