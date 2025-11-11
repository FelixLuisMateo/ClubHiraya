<?php
session_start();
require_once __DIR__ . '/../php/db_connect.php';
date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Club Hiraya — Void Logs</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="sales.css">
  <style>
    .sales-container { display:flex; gap:18px; padding:18px; box-sizing:border-box; }
    .left-panel { width:320px; background:#e8e8ea; padding:14px; border-radius:12px; max-height:calc(100vh - 160px); overflow-y:auto; box-sizing:border-box; }
    .orders-list { display:flex; flex-direction:column; gap:12px; }
    .order-card { background:#fff; border-radius:10px; padding:10px 12px; box-shadow:0 6px 12px rgba(0,0,0,0.06); cursor:pointer; display:flex; justify-content:space-between; align-items:flex-start; border:1px solid #ddd; }
    .order-card.active { border-color:#dc2626; box-shadow:0 10px 18px rgba(0,0,0,0.12); transform:translateY(-2px); }
    .order-card .meta { display:flex; flex-direction:column; gap:6px; font-size:13px; max-width:220px; }
    .order-card .meta .label { font-weight:700; }
    .order-card .meta .sub { font-size:12px; color:#666; }
    .order-card .amount { font-size:16px; font-weight:800; white-space:nowrap; }
    .right-panel { flex:1; padding:18px; background:#e8e8ea; border-radius:12px; min-height:420px; box-sizing:border-box; }
    .right-card { background:#fff; border-radius:10px; padding:18px; border:1px solid #eee; min-height:260px; box-sizing:border-box; }
    .order-item-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed #eee; color:#222; }
    .order-item-row:last-child { border-bottom:none; }
    .topbar-buttons { display:flex; gap:10px; align-items:center; margin-left:auto; }
    .topbar-buttons a {
      text-decoration:none; padding:8px 16px; border-radius:8px;
      background:#ddd; color:#222; font-weight:600; transition:0.2s;
    }
    .topbar-buttons a:hover { background:#ccc; }
    .topbar-buttons a.active {
      background:linear-gradient(135deg,#dc2626,#a20505); color:#fff;
    }
    .voided-badge {
      background:#dc2626; color:#fff; font-weight:bold;
      padding:3px 8px; border-radius:6px; font-size:11px;
      box-shadow:0 1px 4px rgba(0,0,0,0.3);
    }
  </style>
</head>

<body <?php
  if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']) echo 'class="dark-mode"';
?>>

<!-- Sidebar identical to other pages -->
<aside class="sidebar" role="complementary">
  <div class="sidebar-header">
    <img src="../../clubtryara/assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
  </div>
  <nav class="sidebar-menu">
    <a href="../employee_dashboard.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/home.png" alt="Home"></span><span>Home</span></a>
    <a href="../tables/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/cabin.png" alt="Tables"></span><span>Cabins</span></a>
    <a href="../inventory/inventory.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
    <a href="Sales_Report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/sales.png" alt="Sales"></span><span>Sales Report</span></a>
    <a href="report_sales.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/sales.png" alt="Summary"></span><span>Summary Report</span></a>
    <a href="void_logs.php" class="sidebar-btn active"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/sales.png" alt="Void"></span><span>Void Logs</span></a>
    <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/setting.png" alt="Settings"></span><span>Settings</span></a>
  </nav>
  <div style="flex:1"></div>
  <button class="sidebar-logout" type="button"><span>Logout</span></button>
</aside>

<main class="main-content" role="main" style="padding:22px;">
  <div class="topbar" style="display:flex; justify-content:space-between; align-items:center;">
    <div class="search-section">
      <input type="text" id="searchBox" class="search-input" placeholder="Search voided order...">
    </div>
    <div class="topbar-buttons">
      <a href="Sales_Report.php">Sales Report</a>
      <a href="report_sales.php">Summary Report</a>
      <a href="void_logs.php" class="active">Void Logs</a>
    </div>
  </div>

  <div style="height:18px;"></div>

  <div class="sales-container">
    <!-- LEFT PANEL -->
    <div class="left-panel">
      <div class="orders-list">
        <?php
        $logs = [];
        $query = "
          SELECT s.id AS sale_id, s.total_amount, s.created_at, v.voided_by, v.voided_at
          FROM sales_void_log v
          LEFT JOIN sales_report s ON v.sales_id = s.id
          ORDER BY v.voided_at DESC
          LIMIT 200
        ";
        if ($res = $conn->query($query)) {
          while ($r = $res->fetch_assoc()) $logs[] = $r;
          $res->free();
        }

        if (empty($logs)) {
          echo '<div class="order-card"><div class="meta"><span class="label">No voided sales found</span></div></div>';
        } else {
          foreach ($logs as $log) {
            $id = (int)$log['sale_id'];
            $total = number_format($log['total_amount'] ?? 0, 2);
            $createdAt = date('M d, Y h:i A', strtotime($log['created_at']));
            $voidedAt = date('M d, Y h:i A', strtotime($log['voided_at']));
            $by = htmlspecialchars($log['voided_by'] ?? 'Admin');
            echo '<div class="order-card" data-id="'.$id.'" onclick="loadVoidDetail('.$id.', this)">';
            echo '<div class="meta">';
            echo '<span class="label">Order #'.$id.'</span>';
            echo '<span class="sub">Created: '.$createdAt.'</span>';
            echo '<span class="sub">Voided: '.$voidedAt.'</span>';
            echo '<span class="sub">By: '.$by.'</span>';
            echo '</div>';
            echo '<div class="amount">₱ '.$total.'</div>';
            echo '</div>';
          }
        }
        ?>
      </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
      <div id="voidDetail" class="right-card">
        <div style="text-align:center; font-size:15px;">
          <p>Select a voided order to view details.</p>
          <p class="voided-badge">VOIDED</p>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
async function loadVoidDetail(id, el) {
  document.querySelectorAll('.order-card').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  const panel = document.getElementById('voidDetail');
  panel.innerHTML = '<div style="padding:20px; text-align:center;">Loading...</div>';
  try {
    const res = await fetch('void_detail.php?id=' + id);
    const html = await res.text();
    panel.innerHTML = html;
  } catch (e) {
    panel.innerHTML = '<div style="color:red;">Failed to load details.</div>';
  }
}

// simple search
document.getElementById('searchBox').addEventListener('input', e => {
  const q = e.target.value.toLowerCase();
  document.querySelectorAll('.order-card').forEach(card => {
    card.style.display = card.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>
