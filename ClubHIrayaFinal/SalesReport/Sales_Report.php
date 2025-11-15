<?php
session_start();

/**
 * Sales_Report.php
 *
 * Purpose:
 * - Render sales list on the left as cards.
 * - When clicking a left card, fetch the detail HTML from sales_order_detail.php and inject into the right panel.
 * - Uses column names from your supplied SQL (sales_report table).
 *
 * Important:
 * - Design/structure left intact from your original file.
 * - Only changed how data is read / displayed from the SQL and added optional cabin small-text per "Option C".
 * - Keeps compatibility with sales_order_detail.php (you uploaded it separately).
 *
 * Notes:
 * - This file intentionally verbose / commented to match your request for a large file and to make the logic explicit.
 * - Uses the exact columns you provided: payment_details, cabin_name, cabin_price, subtotal, tax, discount_type, cash_given, change_amount, is_voided, etc.
 */

// small helper to send JSON if needed (kept for parity with earlier versions)
function send_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// database connection include
$connectPath = __DIR__ . '/../php/db_connect.php';
if (file_exists($connectPath)) {
    require_once $connectPath;
} else {
    // keep $conn defined to avoid undefined variable notices later in the script
    $conn = null;
}

// ---------------------------
// Helper functions
// ---------------------------

/**
 * Safe HTML escape helper.
 */
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format a decimal/float value as Philippine Peso with 2 decimals.
 */
function fmt_currency($n) {
    // ensure numeric
    if ($n === null || $n === '') $n = 0;
    return '₱ ' . number_format((float)$n, 2);
}

/**
 * Parse payment_details (JSON or plain) into a readable short summary.
 *
 * Input examples found in your SQL dump:
 * - '\nPayment Details: {"given":2000,"change":227.83999999999992}'
 * - '\nPayment Details: {"name":"Carlora Manaloto","ref":"0920409857"}'
 *
 * This function returns a short one-line summary suitable for the left card subtext (if needed).
 */
function parse_payment_summary_from_note_and_payment_details($note, $payment_method, $payment_details) {
    $summary = '';

    // If payment_details column contains JSON already (or stringified JSON), use it.
    if (!empty($payment_details)) {
        // sometimes payment_details may be raw JSON string or null
        $decoded = null;
        if (is_string($payment_details)) {
            $trim = trim($payment_details);
            // defend against prepended "Payment Details: {..}" in the note instead of this column
            if (strlen($trim) && $trim[0] === '{') {
                $decoded = json_decode($trim, true);
            } else {
                // maybe empty or not JSON
                $decoded = json_decode($trim, true);
            }
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
                // generic assembly
                $parts = [];
                if (!empty($decoded['name'])) $parts[] = $decoded['name'];
                if (!empty($decoded['ref'])) $parts[] = $decoded['ref'];
                if (!empty($decoded['given'])) $parts[] = 'Given: ' . $decoded['given'];
                if (!empty($decoded['change'])) $parts[] = 'Change: ' . $decoded['change'];
                $summary = implode(' — ', $parts);
            }
        }
    }

    // If we didn't get a summary from payment_details, try to parse it from note
    if (empty($summary) && !empty($note)) {
        // Look for embedded JSON in note (some rows had '\nPayment Details: {...}')
        if (preg_match('/Payment Details:\s*(\{.*\})/s', $note, $m)) {
            $jsonStr = trim($m[1]);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded)) {
                $pm = strtolower((string)$payment_method);
                if ($pm === 'gcash' || $pm === 'bank_transfer') {
                    $summary = strtoupper($pm === 'gcash' ? 'GCash' : 'Bank') . ($decoded['name'] ? ' — ' . ($decoded['name']) : '') . (!empty($decoded['ref']) ? ' (' . $decoded['ref'] . ')' : '');
                } elseif ($pm === 'cash') {
                    $given = $decoded['given'] ?? null;
                    $change = $decoded['change'] ?? null;
                    $summary = 'Cash';
                    if ($given !== null) $summary .= ' (Given: ' . number_format((float)$given,2) . ')';
                    if ($change !== null) $summary .= ' (Change: ' . number_format((float)$change,2) . ')';
                } else {
                    // fallback
                    $parts = [];
                    if (!empty($decoded['name'])) $parts[] = $decoded['name'];
                    if (!empty($decoded['ref'])) $parts[] = $decoded['ref'];
                    $summary = implode(' — ', $parts);
                }
            } else {
                // Not JSON — short substring fallback
                $summary = substr($jsonStr, 0, 60) . (strlen($jsonStr) > 60 ? '…' : '');
            }
        }
    }

    // Final fallback: if the note looks like "Table 2" use it
    if (empty($summary) && !empty($note)) {
        if (preg_match('/\b(Table|Tables|Cabin)\b/i', $note)) {
            $firstLine = strtok($note, "\n");
            $summary = trim($firstLine);
        }
    }

    return $summary;
}

