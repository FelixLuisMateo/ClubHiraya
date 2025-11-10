<?php
// At the top of files INSIDE subfolders (one level down)
require_once __DIR__ . '/../php/auth.php';
require_login();

// Make this admin-only (most management pages):
require_role('admin');
?>