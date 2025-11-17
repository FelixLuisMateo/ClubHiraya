<?php
session_start();

/**
 * Sales_Report.php (updated)
 * - Search only matches Order ID
 * - Left panel loads all rows (no LIMIT) so scrolling doesn't cut off
 * - Minimal changes elsewhere to keep original layout & behavior
 */

date_default_timezone_set('Asia/Manila');

// database connection include
$connectPath = __DIR__ . '/../php/db_connect.php';
if (file_exists($connectPath)) {
    require_once $connectPath;
} else {
    $conn = null;
}

// ---------------------------
// Helpers
// ---------------------------
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

function fmt_currency($n) {
    if ($n === null || $n === '') $n = 0;
    return '₱ ' . number_format((float)$n, 2);
}

function parse_payment_summary_from_note_and_payment_details($note, $payment_method, $payment_details) {
    $summary = '';

    if (!empty($payment_details)) {
        $decoded = null;
        if (is_string($payment_details)) {
            $trim = trim($payment_details);
            $decoded = json_decode($trim, true);
        } elseif (is_array($payment_details)) {
            $decoded = $payment_details;
        }

        if (is_array($decoded)) {
            $pm = strtolower((string)$payment_method);
            if ($pm === 'gcash' || $pm === 'bank_transfer') {
                $name = $decoded['name'] ?? ($decoded['payer'] ?? '');
                $ref  = $decoded['ref'] ?? ($decoded['reference'] ?? '');
                $summary = strtoupper($pm === 'gcash' ? 'GCash' : 'Bank') . ($name ? ' — ' . $name : '') . ($ref ? ' (' . $ref . ')' : '');
            } elseif ($pm === 'cash') {
                $given = array_key_exists('given', $decoded) ? (float)$decoded['given'] : null;
                $change = array_key_exists('change', $decoded) ? (float)$decoded['change'] : null;
                $summary = 'Cash';
                if ($given !== null) $summary .= ' (Given: ' . number_format($given,2) . ')';
                if ($change !== null) $summary .= ' (Change: ' . number_format($change,2) . ')';
            } else {
                $parts = [];
                if (!empty($decoded['name'])) $parts[] = $decoded['name'];
                if (!empty($decoded['ref'])) $parts[] = $decoded['ref'];
                if (!empty($decoded['given'])) $parts[] = 'Given: ' . $decoded['given'];
                if (!empty($decoded['change'])) $parts[] = 'Change: ' . $decoded['change'];
                $summary = implode(' — ', $parts);
            }
        }
    }

    if (empty($summary) && !empty($note)) {
        if (preg_match('/Payment Details:\s*(\{.*\})/s', $note, $m)) {
            $jsonStr = trim($m[1]);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded)) {
                $pm = strtolower((string)$payment_method);
                if ($pm === 'gcash' || $pm === 'bank_transfer') {
                    $summary = strtoupper($pm === 'gcash' ? 'GCash' : 'Bank') . (!empty($decoded['name']) ? ' — ' . $decoded['name'] : '') . (!empty($decoded['ref']) ? ' (' . $decoded['ref'] . ')' : '');
                } elseif ($pm === 'cash') {
                    $given = $decoded['given'] ?? null;
                    $change = $decoded['change'] ?? null;
                    $summary = 'Cash';
                    if ($given !== null) $summary .= ' (Given: ' . number_format((float)$given,2) . ')';
                    if ($change !== null) $summary .= ' (Change: ' . number_format((float)$change,2) . ')';
                } else {
                    $parts = [];
                    if (!empty($decoded['name'])) $parts[] = $decoded['name'];
                    if (!empty($decoded['ref'])) $parts[] = $decoded['ref'];
                    $summary = implode(' — ', $parts);
                }
            } else {
                $summary = substr($jsonStr, 0, 60) . (strlen($jsonStr) > 60 ? '…' : '');
            }
        }
    }

    if (empty($summary) && !empty($note)) {
        if (preg_match('/\b(Table|Tables|Cabin)\b/i', $note)) {
            $firstLine = strtok($note, "\n");
            $summary = trim($firstLine);
        }
    }

    return $summary;
}

// ---------------------------
// Fetch orders (left panel)
// ---------------------------
$orders = [];