/**
 * Build the label for the left card. This function tries this order:
 *  - If cabin_name exists -> blank (we're using small text for cabin per Option C)
 *  - Otherwise try first line of note (if meaningful)
 *  - Else fallback to 'Order {id}'
 *
 * We intentionally keep the visible "Order ID: {id}" on top (as required)
 * and use cabin as small text at bottom if present (Option C).
 */
function build_left_card_label($row) {
    // The caller wants "Order ID: {id}" always on top, so label is simple.
    // This function is kept for future extension.
    return 'Order ' . intval($row['id']);
}

// ---------------------------
// Fetch orders (left panel)
// ---------------------------

$orders = [];

if ($conn) {
    // Select important columns according to your provided schema.
    // We fetch cabin_name, payment_details, cash_given, change_amount, subtotal, tax, discount_type, is_voided, status, voided_at, voided_by
    $sql = "SELECT id, created_at, total_amount, discount, note, payment_method, table_no, payment_details, cabin_name, cabin_price, subtotal, tax, discount_type, cash_given, change_amount, is_voided, status, voided_at, voided_by
            FROM sales_report
            ORDER BY created_at DESC
            LIMIT 200";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            // normalize certain fields so template usage is simpler
            if (!isset($r['cabin_name'])) $r['cabin_name'] = '';
            if (!isset($r['payment_details'])) $r['payment_details'] = null;
            if (!isset($r['is_voided'])) $r['is_voided'] = 0;
            $orders[] = $r;
        }
        $res->free();
    } else {
        // Query failed — keep empty list but log to error log for debugging
        error_log("sales_report query failed: " . ($conn->error ?? 'no conn error'));
    }
} else {
    // When DB not present, provide a small set of placeholders (kept minimal)
    $orders = [
        ['id'=>101,'created_at'=>'2025-11-03 17:44:00','total_amount'=>1965.70,'discount'=>0,'note'=>'T-13/14','payment_method'=>null,'table_no'=>'Table 13','cabin_name'=>'','payment_details'=>null,'is_voided'=>0],
        ['id'=>102,'created_at'=>'2025-11-03 16:00:00','total_amount'=>1148.54,'discount'=>0,'note'=>'G-4','payment_method'=>null,'table_no'=>null,'cabin_name'=>'Cabin A','payment_details'=>null,'is_voided'=>0],
        ['id'=>103,'created_at'=>'2025-11-02 12:30:00','total_amount'=>6381.32,'discount'=>0,'note'=>'T-6/7','payment_method'=>null,'table_no'=>'Table 6','cabin_name'=>'','payment_details'=>null,'is_voided'=>0],
    ];
}

// ---------------------------
// Output HTML (page)
// ---------------------------
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Club Hiraya — Sales Report</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="sales.css">
    <style>
        /* Keep your original styles intact — copied here to ensure left-cards look right even if sales.css isn't loaded */
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

    <!-- Sidebar (kept similar to your layout) -->
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
                    // Render left cards using the values from $orders
                    foreach ($orders as $o) {
                        // ensure id exists and is integer
                        $id = intval($o['id'] ?? 0);
                        $created_raw = $o['created_at'] ?? null;
                        // format date like "Feb 14 — 5:33 PM"
                        if ($created_raw) {
                            $created_ts = strtotime($created_raw);
                            // month short name + day (no leading zero)
                            $date_str = date('M j', $created_ts);
                            // 12-hour without leading zero for hour (g), with minutes and AM/PM
                            $time_str = date('g:i A', $created_ts);
                        } else {
                            $date_str = date('M j');
                            $time_str = date('g:i A');
                        }

                        // currency
                        $amount = fmt_currency($o['total_amount'] ?? 0);

                        // cabin_name (we will show small text only if present, per Option C)
                        $cabin = trim((string)($o['cabin_name'] ?? ''));
                        // if cabin_name empty, check table_no for fallback (but we will not show it as cabin)
                        $tableNo = trim((string)($o['table_no'] ?? ''));

                        // build payment short summary if needed (not printed by default, but kept to allow future usage)
                        $payment_summary = parse_payment_summary_from_note_and_payment_details($o['note'] ?? '', $o['payment_method'] ?? '', $o['payment_details'] ?? null);

                        // is voided?
                        $isVoided = !empty($o['is_voided']) ? true : false;
                        $voidClass = $isVoided ? ' voided' : '';

                        // Print the card; must match the exact textual order required:
                        // Order ID: 32
                        // Feb 14 — 5:33 PM
                        // ₱ 1,450.00
                        // (cabin A)  <-- only if cabin exists (small muted text at bottom)
                        echo '<div class="order-card' . $voidClass . '" data-id="' . e($id) . '">';

                        echo '<div class="meta">';
                        // Top line: Order ID: 32
                        echo '<span class="label">Order ID: ' . e($id) . '</span>';
                        // Middle line: date — time
                        echo '<span class="sub">' . e($date_str) . ' — ' . e($time_str) . '</span>';
                        // optionally show payment summary as another sub (kept commented out if not needed)
                        // if ($payment_summary) echo '<span class="sub">' . e($payment_summary) . '</span>';

                        // Option C: show small cabin text at bottom (in parentheses) if cabin_name is set
                        if ($cabin !== '') {
                            echo '<span class="cabin">(' . e($cabin) . ')</span>';
                        }

                        echo '</div>'; // end meta

                        // right side amount
                        echo '<div class="amount">' . e($amount) . '</div>';

                        echo '</div>'; // end order-card
                    }

                    // If empty dataset, show a friendly placeholder card
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
                        <div class="small-muted">Discount:</div><div id="detailDiscount">-</div>
                    </div>
                </div>
            </div>
        </div>

    </main>

