<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Club Tryara — Tables</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />

  <link rel="stylesheet" href="../css/table.css">

  <!-- Minimal modal styles (kept local so you don't need to edit main CSS) -->
  <style>
    /* Simple modal */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9998;
    }
    .modal-backdrop.open { display: flex; }

    .modal {
      width: 100%;
      max-width: 520px;
      background: #fff;
      border-radius: 12px;
      padding: 18px;
      box-shadow: 0 12px 30px rgba(0,0,0,0.25);
      box-sizing: border-box;
    }
    .modal h2 { margin: 0 0 8px 0; font-size: 20px; }
    .modal .row { display: flex; gap: 8px; margin-bottom: 10px; }
    .modal label { display:block; font-size: 13px; margin-bottom: 4px; color:#333; }
    .modal input[type="text"],
    .modal input[type="date"],
    .modal input[type="time"],
    .modal input[type="number"],
    .modal select {
      width: 100%;
      padding: 8px 10px;
      border-radius: 8px;
      border: 1px solid #ccc;
      box-sizing: border-box;
      font-size: 14px;
    }
    .modal .actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px; }
    .modal .btn { padding: 8px 12px; border-radius: 8px; border: none; cursor: pointer; font-weight:700; }
    .modal .btn.primary { background:#001B89; color:#fff; }
    .modal .btn.ghost { background:#f0f0f0; color:#111; }
    .modal .note { font-size:13px; color:#666; margin-top:6px; }
    .modal .status { margin-top:8px; font-size:14px; }
  </style>
</head>
<body>
    <noscript>
        <div class="noscript-warning">This app requires JavaScript to function correctly. Please enable JavaScript.</div>
    </noscript>

    <!-- Sidebar -->
    <aside class="sidebar" role="complementary" aria-label="Sidebar">
        <div class="sidebar-header">
            <img src="../assets/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
        </div>

      <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
          <a href="../index.php" class="sidebar-btn" aria-current="page">
              <span class="sidebar-icon"><img src="../assets/home.png" alt="Home icon"></span>
              <span>Home</span>
          </a>
          <a href="../php/tables.php" class="sidebar-btn active">
              <span class="sidebar-icon"><img src="../assets/table.png" alt="Tables icon"></span>
              <span>Tables</span>
          </a>
          <a href="inventory.php" class="sidebar-btn">
              <span class="sidebar-icon"><img src="../assets/inventory.png" alt="Inventory icon"></span>
              <span>Inventory</span>
          </a>
          <a href="sales_report.php" class="sidebar-btn">
              <span class="sidebar-icon"><img src="../assets/sales.png" alt="Sales report icon"></span>
              <span>Sales Report</span>
          </a>
          <a href="settings.php" class="sidebar-btn">
              <span class="sidebar-icon"><img src="../assets/setting.png" alt="Settings icon"></span>
              <span>Settings</span>
          </a>
        </nav>

        <div style="flex:1" aria-hidden="true"></div>

        <button class="sidebar-logout" type="button" aria-label="Logout">
            <span>Logout</span>
        </button>
    </aside>

    <div class="topbar" aria-hidden="false">
    <div class="search-wrap" role="search" aria-label="Search tables">
      <input id="searchInput" type="search" placeholder="Search products" aria-label="Search products">
      <button id="searchClear" title="Clear search" aria-label="Clear search">✕</button>
    </div>
  </div>

  <!-- Filters row (normal flow, sits under the fixed topbar) -->
  <div class="filters-row" aria-hidden="false">
    <div class="filters" role="tablist" aria-label="Table filters">
      <button class="filter-btn active" data-filter="all" id="filterAll" role="tab" aria-selected="true">🏠 All Table</button>
      <button class="filter-btn" data-filter="party" id="filterParty" role="tab" aria-selected="false">👥 Party Size</button>
      <button class="filter-btn" data-filter="date" id="filterDate" role="tab" aria-selected="false">📅 Date</button>
      <button class="filter-btn" data-filter="time" id="filterTime" role="tab" aria-selected="false">⏲️ Time</button>

      <!-- Primary action -->
      <button id="btnAddReservation" class="filter-btn action-btn" aria-label="New reservation" title="New reservation">➕ New</button>

      <!-- Party controls -->
      <div id="partyControl" class="party-size-control" aria-hidden="true">
        <label for="partySelect">Seats:</label>
        <select id="partySelect" aria-label="Filter by number of seats">
          <option value="any">Any</option>
          <option value="2">1-2</option>
          <option value="4">3-4</option>
          <option value="6">5-6</option>
          <option value="8">7-8</option>
        </select>

        <!-- Quick bucket buttons -->
        <div class="party-buckets" aria-hidden="false">
          <button type="button" class="bucket-btn" data-seats="2" title="1-2 persons">1–2</button>
          <button type="button" class="bucket-btn" data-seats="4" title="3-4 persons">3–4</button>
          <button type="button" class="bucket-btn" data-seats="6" title="5-6 persons">5–6</button>
          <button type="button" class="bucket-btn" data-seats="8" title="7-8 persons">7–8</button>
        </div>

        <!-- Allow larger tables toggle -->
        <label class="allow-larger">
          <input type="checkbox" id="allowLarger" aria-label="Allow larger tables (use larger tables if exact not available)">
          Allow larger tables
        </label>
      </div>

      <!-- date/time controls (basic placeholders) -->
      <div id="dateControl" class="party-size-control" aria-hidden="true">
        <input type="date" id="filterDateInput" aria-label="Filter by date">
      </div>

      <div id="timeControl" class="party-size-control" aria-hidden="true">
        <input type="time" id="filterTimeInput" aria-label="Filter by time">
      </div>
    </div>
  </div>

  <!-- Page content -->
  <main class="content-wrap" role="main">
    <div class="cards-backdrop" id="cardsBackdrop" tabindex="0" aria-live="polite">
      <div id="viewHeader" class="view-header" aria-hidden="false"></div>
      <div id="viewContent" class="view-content">
        <div class="cards-grid" id="cardsGrid" role="list"></div>
      </div>
    </div>
  </main>

  <!-- Reservation modal markup (minimal) -->
  <div id="reservationModalBackdrop" class="modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal" role="document" aria-labelledby="reservationTitle">
      <h2 id="reservationTitle">New Reservation</h2>

      <form id="reservationForm" novalidate>
        <div class="row">
          <div style="flex:1">
            <label for="resDate">Date</label>
            <input id="resDate" type="date" required>
          </div>
          <div style="width:130px">
            <label for="resTime">Time</label>
            <input id="resTime" type="time" required>
          </div>
        </div>

        <div class="row">
          <div style="flex:1">
            <label for="resParty">Party size</label>
            <input id="resParty" type="number" min="1" value="2" required>
          </div>
          <div style="width:130px">
            <label for="resDuration">Duration (min)</label>
            <input id="resDuration" type="number" min="15" step="15" value="90" required>
          </div>
        </div>

        <div class="row">
          <div style="flex:1">
            <label for="resGuest">Guest name (optional)</label>
            <input id="resGuest" type="text" maxlength="255" placeholder="Guest name">
          </div>
        </div>

        <div class="note">This will assign an available table automatically. You can specify a table later from the admin view.</div>

        <div class="status" id="resStatus" aria-live="polite"></div>

        <div class="actions">
          <button type="button" class="btn ghost" id="resCancel">Cancel</button>
          <button type="submit" class="btn primary" id="resSubmit">Create</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../js/table.js" defer></script>
  <script src="../js/reservation.js" defer></script>

  <button id="fabNew" class="fab" aria-label="New reservation" title="New reservation">＋</button>
</body>
</html>