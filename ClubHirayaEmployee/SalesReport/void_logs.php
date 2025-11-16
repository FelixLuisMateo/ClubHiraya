<?php
// Restrict access to admins/managers
require_once __DIR__ . '/../includes/require_admin.php';

// DB connection (optional path - adjust if your project uses a different path)
$connectPath = __DIR__ . '/../php/db_connect.php';
if (file_exists($connectPath)) {
    require_once $connectPath;
} else {
    // try the sibling path as fallback
    if (file_exists(__DIR__ . '/../db_connect.php')) {
        require_once __DIR__ . '/../db_connect.php';
    } else {
        $conn = null;
    }
}
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
    .topbar { display:flex; gap:12px; align-items:center; }
    .topbar .search-section { flex:1; }
    .topbar-buttons { display:flex; gap:10px; align-items:center; margin-left:auto; }
    .topbar-buttons a { text-decoration:none; padding:8px 16px; border-radius:8px; background:#ddd; color:#222; font-weight:600; transition:0.2s; }
    .topbar-buttons a:hover { background:#ccc; }
    .topbar-buttons a.active { background:linear-gradient(135deg,#dc2626,#a20505); color:#fff; }
    .voided-badge { background:#dc2626; color:#fff; font-weight:bold; padding:3px 8px; border-radius:6px; font-size:11px; box-shadow:0 1px 4px rgba(0,0,0,0.3); display:inline-block; }
    .small-muted { color:#666; font-size:13px; }
  </style>
</head>

<body<?php if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']) echo ' class="dark-mode"'; ?>>
  <!-- Sidebar include -->
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="main-content" role="main" style="padding:22px;">
    <div class="topbar" style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
      <div class="search-section">
        <input type="text" id="searchBox" class="search-input" placeholder="Search voided order..." aria-label="Search voided orders" style="width:100%; padding:8px 10px; border-radius:8px; border:1px solid #ccc;">
      </div>
      <div class="topbar-buttons">
        <a href="Sales_Report.php">Sales Report</a>
        <a href="report_sales.php">Summary Report</a>
        <a href="void_logs.php" class="active">Void Logs</a>
      </div>
    </div>

    <div class="sales-container">
      <!-- LEFT PANEL -->
      <div class="left-panel" aria-label="Voided orders list">
        <div class="orders-list" id="ordersList">
          <?php
          $logs = [];
          if ($conn) {
            // Ensure the tables exist (defensive)
            $query = "
              SELECT v.sales_id AS sale_id, s.total_amount, s.created_at, v.voided_by, v.voided_at, s.note
              FROM sales_void_log v
              LEFT JOIN sales_report s ON v.sales_id = s.id
              ORDER BY v.voided_at DESC
              LIMIT 500
            ";
            if ($res = $conn->query($query)) {
              while ($r = $res->fetch_assoc()) $logs[] = $r;
              $res->free();
            }
          }

          if (empty($logs)) {
            echo '<div class="order-card"><div class="meta"><span class="label">No voided sales found</span><span class="sub small-muted">There are no void log entries.</span></div><div class="amount">—</div></div>';
          } else {
            foreach ($logs as $log) {
              $id = (int)($log['sale_id'] ?? 0);
              $total = number_format($log['total_amount'] ?? 0, 2);
              $createdAt = !empty($log['created_at']) ? date('M d, Y h:i A', strtotime($log['created_at'])) : 'Unknown';
              $voidedAt = !empty($log['voided_at']) ? date('M d, Y h:i A', strtotime($log['voided_at'])) : '';
              $by = htmlspecialchars($log['voided_by'] ?? 'Unknown');
              $note = htmlspecialchars(substr(($log['note'] ?? ''), 0, 80));
              echo '<div class="order-card" data-id="'.htmlspecialchars($id).'" onclick="loadVoidDetail('.$id.', this)">';
              echo '<div class="meta">';
              echo '<span class="label">Order #'.htmlspecialchars($id).'</span>';
              echo '<span class="sub">Created: '.htmlspecialchars($createdAt).'</span>';
              if ($voidedAt) echo '<span class="sub">Voided: '.htmlspecialchars($voidedAt).'</span>';
              echo '<span class="sub">By: '.$by.'</span>';
              if ($note) echo '<span class="sub small-muted">'.htmlspecialchars($note).'</span>';
              echo '</div>';
              echo '<div class="amount">₱ '.$total.'</div>';
              echo '</div>';
            }
          }
          ?>
        </div>
      </div>

      <!-- RIGHT PANEL -->
      <div class="right-panel" aria-label="Voided order details">
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
  // Load void detail by fetching a server-side partial (void_detail.php)
  async function loadVoidDetail(id, el) {
    if (!id) return;
    // toggle active style
    document.querySelectorAll('.order-card').forEach(c => c.classList.remove('active'));
    if (el) el.classList.add('active');

    const panel = document.getElementById('voidDetail');
    panel.innerHTML = '<div style="padding:20px; text-align:center;">Loading...</div>';

    try {
      // Adjust endpoint path if your detail endpoint is in another file
      const res = await fetch('void_detail.php?id=' + encodeURIComponent(id));
      if (!res.ok) throw new Error('Network response was not ok');
      const html = await res.text();
      panel.innerHTML = html;
    } catch (err) {
      console.error(err);
      panel.innerHTML = '<div style="padding:20px; color:red;">Failed to load details.</div>';
    }
  }

  // Simple client-side search/filter for the left list
  (function(){
    const searchBox = document.getElementById('searchBox');
    if (!searchBox) return;
    searchBox.addEventListener('input', function(){
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('.order-card').forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
      });
    });

    // Auto-click first card if present
    window.addEventListener('load', function(){
      const first = document.querySelector('.order-card');
      if (first) first.click();
    });
  })();
</script>
</body>
</html>