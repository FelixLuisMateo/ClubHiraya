
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Club Hiraya — Tables</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />

  <link rel="stylesheet" href="../css/table.css">
  <!-- small overrides (pins filters to top, hides search topbar) -->
  <link rel="stylesheet" href="../css/table-overrides.css">
  <!-- flatpickr for inline calendar (required by calendar.js) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

        <nav class="sidebar-menu" class="sidebar-btn" aria-current="page">
            <a href="../admin_dashboard.php" class="sidebar-btn" aria-current="page">
                <span class="sidebar-icon"><img src="../assets/logos/home.png" alt="Home icon"></span>
                <span>Home</span>
            </a>
            <a href="../tables/tables.php" class="sidebar-btn active">
                <span class="sidebar-icon"><img src="../assets/logos/cabin.png" alt="Tables icon"></span>
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
            <a href="../settings/settings.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../assets/logos/setting.png" alt="Settings icon"></span>
                <span>Settings</span>
            </a>
        </nav>

        <div style="flex:1" aria-hidden="true"></div>

        <!-- Logout form: uses POST to call logout.php -->
        <form method="post" action="logout.php" style="margin:0;">
            <button class="sidebar-logout" type="submit" aria-label="Logout">
                <span>Logout</span>
            </button>
        </form>
    </aside>

  <!-- NOTE: top search bar removed per request -->

    <!-- Filters row (now pinned to top by CSS) -->
  <div class="filters-row" aria-hidden="false">
    <div class="filters" role="tablist" aria-label="Table filters">
      <button class="filter-btn active" data-filter="all" id="filterAll" role="tab" aria-selected="true">🏠 All Table</button>
      <button class="filter-btn" data-filter="party" id="filterParty" role="tab" aria-selected="false">👥 Party Size</button>
      <button class="filter-btn" data-filter="date" id="filterDate" role="tab" aria-selected="false">📅 Date</button>
      <button class="filter-btn" data-filter="time" id="filterTime" role="tab" aria-selected="false">⏲️ Time</button>
      <button id="btnAddReservation" class="filter-btn action-btn" aria-label="New reservation" title="New reservation">➕ New</button>

      <!-- party-size control -->
      <div id="partyControl" class="party-size-control" aria-hidden="true">
        <label for="partySelect">Seats:</label>
        <select id="partySelect" aria-label="Filter by number of seats">
          <option value="any">Any</option>
          <option value="2">1-2</option>
          <option value="4">3-4</option>
          <option value="6">5-6</option>
        </select>
      </div>

      <!-- NEW: Party sort control -->
      <div id="partySortControl" class="party-sort-control" aria-hidden="true" style="margin-left:12px;">
        <label for="partySortSelect">Sort by Seats:</label>
        <select id="partySortSelect">
          <option value="default">Default</option>
          <option value="asc">Ascending</option>
          <option value="desc">Descending</option>
        </select>
      </div>

      <!-- date/time controls (kept for JS to use inside views) -->
      <div id="dateControl" class="party-size-control" aria-hidden="true">
        <input type="date" id="filterDateInput" aria-label="Filter by date">
      </div>
    </div>
  </div>

  <!-- Page content -->
  <main class="content-wrap" role="main">
    <div class="cards-backdrop" id="cardsBackdrop" tabindex="0" aria-live="polite">
      <div id="viewHeader" class="view-header" aria-hidden="false"></div>
      <div id="viewContent" class="view-content">
        <div class="cards-grid" id="cardsGrid" role="list">
          <!-- JS will render table cards here -->
        </div>
      </div>
    </div>
  </main>

  <!-- Load scripts: table.js first (renders views), then flatpickr, then calendar.js so it can initialize calendars. -->
  <script src="../js/table.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
  <script src="../js/calendar.js" defer></script>

  <button id="fabNew" class="fab" aria-label="New reservation" title="New reservation">＋</button>
</body>
</html>