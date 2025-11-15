<?php
session_start();

/**
 * Sales_Report.php
 * Lists sales and displays details. Includes AJAX detail endpoint.
 *
 * Improvements in this version:
 * - Stronger left-column card styling so items render as cards (matching screenshot layout).
 * - Left cards display a usable label derived from: table_no (if present) -> parsed note -> payment summary -> fallback "Order #".
 * - Shows a short payment summary on the left card (GCash — name (ref), Cash (Given/Change), etc).
 * - Detail panel shows reservation/table information and displays items with qty, unit price and line totals.
 * - AJAX detail endpoint remains robust and avoids querying tables/columns that do not exist.
 */

// quick helper to send JSON
function send_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$connectPath = __DIR__ . '/../php/db_connect.php';
if (file_exists($connectPath)) {
    require_once $connectPath;
} else {
    $conn = null;
}

// Parse note field and extract label/payment summary
function parse_note_and_payment($note, $payment_method = null) {
    $result = ['label' => '', 'payment_summary' => ''];

    if ($note === null) $note = '';
    $note = trim($note);

    // Use table-like notes first line as label (unless it's the payment details line)
    if (strlen($note) > 0) {
        $lines = preg_split("/\r\n|\n|\r/", $note);
        if (isset($lines[0]) && stripos($lines[0], 'Payment Details:') === false) {
            $result['label'] = trim($lines[0]);
        }
    }

    // Try to find "Payment Details: { ... }" JSON blob inside note
    if (preg_match('/Payment Details:\s*(\{.*\})/s', $note, $m)) {
        $jsonStr = trim($m[1]);
        $decoded = json_decode($jsonStr, true);
        if (is_array($decoded)) {
            $pm = $payment_method ? strtolower($payment_method) : '';
            if ($pm === 'gcash') {
                $name = $decoded['name'] ?? ($decoded['payer'] ?? '');
                $ref = $decoded['ref'] ?? ($decoded['reference'] ?? '');
                $result['payment_summary'] = 'GCash';
                if ($name) $result['payment_summary'] .= ' — ' . $name;
                if ($ref) $result['payment_summary'] .= ' (' . $ref . ')';
            } elseif ($pm === 'bank_transfer') {
                $name = $decoded['name'] ?? ($decoded['payer'] ?? '');
                $ref = $decoded['ref'] ?? ($decoded['reference'] ?? '');
                $result['payment_summary'] = 'Bank Transfer';
                if ($name) $result['payment_summary'] .= ' — ' . $name;
                if ($ref) $result['payment_summary'] .= ' (' . $ref . ')';
            } elseif ($pm === 'cash') {
                $given = array_key_exists('given', $decoded) ? (float)$decoded['given'] : null;
                $change = array_key_exists('change', $decoded) ? (float)$decoded['change'] : null;
                $result['payment_summary'] = 'Cash';
                if ($given !== null) $result['payment_summary'] .= ' (Given: ₱' . number_format($given,2) . ')';
                if ($change !== null) $result['payment_summary'] .= ' (Change: ₱' . number_format($change,2) . ')';
            } else {
                // Generic summary
                $parts = [];
                if (!empty($decoded['name'])) $parts[] = $decoded['name'];
                if (!empty($decoded['ref'])) $parts[] = $decoded['ref'];
                if (!empty($decoded['given'])) $parts[] = 'Given: ' . $decoded['given'];
                if (!empty($decoded['change'])) $parts[] = 'Change: ' . $decoded['change'];
                $result['payment_summary'] = implode(' — ', $parts);
            }
        } else {
            $result['payment_summary'] = substr($jsonStr, 0, 80) . (strlen($jsonStr) > 80 ? '...' : '');
        }
    } else {
        // fallback: if the note contains "Table ..." or "Table X" it is likely a reservation label
        if (empty($result['label']) && preg_match('/\b(Tables?|Table|Cabin)\b/i', $note)) {
            $firstLine = strtok($note, "\n");
            if ($firstLine) $result['label'] = trim($firstLine);
        }
    }

    // if we don't have a label but have a payment summary, promote it to label
    if (empty($result['label']) && !empty($result['payment_summary'])) {
        $result['label'] = $result['payment_summary'];
        $result['payment_summary'] = '';
    }

    return $result;
}

