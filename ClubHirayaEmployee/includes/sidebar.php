<?php
// sidebar.php - include this where your sidebar markup goes
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
?>
<aside class="sidebar" role="complementary" aria-label="Sidebar">
  <div class="sidebar-header"><img src="../assets/logos/logo1.png" class="sidebar-header-img" alt="logo"></div>
  <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
      <a href="../employee_dashboard.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/home.png" alt="Home"></span><span>Home</span></a>
      <a href="../tables/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/cabin.png" alt="Cabins"></span><span>Cabins</span></a>

      <?php if (in_array($role, ['admin', 'manager'], true)): ?>
        <a href="../inventory/inventory.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
        <a href="../SalesReport/sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/sales.png" alt="Sales"></span><span>Sales Report</span></a>
      <?php endif; ?>

      <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/setting.png" alt="Settings"></span><span>Settings</span></a>
  </nav>
</aside>