if ($conn) {
    // Removed LIMIT so all rows are fetched and the left-panel scrolling won't stop at 219.
    $sql = "SELECT id, created_at, total_amount, discount, note, payment_method, table_no, payment_details, cabin_name, cabin_price, subtotal, tax, discount_type, cash_given, change_amount, is_voided, status, voided_at, voided_by
            FROM sales_report
            ORDER BY created_at DESC";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            if (!isset($r['cabin_name'])) $r['cabin_name'] = '';
            if (!isset($r['payment_details'])) $r['payment_details'] = null;
            if (!isset($r['is_voided'])) $r['is_voided'] = 0;
            $orders[] = $r;
        }
        $res->free();
    } else {
        error_log("sales_report query failed: " . ($conn->error ?? 'no conn error'));
    }
} else {
    // Fallback placeholder rows when DB missing
    $orders = [
        ['id'=>101,'created_at'=>'2025-11-03 17:44:00','total_amount'=>1965.70,'discount'=>0,'note'=>'T-13/14','payment_method'=>null,'table_no'=>'Table 13','cabin_name'=>'','payment_details'=>null,'is_voided'=>0],
        ['id'=>102,'created_at'=>'2025-11-03 16:00:00','total_amount'=>1148.54,'discount'=>0,'note'=>'G-4','payment_method'=>null,'table_no'=>null,'cabin_name'=>'Cabin A','payment_details'=>null,'is_voided'=>0],
        ['id'=>103,'created_at'=>'2025-11-02 12:30:00','total_amount'=>6381.32,'discount'=>0,'note'=>'T-6/7','payment_method'=>null,'table_no'=>'Table 6','cabin_name'=>'','payment_details'=>null,'is_voided'=>0],
    ];
}