// ---------- AJAX detail endpoint ----------
if (isset($_GET['action']) && $_GET['action'] === 'detail' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $response = ['ok' => false, 'order' => null, 'items' => []];

    if ($conn) {
        $stmt = $conn->prepare("SELECT id, created_at, total_amount, discount, note, table_no, payment_method, service_charge FROM sales_report WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $response['ok'] = true;
                    $response['order'] = $row;

                    // fetch items from sales_items (preferred) then fall back to other tables
                    $items = [];
                    $checkedTables = ['sales_items', 'order_items', 'sales_order_items', 'order_line_items'];
                    foreach ($checkedTables as $t) {
                        // check columns
                        $hasSalesId = false; $hasOrderId = false;
                        try {
                            $colRes = $conn->query("SHOW COLUMNS FROM `{$conn->real_escape_string($t)}` LIKE 'sales_id'");
                            if ($colRes && $colRes->num_rows) $hasSalesId = true;
                            if ($colRes) $colRes->free();
                        } catch (Exception $e) {}
                        try {
                            $colRes2 = $conn->query("SHOW COLUMNS FROM `{$conn->real_escape_string($t)}` LIKE 'order_id'");
                            if ($colRes2 && $colRes2->num_rows) $hasOrderId = true;
                            if ($colRes2) $colRes2->free();
                        } catch (Exception $e) {}

                        if ($hasSalesId) {
                            $q = "SELECT * FROM `{$conn->real_escape_string($t)}` WHERE sales_id = ? ORDER BY id ASC LIMIT 200";
                            $s2 = $conn->prepare($q);
                            if ($s2) {
                                $s2->bind_param('i', $id);
                                if ($s2->execute()) {
                                    $r2 = $s2->get_result();
                                    while ($it = $r2->fetch_assoc()) $items[] = $it;
                                    if ($r2) $r2->free();
                                }
                                $s2->close();
                            }
                        } elseif ($hasOrderId) {
                            $q = "SELECT * FROM `{$conn->real_escape_string($t)}` WHERE order_id = ? ORDER BY id ASC LIMIT 200";
                            $s2 = $conn->prepare($q);
                            if ($s2) {
                                $s2->bind_param('i', $id);
                                if ($s2->execute()) {
                                    $r2 = $s2->get_result();
                                    while ($it = $r2->fetch_assoc()) $items[] = $it;
                                    if ($r2) $r2->free();
                                }
                                $s2->close();
                            }
                        }

                        if (!empty($items)) break;
                    }

                    $response['items'] = $items;
                }
                if ($res) $res->free();
            }
            $stmt->close();
        } else {
            $response['ok'] = false;
            $response['error'] = 'prepare_failed: ' . $conn->error;
        }
    } else {
        // sample data for dev without DB
        $response['ok'] = true;
        $response['order'] = [
            'id' => $id,
            'created_at' => date('Y-m-d H:i:s'),
            'total_amount' => 1965.70,
            'discount' => 0,
            'note' => 'Table 2',
            'table_no' => 'Table 2',
            'payment_method' => 'gcash',
            'service_charge' => 48.90
        ];
        $response['items'] = [
            ['item_name' => 'Lechon Baka', 'qty' => 1, 'unit_price' => 599, 'line_total' => 599],
            ['item_name' => 'Crispy Pata', 'qty' => 1, 'unit_price' => 899, 'line_total' => 899]
        ];
    }

    send_json($response);
}

