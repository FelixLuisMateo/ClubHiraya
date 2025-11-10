<?php
session_start();

include 'ThemeSettings.php';
include 'SystemSettings.php';
include 'NotificationsSettings.php';
include 'BackupRestore.php';
include 'ChangePassword.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Tryara</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Styles -->
    <link rel="stylesheet" href="css/style.css">

    <!-- Load the app.js file that requests products from php/get_products.php -->
    <script defer src="js/app.js"></script>
</head>
<body 
<?php
  if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']) echo ' class="dark-mode"';
  if (isset($_SESSION['accent_color'])) {
    $accent = $_SESSION['accent_color'];
    $gradientMap = [
      '#d33fd3' => ['#d33fd3', '#a2058f'],
      '#4b4bff' => ['#4b4bff', '#001b89'],
      '#bdbdbd' => ['#bdbdbd', '#7a7a7a'],
    ];
    $g = $gradientMap[$accent] ?? $gradientMap['#d33fd3'];
    echo ' style="--accent-start: '.$g[0].'; --accent-end: '.$g[1].';"';
  }
?>>
    <noscript>
        <div class="noscript-warning">This app requires JavaScript to function correctly. Please enable JavaScript.</div>
    </noscript>

    <!-- Sidebar -->
    <aside class="sidebar" role="complementary" aria-label="Sidebar">
        <div class="sidebar-header">
            <img src="../assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
        </div>

        <nav class="sidebar-menu" class="sidebar-btn">
            <a href="../admin_dashboard.php" class="sidebar-btn" aria-current="page">
                <span class="sidebar-icon"><img src="../assets/logos/home.png" alt="Home icon"></span>
                <span>Home</span>
            </a>
            <a href="../tables/tables.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../assets/logos/table.png" alt="Tables icon"></span>
                <span>Cabins</span>
            </a>
            <a href="../inventory/inventory.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../assets/logos/inventory.png" alt="Inventory icon"></span>
                <span>Inventory</span>
            </a>
            <a href="../salesreport/../SalesReport/sales_report.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../assets/logos/sales.png" alt="Sales report icon"></span>
                <span>Sales Report</span>
            </a>
            <a href="../settings/settings.php" class="sidebar-btn active">
                <span class="sidebar-icon"><img src="../assets/logos/setting.png" alt="Settings icon"></span>
                <span>Settings</span>
            </a>
        </nav>

        <div style="flex:1" aria-hidden="true"></div>

        <button class="sidebar-logout" type="button" aria-label="Logout">
            <span>Logout</span>
        </button>
    </aside>

    <!-- Main Content -->
    <main class="main-content" role="main" aria-label="Main content">

<link rel="stylesheet" href="settings.css">

<div class="settings-container">
    <div class="settings-box">
        <?php renderChangePassword(); ?>
    </div>
    <div class="settings-row">
        <div class="settings-box"><?php renderThemeSettings(); ?></div>
        <div class="settings-box"><?php renderSystemSettings(); ?></div>
    </div>
    <div class="settings-row">
        <div class="settings-box"><?php renderNotificationsSettings(); ?></div>
        <div class="settings-box"><?php renderBackupRestore(); ?></div>
    </div>
</div>