<?php
require_once __DIR__ . '/../php/auth.php';
require_login();
// Allow both admin and employee to access settings
require_role(['admin', 'employee']);
?>