// ---------- Render HTML (page) ----------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Club Hiraya — Sales Report</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="sales.css">
    <style>
        /* Ensure left cards look like the intended UI even if sales.css isn't loaded */
        .sales-container { display:flex; gap:18px; padding:18px; box-sizing:border-box; }
        .left-panel { width: 320px; background: #e8e8ea; padding:14px; border-radius:12px; max-height: calc(100vh - 160px); overflow-y:auto; box-sizing:border-box; }
        .orders-list { display:flex; flex-direction:column; gap:12px; }
        .order-card { background:#fff; border-radius:10px; padding:10px 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.06); cursor:pointer; display:flex; justify-content:space-between; align-items:flex-start; border:1px solid #ddd; }
        .order-card .meta { display:flex; flex-direction:column; gap:6px; font-size:13px; color:#222; max-width: 220px; }
        .order-card .meta .label { font-weight:700; color:#333; white-space:normal; word-break:break-word; }
        .order-card .meta .sub { font-size:12px; color:#666; }
        .order-card .amount { font-size:16px; font-weight:800; color:#111; padding-left:8px; white-space:nowrap; }
        .order-card.active { border-color:#d33fd3; box-shadow:0 10px 18px rgba(0,0,0,0.12); transform:translateY(-2px); }
        .right-panel { flex:1; padding:18px; background:#e8e8ea; border-radius:12px; min-height:420px; box-sizing:border-box; }
        .right-card { background:#fff; border-radius:10px; padding:18px; border:1px solid #eee; min-height:260px; box-sizing:border-box; }
        .order-title { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .order-title .main { font-weight:800; font-size:20px; color:#222; }
        .order-title .meta { font-size:13px; color:#666; }
        .order-items { margin-top:12px; border-radius:8px; padding:12px; background:#faf9fb; border:1px solid #f0e8ef; }
        .order-item-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed #eee; color:#222; }
        .order-item-row:last-child { border-bottom: none; }
        .order-item-row .left { display:flex; gap:8px; align-items:center; }
        .order-item-row .name { font-weight:600; }
        .order-item-row .qty { color:#555; font-size:13px; }
        .order-item-row .price { font-weight:700; color:#111; min-width:120px; text-align:right; }
        .small-muted { color:#666; font-size:13px; }
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
            <a href="../index.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/home.png" alt="Home"></span><span>Home</span></a>
            <a href="../../ClubTryara/tables/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/table.png" alt="Tables"></span><span>Cabins</span></a>
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
        <div class="topbar" style="left: 160px; right: 40px; position:relative;">
            <div class="search-section">
                <input type="text" class="search-input" placeholder="Search orders" id="searchBox" aria-label="Search orders">
            </div>
        </div>

        <div style="height: 18px;"></div>

        <div class="sales-container" style="margin-left:24px; margin-right:24px;">
            <div class="left-panel" id="leftPanel" aria-label="Orders list">
                <div class="orders-list" id="ordersList">
                    <?php
                    $orders = [];
                    if ($conn) {
                        $sql = "SELECT id, created_at, total_amount, discount, note, payment_method, table_no FROM sales_report ORDER BY created_at DESC LIMIT 200";
                        if ($res = $conn->query($sql)) {
                            while ($r = $res->fetch_assoc()) {
                                $orders[] = $r;
                            }
                            $res->free();
                        }
                    } else {
                        // placeholder orders when no DB available
                        $orders = [
                            ['id'=>101,'created_at'=>'2025-11-03 17:44:00','total_amount'=>1965.70,'discount'=>0,'note'=>'T-13/14','payment_method'=>null,'table_no'=>'Table 13'],
                            ['id'=>102,'created_at'=>'2025-11-03 16:00:00','total_amount'=>1148.54,'discount'=>0,'note'=>'G-4','payment_method'=>null,'table_no'=>null],
                            ['id'=>103,'created_at'=>'2025-11-02 12:30:00','total_amount'=>6381.32,'discount'=>0,'note'=>'T-6/7','payment_method'=>null,'table_no'=>'Table 6'],
                        ];
                    }

                    foreach ($orders as $o) {
                        $id = htmlspecialchars($o['id']);
                        $date = date('M d, Y', strtotime($o['created_at'] ?? date('Y-m-d')));
                        $rawNote = $o['note'] ?? '';
                        $pm = $o['payment_method'] ?? null;
                        $parsed = parse_note_and_payment($rawNote, $pm);

                        // Prefer table_no if present for the label
                        $labelSource = '';
                        if (!empty($o['table_no'])) {
                            $labelSource = $o['table_no'];
                        } elseif (!empty($parsed['label'])) {
                            $labelSource = $parsed['label'];
                        } elseif (!empty($parsed['payment_summary'])) {
                            $labelSource = $parsed['payment_summary'];
                        } else {
                            $labelSource = 'Order ' . $id;
                        }

                        $shortSummary = '';
                        if (!empty($parsed['payment_summary'])) {
                            $shortSummary = (strlen($parsed['payment_summary']) > 40) ? substr($parsed['payment_summary'], 0, 38) . '…' : $parsed['payment_summary'];
                        }

                        $displayLabel = htmlspecialchars($labelSource);
                        $amount = number_format(floatval($o['total_amount'] ?? 0), 2);

                        echo '<div class="order-card" data-id="'.$id.'">';
                        echo '<div class="meta"><span class="label">'.$displayLabel.'</span>';
                        if ($shortSummary) echo '<span class="sub">'.htmlspecialchars($shortSummary).'</span>';
                        echo '<span class="sub">' . htmlspecialchars($date) . '</span></div>';
                        echo '<div class="amount">₱ '.$amount.'</div>';
                        echo '</div>';
                    }

                    if (empty($orders)) {
                        echo '<div class="order-card"><div class="meta"><span class="label">No orders</span><span class="small-muted">No sales yet</span></div><div class="amount">—</div></div>';
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

    function getDetailUrl(id) {
        const base = window.location.href.split('?')[0].split('#')[0];
        if (base.endsWith('/')) return base + 'Sales_Report.php?action=detail&id=' + encodeURIComponent(id);
        return base + '?action=detail&id=' + encodeURIComponent(id);
    }

    function formatCurrency(n) {
        return '₱ ' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    async function loadDetail(id, cardElement) {
        setActiveCard(cardElement);
        detailTitle.textContent = 'Loading...';
        detailDate.textContent = '';
        detailTotal.textContent = '';
        orderItems.innerHTML = '<div class="order-item-empty">Loading items...</div>';
        detailDiscount.textContent = '';

        try {
            const url = getDetailUrl(id);
            const res = await fetch(url, { credentials: 'same-origin' });
            const text = await res.text();

            let payload;
            try {
                payload = JSON.parse(text);
            } catch (e) {
                orderItems.innerHTML = '<div class="order-item-empty">Failed to retrieve order details.<br/><small>Server returned non-JSON response. See debug below.</small></div>';
                const dbg = document.createElement('div');
                dbg.style.marginTop = '8px';
                dbg.style.padding = '8px';
                dbg.style.background = '#fff7f7';
                dbg.style.border = '1px solid #ffd6d6';
                dbg.style.color = '#900';
                dbg.style.maxHeight = '320px';
                dbg.style.overflow = 'auto';
                dbg.innerHTML = '<pre style="white-space:pre-wrap;">' + text.substring(0, 800) + (text.length > 800 ? '\n\n(truncated...)' : '') + '</pre>';
                const rc = document.getElementById('orderItems');
                rc.innerHTML = '';
                rc.appendChild(dbg);
                detailTitle.textContent = 'Error loading order';
                return;
            }

            if (!payload.ok) {
                detailTitle.textContent = 'Order not found';
                orderItems.innerHTML = '<div class="order-item-empty">No details available for this order.</div>';
                return;
            }

            const o = payload.order;
            const title = (o.table_no ? o.table_no : (o.note ? (o.note.split(/\r\n|\n/)[0] || ('Order ' + o.id)) : ('Order ' + o.id)));
            detailTitle.textContent = title;
            detailDate.textContent = new Date(o.created_at).toLocaleString();
            detailTotal.textContent = formatCurrency(o.total_amount || 0);
            detailDiscount.textContent = (o.discount ? formatCurrency(o.discount) : '₱ 0.00');

            // show reservation info if table_no present
            let reserveHtml = '';
            if (o.table_no) {
                reserveHtml += '<div style="margin-top:8px;color:#444"><strong>Reservation / Table:</strong> ' + (o.table_no) + '</div>';
            }

            // build items table
            const items = payload.items || [];
            if (items.length === 0) {
                // fallback: show note lines or "No items"
                if (o.note && o.note.indexOf('\n') !== -1) {
                    const lines = o.note.split('\n');
                    orderItems.innerHTML = '';
                    lines.forEach(line => {
                        const row = document.createElement('div');
                        row.className = 'order-item-row';
                        row.innerHTML = '<div class="left"><div class="name">'+line+'</div></div><div class="price"></div>';
                        orderItems.appendChild(row);
                    });
                } else {
                    orderItems.innerHTML = '<div class="order-item-empty">No line items recorded for this order.</div>';
                }
            } else {
                orderItems.innerHTML = '';
                items.forEach(it => {
                    // support various column names returned
                    const name = it.item_name || it.name || it.product_name || it.title || ('Item #' + (it.id || ''));
                    const qty = (typeof it.qty !== 'undefined') ? it.qty : (it.quantity || 1);
                    const unitPrice = (typeof it.unit_price !== 'undefined') ? it.unit_price : (it.price || it.amount || 0);
                    const lineTotal = (typeof it.line_total !== 'undefined' && Number(it.line_total) !== 0) ? it.line_total : (qty * unitPrice);

                    const row = document.createElement('div');
                    row.className = 'order-item-row';
                    const left = document.createElement('div');
                    left.className = 'left';
                    const nameEl = document.createElement('div');
                    nameEl.className = 'name';
                    nameEl.textContent = name;
                    const qtyEl = document.createElement('div');
                    qtyEl.className = 'qty';
                    qtyEl.textContent = 'x' + qty;
                    left.appendChild(nameEl);
                    left.appendChild(qtyEl);

                    const right = document.createElement('div');
                    right.className = 'price';
                    right.textContent = formatCurrency(lineTotal);

                    row.appendChild(left);
                    row.appendChild(right);
                    orderItems.appendChild(row);
                });

                // show reservation block under items if present
                if (o.table_no) {
                    const row = document.createElement('div');
                    row.style.marginTop = '10px';
                    row.innerHTML = '<div class="small-muted"><strong>Reservation:</strong> ' + (o.table_no) + '</div>';
                    orderItems.appendChild(row);
                }
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

    // click first card if present
    const firstCard = document.querySelector('.order-card');
    if (firstCard) firstCard.click();

    // search filter
    const searchBox = document.getElementById('searchBox');
    if (searchBox) {
      searchBox.addEventListener('input', function(){
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.order-card').forEach(card => {
          const meta = (card.querySelector('.meta .label') && card.querySelector('.meta .label').textContent) || '';
          const sub = (card.querySelector('.meta .sub') && card.querySelector('.meta .sub').textContent) || '';
          const amt = (card.querySelector('.amount') && card.querySelector('.amount').textContent) || '';
          if (!q || meta.toLowerCase().includes(q) || sub.toLowerCase().includes(q) || amt.toLowerCase().includes(q)) {
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