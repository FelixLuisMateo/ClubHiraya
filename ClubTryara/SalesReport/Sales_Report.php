<?php
session_start();

/**
 * Sales_Report.php
 * - Lists sales on left (scrollable cards)
 * - Displays selected order details on the right
 * - Supports AJAX detail fetch via ?action=detail&id=...
 *
 * Notes:
 * - This tries to use a `sales_report` table (id, created_at, total_amount, discount, note).
 *   If your project uses a different table or schema, adjust the SQL to match.
 * - AJAX detail endpoint will also try to fetch `sales_items` / `order_items` (optional)
 *   to show line items for the selected order. If these tables are not present it will
 *   fall back to the `note` column or an empty items list.
 */

// quick helper to send JSON
function send_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// include DB connection (adjust path if your db_connect.php is elsewhere)
$connectPath = __DIR__ . '/../php/db_connect.php';
if (file_exists($connectPath)) {
    require_once $connectPath;
} else {
    // If no DB connection file, set $conn = null and we'll use sample data below
    $conn = null;
}

// AJAX: return details for a specific order
if (isset($_GET['action']) && $_GET['action'] === 'detail' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $response = [
        'ok' => false,
        'order' => null,
        'items' => []
    ];

    if ($conn) {
        // try to fetch the main order row
        $stmt = $conn->prepare("SELECT id, created_at, total_amount, discount, note FROM sales_report WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $response['ok'] = true;
                    $response['order'] = $row;

                    // try to fetch associated items from common names
                    $items = [];
                    $possibleTables = ['sales_items', 'order_items', 'sales_order_items', 'order_line_items'];
                    foreach ($possibleTables as $t) {
                        $q = "SELECT * FROM `$t` WHERE sales_id = ? OR order_id = ? LIMIT 100";
                        $s2 = $conn->prepare($q);
                        if ($s2) {
                            $s2->bind_param('ii', $id, $id);
                            if ($s2->execute()) {
                                $r2 = $s2->get_result();
                                while ($it = $r2->fetch_assoc()) {
                                    $items[] = $it;
                                }
                            }
                            $s2->close();
                        }
                        if (!empty($items)) break;
                    }

                    $response['items'] = $items;
                }
                $res->free();
            }
            $stmt->close();
        }
    } else {
        // no DB -> return sample data for demo
        $response['ok'] = true;
        $response['order'] = [
            'id' => $id,
            'created_at' => date('Y-m-d H:i:s'),
            'total_amount' => 1965.70,
            'discount' => 0,
            'note' => 'Demo order note'
        ];
        $response['items'] = [
            ['name' => 'Lechon Baka', 'price' => 599, 'qty' => 1],
            ['name' => 'Crispy Pata', 'price' => 899, 'qty' => 1],
            ['name' => 'Sisig', 'price' => 289, 'qty' => 1]
        ];
    }

    send_json($response);
}

