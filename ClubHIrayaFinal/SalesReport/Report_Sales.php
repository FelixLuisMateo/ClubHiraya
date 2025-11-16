<?php
session_start();
require_once __DIR__ . '/../php/db_connect.php';
date_default_timezone_set('Asia/Manila');

// Get current range and offset for navigation
$range = $_GET['range'] ?? 'week';
$offset = intval($_GET['offset'] ?? 0); // offset to move between weeks/months/years

// Selected month/year (from dropdowns). 0 means "not selected".
$selectedMonth = intval($_GET['month_select'] ?? 0);
$selectedYear  = intval($_GET['year_select'] ?? 0);

$today = new DateTime();
$startDate = new DateTime();
$endDate = new DateTime();

// Handle date range with offset logic (robust)
switch ($range) {
    case 'month':
        // If user selected a month/year, use it; otherwise default to current month/year
        if ($selectedMonth >= 1 && $selectedMonth <= 12 && $selectedYear >= 1900) {
            $startDate = DateTime::createFromFormat('Y-n-j', sprintf('%04d-%d-1', $selectedYear, $selectedMonth));
        } else {
            $startDate = new DateTime('first day of this month');
        }

        // Apply month offset safely
        if ($offset !== 0) {
            $months = abs($offset);
            if ($offset > 0) {
                $startDate->add(new DateInterval('P' . $months . 'M'));
            } else {
                $startDate->sub(new DateInterval('P' . $months . 'M'));
            }
        }

        $endDate = (clone $startDate)->modify('last day of this month');
        $startDate->setTime(0,0,0);
        $endDate->setTime(23,59,59);
        break;

    case 'year':
        // If user selected year, use it; otherwise default to this year
        if ($selectedYear >= 1900) {
            $startDate = DateTime::createFromFormat('Y-m-d', sprintf('%04d-01-01', $selectedYear));
        } else {
            $startDate = new DateTime('first day of January this year');
        }

        // Apply year offset safely
        if ($offset !== 0) {
            $years = abs($offset);
            if ($offset > 0) {
                $startDate->add(new DateInterval('P' . $years . 'Y'));
            } else {
                $startDate->sub(new DateInterval('P' . $years . 'Y'));
            }
        }

        $yearNum = intval($startDate->format('Y'));
        $endDate = new DateTime("$yearNum-12-31 23:59:59");
        $startDate->setTime(0,0,0);
        break;

    default:
        // week
        $startDate = new DateTime();
        $startDate->modify('monday this week');
        if ($offset !== 0) {
            $weeks = abs($offset);
            $days = $weeks * 7;
            if ($offset > 0) $startDate->add(new DateInterval('P' . $days . 'D'));
            else $startDate->sub(new DateInterval('P' . $days . 'D'));
        }
        $endDate = (clone $startDate)->modify('sunday this week');
        $startDate->setTime(0,0,0);
        $endDate->setTime(23,59,59);
        break;
}

$startStr = $startDate->format('Y-m-d H:i:s');
$endStr = $endDate->format('Y-m-d H:i:s');

// --- Fetch Data ---
$salesData = [];
if ($conn) {
    if ($range === 'year') {
        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as period, SUM(total_amount) as total
            FROM sales_report
            WHERE created_at BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period ASC
        ");
    } elseif ($range === 'month') {
        $stmt = $conn->prepare("
            SELECT WEEK(created_at, 1) as period, SUM(total_amount) as total
            FROM sales_report
            WHERE created_at BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period ASC
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT DATE(created_at) as period, SUM(total_amount) as total
            FROM sales_report
            WHERE created_at BETWEEN ? AND ?
            GROUP BY period
            ORDER BY period ASC
        ");
    }

    if ($stmt) {
        $stmt->bind_param('ss', $startStr, $endStr);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $salesData[$r['period']] = floatval($r['total']);
        }
        $stmt->close();
    }
}

// --- Prepare Chart Data ---
$chartLabels = [];
$chartTotals = [];