// ---------------------------
// Output HTML
// ---------------------------
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Club Hiraya — Sales Report</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="sales.css">
    <style>
        .sales-container { display:flex; gap:18px; padding:18px; box-sizing:border-box; }
        .left-panel { width: 320px; background: #e8e8ea; padding:14px; border-radius:12px; max-height: calc(100vh - 160px); overflow-y:auto; box-sizing:border-box; }
        .orders-list { display:flex; flex-direction:column; gap:12px; }
        .order-card { background:#fff; border-radius:10px; padding:10px 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.06); cursor:pointer; display:flex; justify-content:space-between; align-items:flex-start; border:1px solid #ddd; }
        .order-card .meta { display:flex; flex-direction:column; gap:6px; font-size:13px; max-width: 220px; }
        .order-card .meta .label { font-weight:700; white-space:normal; word-break:break-word; }
        .order-card .meta .sub { font-size:12px; color:#666; }
        .order-card .meta .cabin { font-size:11px; color:#999; margin-top:4px; }
        .order-card .amount { font-size:16px; font-weight:800; padding-left:8px; white-space:nowrap; }
        .order-card.active { border-color:#d33fd3; box-shadow:0 10px 18px rgba(0,0,0,0.12); transform:translateY(-2px); }
        .right-panel { flex:1; padding:18px; background:#e8e8ea; border-radius:12px; min-height:420px; box-sizing:border-box; }
        .right-card { background:#fff; border-radius:10px; padding:18px; border:1px solid #eee; min-height:260px; box-sizing:border-box; }
        .order-title { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .order-title .main { font-weight:800; font-size:20px;  }
        .order-title .meta { font-size:13px; color:#666; }
        .order-items { margin-top:12px; border-radius:8px; padding:12px; background:#faf9fb; border:1px solid #f0e8ef; }
        .order-item-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed #eee; color:#222; }
        .order-item-row:last-child { border-bottom: none; }
        .order-item-row .left { display:flex; gap:8px; align-items:center; }
        .order-item-row .name { font-weight:600; }
        .order-item-row .qty { color:#555; font-size:13px; }
        .order-item-row .price { font-weight:700; color:#111; min-width:120px; text-align:right; }
        .small-muted { color:#666; font-size:13px; }
        .topbar-buttons { display: flex; gap: 10px; align-items: center; margin-left: auto; }
        .topbar-buttons a { text-decoration: none; padding: 8px 16px; border-radius: 8px; background: #ddd; color: #222; font-weight: 600; transition: 0.2s; }
        .topbar-buttons a:hover { background: #ccc; }
        .topbar-buttons a.active { background: linear-gradient(135deg, #d33fd3, #a2058f); color: #fff; }
        .order-card.voided { opacity: 0.5; pointer-events: none; position: relative; }
        .order-card.voided::after { content: "VOIDED"; position: absolute; top: 6px; right: 10px; background: #dc2626; color: white; font-weight: bold; padding: 2px 8px; border-radius: 6px; font-size: 11px; box-shadow: 0 1px 4px rgba(255, 0, 0, 0.73); }
    </style>
</head>
<body <?php
    if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']) echo 'class="dark-mode"';
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

    <aside class="sidebar" role="complementary" aria-label="Sidebar">
        <div class="sidebar-header">
            <img src="../../clubtryara/assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
        </div>
        <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
            <a href="../admin_dashboard.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/home.png" alt="Home"></span><span>Home</span></a>
            <a href="../tables/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/cabin.png" alt="Tables icon"></span><span>Cabins</span></a>
            <a href="../inventory/inventory.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
            <a href="Sales_Report.php" class="sidebar-btn active"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/sales.png" alt="Sales report"></span><span>Sales Report</span></a>
            <a href="../settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/setting.png" alt="Settings"></span><span>Settings</span></a>
        </nav>
        <div style="flex:1" aria-hidden="true"></div>
        <form method="post" action="../logout.php" style="margin:0;">
            <button class="sidebar-logout" type="submit" aria-label="Logout">
            <span>Logout</span>
        </button>
        </form>
    </aside>

    <main class="main-content" role="main" aria-label="Main content" style="padding:22px;">
        <div class="topbar" style="left: 160px; right: 40px; display: flex; justify-content: space-between; align-items: center;">
            <div class="search-section">
                <input type="text" class="search-input" placeholder="Search order ID" id="searchBox" aria-label="Search orders">
            </div>
            <div class="topbar-buttons">
                <a href="Sales_Report.php" class="active">Sales Report</a>
                <a href="report_sales.php">Summary Report</a>
                <a href="void_logs.php" style="background:#dc2626; color:#fff;">Void Logs</a>
            </div>
        </div>

        <div style="height: 18px;"></div>

        <div class="sales-container" style="margin-left:24px; margin-right:24px;">
            <div class="left-panel" id="leftPanel" aria-label="Orders list">
                <div class="orders-list" id="ordersList">
                    <?php
                    foreach ($orders as $o) {
                        $id = intval($o['id'] ?? 0);
                        $created_raw = $o['created_at'] ?? null;
                        if ($created_raw) {
                            $created_ts = strtotime($created_raw);
                            $date_str = date('M j', $created_ts);
                            $time_str = date('g:i A', $created_ts);
                        } else {
                            $date_str = date('M j');
                            $time_str = date('g:i A');
                        }
                        $amount = fmt_currency($o['total_amount'] ?? 0);
                        $cabin = trim((string)($o['cabin_name'] ?? ''));
                        $tableNo = trim((string)($o['table_no'] ?? ''));
                        $payment_summary = parse_payment_summary_from_note_and_payment_details($o['note'] ?? '', $o['payment_method'] ?? '', $o['payment_details'] ?? null);
                        $isVoided = !empty($o['is_voided']) ? true : false;
                        $voidClass = $isVoided ? ' voided' : '';

                        echo '<div class="order-card' . $voidClass . '" data-id="' . e($id) . '">';
                        echo '<div class="meta">';
                        echo '<span class="label">Order ID: ' . e($id) . '</span>';
                        echo '<span class="sub">' . e($date_str) . ' — ' . e($time_str) . '</span>';
                        if ($cabin !== '') {
                            echo '<span class="cabin">(' . e($cabin) . ')</span>';
                        }
                        echo '</div>';
                        echo '<div class="amount">' . e($amount) . '</div>';
                        echo '</div>';
                    }

                    if (empty($orders)) {
                        echo '<div class="order-card"><div class="meta"><span class="label">No orders</span><span class="sub small-muted">No sales yet</span></div><div class="amount">—</div></div>';
                    }
                    ?>
                </div>
            </div>

            <div class="right-panel" id="rightPanel" aria-label="Order details">
                <div class="right-card" id="detailCard" role="region" aria-live="polite">
                    <div class="order-title">
                        <div class="main" id="detailTitle">Select an order</div>
                        <div class="meta" id="detailDate"></div>
                    </div>
                    <div class="grand" id="detailTotal" style="font-size:28px;font-weight:800;margin-top:8px;"></div>

                    <div class="order-items" id="orderItems">
                        <div class="order-item-empty">Click an order on the left to see details</div>
                    </div>

                    <div style="height:12px;"></div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; align-items:center;">
                        <div class="small-muted"></div><div id="detailDiscount">-</div>
                    </div>
                </div>
            </div>
        </div>

    </main>

<script>
(function(){
    const detailTitle = document.getElementById('detailTitle');
    const detailDate = document.getElementById('detailDate');
    const detailTotal = document.getElementById('detailTotal');
    const orderItems = document.getElementById('orderItems');
    const detailDiscount = document.getElementById('detailDiscount');

    function setActiveCard(card) {
        document.querySelectorAll('.order-card').forEach(c => c.classList.remove('active'));
        if (card) card.classList.add('active');
    }

    function formatCurrency(n) {
        const num = Number(n || 0);
        return '₱ ' + num.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    async function loadDetail(id, cardElement) {
        setActiveCard(cardElement);
        detailTitle.textContent = 'Loading...';
        detailDate.textContent = '';
        detailTotal.textContent = '';
        orderItems.innerHTML = '<div class="order-item-empty">Loading items...</div>';
        detailDiscount.textContent = '';

        try {
            const res = await fetch('sales_order_detail.php?id=' + encodeURIComponent(id));
            if (!res.ok) {
                orderItems.innerHTML = '<div class="order-item-empty">Failed to load order details (server error).</div>';
                detailTitle.textContent = 'Error';
                return;
            }
            const text = await res.text();
            orderItems.innerHTML = text;
            detailTitle.textContent = 'Order #' + id;

            try {
                const tmp = document.createElement('div');
                tmp.innerHTML = text;
                const strongs = tmp.querySelectorAll('strong');
                for (let s of strongs) {
                    const txt = (s.textContent || '').trim().toLowerCase();
                    if (txt.indexOf('total payable') !== -1 || txt.indexOf('total') !== -1) {
                        let parent = s.parentElement;
                        if (parent) {
                            const amountText = parent.textContent.replace(/[\s\xa0]+/g, ' ').match(/₱\s*[\d,]+\.\d{2}/);
                            if (amountText) {
                                detailTotal.textContent = amountText[0];
                                break;
                            }
                        }
                    }
                }
            } catch (err) {
                console.warn('Detail total parse failed', err);
            }

            const voidBtn = orderItems.querySelector('#voidBtn');
            if (voidBtn) {
                voidBtn.addEventListener('click', async function () {
                    const orderId = this.getAttribute('data-id');
                    if (!confirm('Are you sure you want to VOID Order #' + orderId + '?')) return;

                    try {
                        const res = await fetch('void_sale.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'id=' + encodeURIComponent(orderId)
                        });
                        const data = await res.json();
                        if (data.ok) {
                            alert(data.message || 'Order voided successfully.');
                            location.reload();
                        } else {
                            alert('Failed to void: ' + (data.error || 'Unknown error.'));
                        }
                    } catch (err) {
                        alert('Error connecting to server.');
                        console.error(err);
                    }
                });
            }

        } catch (e) {
            detailTitle.textContent = 'Error loading order';
            orderItems.innerHTML = '<div class="order-item-empty">Failed to retrieve order details.</div>';
            console.error(e);
        }
    }

    document.querySelectorAll('.order-card').forEach(card => {
        card.addEventListener('click', function(){
            const id = this.getAttribute('data-id');
            if (!id) return;
            loadDetail(id, this);
        });
    });

    const firstCard = document.querySelector('.order-card');
    if (firstCard) firstCard.click();

    const searchBox = document.getElementById('searchBox');
    if (searchBox) {
      searchBox.addEventListener('input', function(){
        const q = this.value.trim();
        // Only match Order ID. Extract digits from input.
        const digits = q.replace(/\D/g, '');
        document.querySelectorAll('.order-card').forEach(card => {
          const id = (card.getAttribute('data-id') || '');
          // If input is empty -> show all
          if (!q) {
            card.style.display = '';
            return;
          }
          // If user typed digits, filter by id substring
          if (digits.length > 0) {
            if (id.indexOf(digits) !== -1) {
              card.style.display = '';
            } else {
              card.style.display = 'none';
            }
          } else {
            // Non-digit input: show all (you can change to hide-all if preferred)
            card.style.display = '';
          }
        });
      });
    }

})();
</script>
</body>
</html>