<script>
/*
 * Client-side behaviour:
 * - Clicking a left card will fetch details from sales_order_detail.php?id={id}
 * - The returned HTML (already prepared by your sales_order_detail.php) will be injected into #orderItems
 * - The script keeps an 'active' class on the currently selected card
 *
 * Note: sales_order_detail.php must expect ?id=NN and return fragment HTML (you uploaded that file earlier).
 */

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
        // simple frontend formatting fallback
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
            // Fetch detail fragment from server (sales_order_detail.php returns HTML)
            const res = await fetch('sales_order_detail.php?id=' + encodeURIComponent(id));
            if (!res.ok) {
                orderItems.innerHTML = '<div class="order-item-empty">Failed to load order details (server error).</div>';
                detailTitle.textContent = 'Error';
                return;
            }
            const text = await res.text();

            // inject returned HTML directly
            orderItems.innerHTML = text;

            // set top title
            detailTitle.textContent = 'Order #' + id;

            // attempt to extract a displayed total in the returned HTML (sales_order_detail.php prints Total Payable)
            // We'll try to find an element with text like "Total Payable" and the next sibling numeric text.
            // If not found, leave empty.
            try {
                // create a temporary container to query the returned HTML
                const tmp = document.createElement('div');
                tmp.innerHTML = text;

                // Check for known patterns: a row with "Total Payable" or <strong>Total Payable</strong>
                const strongs = tmp.querySelectorAll('strong');
                for (let s of strongs) {
                    const txt = (s.textContent || '').trim().toLowerCase();
                    if (txt.indexOf('total payable') !== -1 || txt.indexOf('total') !== -1) {
                        // next sibling or parent row contains amount
                        // try DOM traversal
                        let parent = s.parentElement;
                        if (parent) {
                            // find numeric substring in parent's text
                            const amountText = parent.textContent.replace(/[\s\xa0]+/g, ' ').match(/₱\s*[\d,]+\.\d{2}/);
                            if (amountText) {
                                detailTotal.textContent = amountText[0];
                                break;
                            }
                        }
                    }
                }
            } catch (err) {
                // ignore parsing errors — detailTotal stays empty
                console.warn('Detail total parse failed', err);
            }

            // Rebind Void button if present inside injected HTML
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
                            // reload page to reflect voided state
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

    // attach click handlers
    document.querySelectorAll('.order-card').forEach(card => {
        card.addEventListener('click', function(){
            const id = this.getAttribute('data-id');
            if (!id) return;
            loadDetail(id, this);
        });
    });

    // auto-click first card for convenience
    const firstCard = document.querySelector('.order-card');
    if (firstCard) firstCard.click();

    // search filter
    const searchBox = document.getElementById('searchBox');
    if (searchBox) {
      searchBox.addEventListener('input', function(){
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.order-card').forEach(card => {
          const metaLabelEl = card.querySelector('.meta .label');
          const metaSubEl = card.querySelector('.meta .sub');
          const cabinEl = card.querySelector('.meta .cabin');
          const meta = (metaLabelEl && metaLabelEl.textContent) || '';
          const sub = (metaSubEl && metaSubEl.textContent) || '';
          const cabin = (cabinEl && cabinEl.textContent) || '';
          const amt = (card.querySelector('.amount') && card.querySelector('.amount').textContent) || '';
          const haystack = (meta + ' ' + sub + ' ' + cabin + ' ' + amt).toLowerCase();
          if (!q || haystack.includes(q)) {
            card.style.display = '';
          } else {
            card.style.display = 'none';
          }
        });
      });
    }

})();
</script>
</body>
</html>