// ---------- Render HTML page ----------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Club Hiraya — Sales Report</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="sales.css">
    <style>
        /* Page specific tweaks to match the provided designs */
        .sales-container { display: flex; gap: 18px; padding: 18px; box-sizing: border-box; }
        .left-panel { width: 280px; background: #e8e8ea; padding: 14px; border-radius: 14px; box-shadow: inset 0 0 0 2px rgba(0,0,0,0.08); max-height: calc(100vh - 160px); overflow-y: auto; }
        .right-panel { flex: 1; padding: 18px; background: #e8e8ea; border-radius: 14px; min-height: 420px; box-sizing: border-box; position: relative; }
        .orders-list { display:flex; flex-direction:column; gap:12px; }
        .order-card { background: #fff; border-radius: 12px; padding: 12px 14px; box-shadow: 0 6px 12px rgba(0,0,0,0.08); cursor: pointer; display:flex; justify-content:space-between; align-items:center; border: 2px solid #bdbdbd; }
        .order-card .meta { display:flex; flex-direction:column; gap:4px; font-size:13px; color:#222; }
        .order-card .meta .label { font-weight:700; color:#333; }
        .order-card .amount { font-size:18px; font-weight:700; color:#111; background:transparent; padding:6px 12px; border-radius:10px; }
        .order-card.active { border-color: #d33fd3; box-shadow: 0 10px 18px rgba(0,0,0,0.12); transform: translateY(-2px); }
        .right-card { background:#fff; border-radius:12px; padding:18px; border:3px solid #eee; min-height:260px; box-sizing:border-box; }
        .right-card .order-title { font-weight:800; font-size:22px; color:#222; display:flex; justify-content:space-between; align-items:center; }
        .right-card .grand { font-size:32px; font-weight:800; color:#111; margin:8px 0 12px 0; }
        .order-items { border:2px solid #f0e8ef; border-radius:12px; padding:14px; background:#fff; min-height:160px; box-sizing:border-box; }
        .order-item-row { display:flex; justify-content:space-between; padding:6px 0; font-size:16px; color:#222; }
        .order-item-row .name { color:#222; }
        .order-item-empty { color:#666; text-align:center; padding:26px 0; }
        /* small scrollbar visual for panels */
        .left-panel::-webkit-scrollbar, .right-panel::-webkit-scrollbar { width:10px; height:10px; }
        .left-panel::-webkit-scrollbar-thumb, .right-panel::-webkit-scrollbar-thumb { background:#cfcfcf; border-radius:8px; }
        /* top filter row like the screenshot (tabs) */
        .report-tabs { display:flex; gap:12px; margin:12px 0 18px 0; align-items:center; }
        .tab { padding:8px 18px; border-radius:18px; background:#fff; border:3px solid #ddd; font-weight:700; cursor:pointer; }
        .tab.active { background:#d33fd3; color:#fff; border-color:#000; box-shadow: 0 3px 8px rgba(0,0,0,0.12); }
        /* small date label */
        .small-muted { color:#666; font-size:13px; }
        /* responsive */
        @media (max-width: 980px) {
            .sales-container { flex-direction:column; }
            .left-panel { width:100%; max-height:250px; display:flex; overflow-x:auto; flex-direction:row; padding:10px; }
            .orders-list { flex-direction:row; gap:10px; }
            .order-card { min-width:180px; flex:0 0 auto; }
        }
    </style>
</head>
<body <?php
    // body classes and accent style (reuse user's approach)
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

    <!-- Sidebar (kept similar to your layout) -->
    <aside class="sidebar" role="complementary" aria-label="Sidebar">
        <div class="sidebar-header">
            <img src="../../clubtryara/assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
        </div>
        <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
            <a href="../index.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/home.png" alt="Home"></span><span>Home</span>
            </a>
            <a href="../../ClubTryara/tables/tables.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/table.png" alt="Tables"></span><span>Tables</span>
            </a>
            <a href="../inventory/inventory.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/inventory.png" alt="Inventory"></span><span>Inventory</span>
            </a>
            <a href="Sales_Report.php" class="sidebar-btn active">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/sales.png" alt="Sales report"></span><span>Sales Report</span>
            </a>
            <a href="../settings/settings.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../../clubtryara/assets/logos/setting.png" alt="Settings"></span><span>Settings</span>
            </a>
        </nav>
        <div style="flex:1" aria-hidden="true"></div>
        <button class="sidebar-logout" type="button" aria-label="Logout"><span>Logout</span></button>
    </aside>

    <main class="main-content" role="main" aria-label="Main content" style="padding:22px;">
        <div class="topbar" style="left: 160px; right: 40px; position:relative;">
            <div class="search-section">
                <input type="text" class="search-input" placeholder="Search orders" id="searchBox" aria-label="Search orders">
            </div>
        </div>

        <div style="height: 18px;"></div>

        <!-- tabs -->
        <div class="report-tabs" style="margin-left:24px;">
            <div class="tab">Report</div>
            <div class="tab active">Sales</div>
        </div>

        <div class="sales-container" style="margin-left:24px; margin-right:24px;">
            <!-- LEFT: order list -->
            <div class="left-panel" id="leftPanel" aria-label="Orders list">
                <div class="orders-list" id="ordersList">
                    <?php
                    // Fetch orders from DB (descending)
                    $orders = [];
                    if ($conn) {
                        $sql = "SELECT id, created_at, total_amount, discount, note FROM sales_report ORDER BY created_at DESC LIMIT 200";
                        if ($res = $conn->query($sql)) {
                            while ($r = $res->fetch_assoc()) {
                                $orders[] = $r;
                            }
                            $res->free();
                        }
                    } else {
                        // sample demo orders
                        $orders = [
                            ['id'=>101,'created_at'=>'2025-11-03 17:44:00','total_amount'=>1965.70,'discount'=>0,'note'=>'T-13/14'],
                            ['id'=>102,'created_at'=>'2025-11-03 16:00:00','total_amount'=>1148.54,'discount'=>0,'note'=>'G-4'],
                            ['id'=>103,'created_at'=>'2025-11-02 12:30:00','total_amount'=>6381.32,'discount'=>0,'note'=>'T-6/7'],
                            ['id'=>104,'created_at'=>'2025-11-01 10:00:00','total_amount'=>3644.58,'discount'=>0,'note'=>'T-8'],
                        ];
                    }

                    // render list
                    $firstId = null;
                    foreach ($orders as $idx => $o) {
                        $id = htmlspecialchars($o['id']);
                        $date = date('M d, Y', strtotime($o['created_at'] ?? date('Y-m-d')));
                        $label = htmlspecialchars($o['note'] ?? ('Order '.$id));
                        $amount = number_format(floatval($o['total_amount'] ?? 0), 2);
                        if ($idx === 0) $firstId = $id;
                        echo '<div class="order-card" data-id="'.$id.'">';
                        echo '<div class="meta"><span class="label">'.$label.'</span><span class="small-muted">'.$date.'</span></div>';
                        echo '<div class="amount">₱ '.$amount.'</div>';
                        echo '</div>';
                    }

                    if (empty($orders)) {
                        echo '<div class="order-card"><div class="meta"><span class="label">No orders</span><span class="small-muted">No sales yet</span></div><div class="amount">—</div></div>';
                    }
                    ?>
                </div>
            </div>

            <!-- RIGHT: detail view -->
            <div class="right-panel" id="rightPanel" aria-label="Order details">
                <div class="right-card" id="detailCard" role="region" aria-live="polite">
                    <div class="order-title">
                        <div id="detailTitle">Select an order</div>
                        <div class="small-muted" id="detailDate"></div>
                    </div>
                    <div class="grand" id="detailTotal"></div>

                    <div class="order-items" id="orderItems">
                        <div class="order-item-empty">Click an order on the left to see details</div>
                    </div>

                    <div style="height:12px;"></div>
                    <div style="display:flex; justify-content:flex-end; gap:8px; align-items:center;">
                        <div class="small-muted">Discount:</div><div id="detailDiscount">-</div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        // Basic client interactions: clicking order cards fetches details and updates UI.
        (function(){
            const list = document.getElementById('ordersList');
            const detailTitle = document.getElementById('detailTitle');
            const detailDate = document.getElementById('detailDate');
            const detailTotal = document.getElementById('detailTotal');
            const orderItems = document.getElementById('orderItems');
            const detailDiscount = document.getElementById('detailDiscount');

            function setActiveCard(card) {
                document.querySelectorAll('.order-card').forEach(c => c.classList.remove('active'));
                if (card) card.classList.add('active');
            }

            async function loadDetail(id, cardElement) {
                setActiveCard(cardElement);
                detailTitle.textContent = 'Loading...';
                detailDate.textContent = '';
                detailTotal.textContent = '';
                orderItems.innerHTML = '<div class="order-item-empty">Loading items...</div>';
                detailDiscount.textContent = '';

                try {
                    const res = await fetch(window.location.pathname + '?action=detail&id=' + encodeURIComponent(id));
                    const payload = await res.json();
                    if (!payload.ok) {
                        detailTitle.textContent = 'Order not found';
                        orderItems.innerHTML = '<div class="order-item-empty">No details available for this order.</div>';
                        return;
                    }
                    const o = payload.order;
                    detailTitle.textContent = (o.note ? o.note : ('Order ' + o.id));
                    detailDate.textContent = new Date(o.created_at).toLocaleString();
                    detailTotal.textContent = '₱ ' + (Number(o.total_amount).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}));
                    detailDiscount.textContent = (o.discount ? '₱ ' + Number(o.discount).toFixed(2) : '₱ 0.00');

                    // Render items
                    const items = payload.items || [];
                    if (items.length === 0) {
                        // fallback: show note if has text lines separated by newline
                        if (o.note && o.note.indexOf('\n') !== -1) {
                            const lines = o.note.split('\n');
                            orderItems.innerHTML = '';
                            lines.forEach(line => {
                                const row = document.createElement('div');
                                row.className = 'order-item-row';
                                row.innerHTML = '<div class="name">'+line+'</div><div class="price"></div>';
                                orderItems.appendChild(row);
                            });
                        } else {
                            orderItems.innerHTML = '<div class="order-item-empty">No line items recorded for this order.</div>';
                        }
                    } else {
                        orderItems.innerHTML = '';
                        items.forEach(it => {
                            // adapt to various column names
                            const name = it.name || it.product_name || it.item_name || it.title || ('Item #' + (it.id || ''));
                            const qty = (it.qty || it.quantity || it.q || 1);
                            const price = (it.price || it.unit_price || it.amount || it.total || 0);
                            const row = document.createElement('div');
                            row.className = 'order-item-row';
                            const left = document.createElement('div');
                            left.className = 'name';
                            left.textContent = name + (qty && qty != 1 ? ' x' + qty : '');
                            const right = document.createElement('div');
                            right.className = 'price';
                            right.textContent = '₱ ' + Number(price).toLocaleString(undefined, {minimumFractionDigits:2});
                            row.appendChild(left);
                            row.appendChild(right);
                            orderItems.appendChild(row);
                        });
                    }
                } catch (e) {
                    detailTitle.textContent = 'Error loading order';
                    orderItems.innerHTML = '<div class="order-item-empty">Failed to retrieve order details.</div>';
                    console.error(e);
                }
            }

            // attach click listeners to cards
            document.querySelectorAll('.order-card').forEach(card => {
                card.addEventListener('click', function(){
                    const id = this.getAttribute('data-id');
                    if (!id) return;
                    loadDetail(id, this);
                });
            });

            // optionally load first order
            const firstCard = document.querySelector('.order-card');
            if (firstCard) {
                firstCard.click();
            }

            // simple search box filter
            const searchBox = document.getElementById('searchBox');
            searchBox.addEventListener('input', function(){
                const q = this.value.trim().toLowerCase();
                document.querySelectorAll('.order-card').forEach(card => {
                    const meta = card.querySelector('.meta .label').textContent.toLowerCase();
                    const amt = card.querySelector('.amount').textContent.toLowerCase();
                    if (!q || meta.includes(q) || amt.includes(q)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        })();
    </script>
</body>
</html>