if ($range === 'year') {
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $yearNum = intval($startDate->format('Y'));
    for ($i = 1; $i <= 12; $i++) {
        $key = sprintf('%04d-%02d', $yearNum, $i);
        $chartLabels[] = $months[$i - 1];
        $chartTotals[] = $salesData[$key] ?? 0;
    }
} elseif ($range === 'month') {
    $weeks = ['Week 1','Week 2','Week 3','Week 4','Week 5'];
    $weekNum = intval($startDate->format('W'));
    foreach ($weeks as $index => $label) {
        $chartLabels[] = $label;
        $chartTotals[] = $salesData[$weekNum + $index] ?? 0;
    }
} else {
    $period = new DatePeriod($startDate, new DateInterval('P1D'), (clone $endDate)->modify('+1 day'));
    foreach ($period as $date) {
        $d = $date->format('Y-m-d');
        $chartLabels[] = $date->format('D');
        $chartTotals[] = $salesData[$d] ?? 0;
    }
}

$totalToDate = array_sum($chartTotals);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Club Hiraya — Summary Report</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="sales.css">
<style>
.report-container { display:flex; flex-direction:column; gap:20px; padding:24px; }
.chart-row { display:flex; gap:24px; align-items:flex-start; }
.chart-box { flex:3; background:#fff; border-radius:12px; padding:20px; border:1px solid #ddd; box-shadow:0 3px 8px rgba(0,0,0,0.08); }
.summary-box { flex:1; background:#fff; border-radius:12px; padding:18px; display:flex; flex-direction:column; gap:12px; border:1px solid #ddd; }
.summary-item { border:1px solid #eee; border-radius:8px; padding:12px; background:#faf9fb; font-weight:600; }
.summary-item small { display:block; font-weight:500; }
.summary-item strong { font-size:18px; ; }
.table-section { background:#fff; border-radius:12px; padding:14px; border:1px solid #ddd; overflow-x:auto; }
.filter-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:10px; }
.filter-bar select, .filter-bar button {
  padding:6px 10px; border-radius:6px; border:1px solid #999; cursor:pointer;
}
.filter-bar button {
  background:linear-gradient(135deg,#d33fd3,#a2058f); color:#fff; font-weight:600;
}
.filter-bar button:hover { opacity:0.8; }
table { width:100%; border-collapse:collapse; font-size:14px; min-width:600px; }
th, td { border:1px solid #000000ff; padding:8px; text-align:center; white-space:nowrap; }
th { background:#f0f0f0; }
.topbar-buttons { display:flex; gap:10px; align-items:center; margin-left:auto; }
.topbar-buttons a {
  text-decoration:none; padding:8px 16px; border-radius:8px; background:#ddd; color:#222; font-weight:600; transition:0.2s;
}
.topbar-buttons a:hover { background:#ccc; }
.topbar-buttons a.active {
  background:linear-gradient(135deg,#d33fd3,#a2058f); color:#fff;
}
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


<!-- Sidebar -->
<aside class="sidebar" role="complementary" aria-label="Sidebar">
    <div class="sidebar-header">
        <img src="../../clubtryara/assets/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
    </div>
    <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
        <a href="../admin_dashboard.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/home.png" alt="Home"></span><span>Home</span></a>
        <a href="../tables/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/cabin.png" alt="Tables"></span><span>Cabins</span></a>
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

<!-- Main -->
<main class="main-content" role="main" aria-label="Main content" style="padding:22px;">
    <div class="topbar" style="left:160px; right:40px; display:flex; justify-content:space-between; align-items:center;">
        <div class="search-section">
            <input type="text" class="search-input" placeholder="Search report..." aria-label="Search">
        </div>
        <div class="topbar-buttons">
            <a href="Sales_Report.php">Sales Report</a>
            <a href="report_sales.php" class="active">Summary Report</a>
        </div>
    </div>

    <div class="report-container">
        <div class="chart-row">
            <div class="chart-box">
                <canvas id="salesChart" height="120"></canvas>
            </div>
            <div class="summary-box">
                <div class="summary-item">
                    <small>Today</small>
                    <strong>₱<?php echo number_format($salesData[$today->format('Y-m-d')] ?? 0, 2); ?></strong>
                </div>
                <div class="summary-item">
                    <small>Yesterday</small>
                    <strong>₱<?php
                        $yesterday = (clone $today)->modify('-1 day')->format('Y-m-d');
                        echo number_format($salesData[$yesterday] ?? 0, 2);
                    ?></strong>
                </div>
                <div class="summary-item">
                    <small><?php echo ucfirst($range); ?> to Date</small>
                    <strong>₱<?php echo number_format($totalToDate, 2); ?></strong>
                </div>
            </div>
        </div>

        <div class="table-section">
            <div class="filter-bar">
                <div>
                    <button onclick="navigateRange(-1)">⬅️ Previous</button>
                    <strong style="margin:0 10px;"><?php echo $startDate->format('M d, Y'); ?> – <?php echo $endDate->format('M d, Y'); ?></strong>
                    <button onclick="navigateRange(1)">Next ➡️</button>
                </div>
                <form method="get">
                    <?php
                    // Reset offset to 0 when selecting month/year
                    if ($range === 'month' && isset($_GET['month_select'])) {
                        $offset = 0;
                    }
                    if ($range === 'year' && isset($_GET['year_select'])) {
                        $offset = 0;
                    }
                    ?>
                    <input type="hidden" name="offset" value="<?php echo $offset; ?>">

                    <select name="range" onchange="this.form.submit()">
                        <option value="week" <?php if($range=='week') echo 'selected'; ?>>This Week</option>
                        <option value="month" <?php if($range=='month') echo 'selected'; ?>>This Month</option>
                        <option value="year" <?php if($range=='year') echo 'selected'; ?>>This Year</option>
                    </select>

                    <!-- Month dropdown (shows ONLY when range=month) -->
                    <select name="month_select" id="monthSelect" onchange="this.form.submit()" style="display:<?php echo ($range==='month')?'inline-block':'none'; ?>; margin-left:8px;">
                        <?php
                        $defaultMonth = intval($startDate->format('n'));
                        $useMonth = ($selectedMonth >=1 && $selectedMonth <=12) ? $selectedMonth : $defaultMonth;
                        for ($m=1;$m<=12;$m++) {
                            $sel = ($m == $useMonth) ? 'selected' : '';
                            echo "<option value='$m' $sel>" . date('F', mktime(0,0,0,$m,1)) . "</option>";
                        }
                        ?>
                    </select>

                    <!-- Year dropdown (shows ONLY when range=year) -->
                    <select name="year_select" id="yearSelect" onchange="this.form.submit()" style="display:<?php echo ($range==='year')?'inline-block':'none'; ?>; margin-left:8px;">
                        <?php
                        // Years: current year down to current-10
                        $currYear = intval((new DateTime())->format('Y'));
                        $startYear = $currYear;
                        $endYear = $currYear - 10;
                        $useYear = ($selectedYear >= 1900) ? $selectedYear : intval($startDate->format('Y'));
                        for ($y = $startYear; $y >= $endYear; $y--) {
                            $sel = ($y == $useYear) ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                        ?>
                    </select>

                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($chartLabels as $lbl): ?>
                            <th><?php echo htmlspecialchars($lbl); ?></th>
                        <?php endforeach; ?>
                        <th><?php echo ucfirst($range); ?> to Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($chartTotals as $amt): ?>
                            <td>₱<?php echo number_format($amt, 2); ?></td>
                        <?php endforeach; ?>
                        <td><strong>₱<?php echo number_format($totalToDate, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function navigateRange(direction) {
  const url = new URL(window.location.href);
  let offset = parseInt(url.searchParams.get('offset') || 0);
  offset += direction;
  url.searchParams.set('offset', offset);
  // keep range param (use current selection from server-rendered PHP)
  url.searchParams.set('range', '<?php echo $range; ?>');
  // when month/year dropdowns are visible include their current values
  const monthEl = document.getElementById('monthSelect');
  const yearEl = document.getElementById('yearSelect');
  if (monthEl && monthEl.style.display !== 'none') url.searchParams.set('month_select', monthEl.value);
  if (yearEl && yearEl.style.display !== 'none') url.searchParams.set('year_select', yearEl.value);
  window.location.href = url.toString();
}
</script>

<script>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'Sales (₱)',
            data: <?php echo json_encode($chartTotals); ?>,
            backgroundColor: 'rgba(211,63,211,0.7)',
            borderColor: '#a2058f',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>
