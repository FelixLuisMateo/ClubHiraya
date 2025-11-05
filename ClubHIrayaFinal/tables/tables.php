<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Club Tryara — Tables</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../css/table.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    .date-simple-wrap { margin: 24px 0 16px; display: flex; align-items: center; gap: 10px; }
    .table-status-result { margin: 18px 0 36px; }
    .table-status-result .tbl-card {
      padding: 12px; border-radius: 6px; border:1px solid #ddd;
      margin-bottom: 5px; display: flex; align-items: center; justify-content: space-between;
    }
    .tbl-status {
      font-weight: bold; padding: 3px 14px; border-radius: 8px; font-size: 1rem;
    }
    .tbl-status.available { background: #e7faee; color: #00b256; }
    .tbl-status.reserved { background: #fff7d6; color: #cc8600; }
    .tbl-status.occupied { background: #ffe8e8; color: #d20000; }
  </style>
</head>
<body>
    <noscript>
        <div class="noscript-warning">This app requires JavaScript to function correctly. Please enable JavaScript.</div>
    </noscript>

    <!-- Sidebar (unchanged) -->
    <aside class="sidebar" role="complementary" aria-label="Sidebar">
        <div class="sidebar-header">
            <img src="../assets/foods/logos/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
        </div>
        <nav class="sidebar-menu" class="sidebar-btn" aria-current="page">
            <a href="../admin_dashboard.php" class="sidebar-btn" aria-current="page">
                <span class="sidebar-icon"><img src="../assets/foods/logos/home.png" alt="Home icon"></span>
                <span>Home</span>
            </a>
            <a href="../tables/tables.php" class="sidebar-btn active">
                <span class="sidebar-icon"><img src="../assets/foods/logos/table.png" alt="Tables icon"></span>
                <span>Tables</span>
            </a>
            <a href="../inventory/inventory.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../assets/foods/logos/inventory.png" alt="Inventory icon"></span>
                <span>Inventory</span>
            </a>
            <a href="../salesreport/sales_report.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../assets/foods/logos/sales.png" alt="Sales report icon"></span>
                <span>Sales Report</span>
            </a>
            <a href="../settings/settings.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="../assets/foods/logos/setting.png" alt="Settings icon"></span>
                <span>Settings</span>
            </a>
        </nav>
        <div style="flex:1" aria-hidden="true"></div>
        <button class="sidebar-logout" type="button" aria-label="Logout">
            <span>Logout</span>
        </button>
    </aside>

    <!-- Topbar (unchanged) -->
    <div class="topbar" aria-hidden="false">
      <div class="search-wrap" role="search" aria-label="Search tables">
        <input id="searchInput" type="search" placeholder="Search tables" aria-label="Search tables">
        <button id="searchClear" title="Clear search" aria-label="Clear search">✕</button>
      </div>
    </div>

    <!-- Simple date picker and status result (this replaces the boxes!) -->
    <div class="date-simple-wrap">
      <input type="text" id="tableDate" placeholder="YYYY-MM-DD" />
      <button id="btnCheckStatus" type="button">Check Table Status</button>
      <span id="dateStatusError" style="color:#c00; font-weight:600;"></span>
    </div>
    <div id="tableStatusResult" class="table-status-result"></div>

    <!-- Filters row (unchanged) -->
    <div class="filters-row" aria-hidden="false">
      <div class="filters" role="tablist" aria-label="Table filters">
        <button class="filter-btn active" data-filter="all" id="filterAll" role="tab" aria-selected="true">🏠 All Table</button>
        <button class="filter-btn" data-filter="party" id="filterParty" role="tab" aria-selected="false">👥 Party Size</button>
        <button class="filter-btn" data-filter="date" id="filterDate" role="tab" aria-selected="false">📅 Date</button>
        <button class="filter-btn" data-filter="time" id="filterTime" role="tab" aria-selected="false">⏲️ Time</button>
        <button id="btnAddReservation" class="filter-btn action-btn" aria-label="New reservation" title="New reservation">➕ New</button>
        <div id="partyControl" class="party-size-control" aria-hidden="true">
          <label for="partySelect">Seats:</label>
          <select id="partySelect" aria-label="Filter by number of seats">
            <option value="any">Any</option>
            <option value="2">1-2</option>
            <option value="4">3-4</option>
            <option value="6">5-6</option>
            <option value="8">7-8</option>
          </select>
        </div>
        <div id="dateControl" class="party-size-control" aria-hidden="true">
          <input type="date" id="filterDateInput" aria-label="Filter by date">
        </div>
        <div id="timeControl" class="party-size-control" aria-hidden="true">
          <input type="time" id="filterTimeInput" aria-label="Filter by time">
        </div>
      </div>
    </div>

    <!-- Main content (unchanged) -->
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

  <!-- Scripts -->
  <script src="../js/table.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      flatpickr("#tableDate", {
        dateFormat: "Y-m-d",
        minDate: "today",
        defaultDate: new Date()
      });

      document.getElementById('btnCheckStatus').addEventListener('click', async function() {
        const pickedDate = document.getElementById('tableDate').value;
        document.getElementById('dateStatusError').textContent = '';
        if (!pickedDate) {
          document.getElementById('dateStatusError').textContent = 'Please select a date.';
          return;
        }
        const time = "12:00";
        document.getElementById('tableStatusResult').innerHTML = 'Loading...';

        try {
          const res = await fetch(`../api/get_availability.php?date=${encodeURIComponent(pickedDate)}&time=${encodeURIComponent(time)}&duration=90&seats=1`);
          const availJson = await res.json();

          // Fetch all tables for more status info
          const allRes = await fetch('../api/get_tables.php');
          const allJson = await allRes.json();
          let allTables = (allJson.success && Array.isArray(allJson.data)) ? allJson.data : [];

          // IDs of available tables
          const availableIds = availJson.success && Array.isArray(availJson.data)
            ? availJson.data.map(t => parseInt(t.id,10))
            : [];

          const html = allTables.map(tbl => {
            let status = tbl.status;
            let statusClass = '';
            if (availableIds.includes(parseInt(tbl.id,10))) {
              status = 'available';
              statusClass = 'available';
            } else if (tbl.status === 'reserved') {
              statusClass = 'reserved';
            } else if (tbl.status === 'occupied') {
              statusClass = 'occupied';
            }
            return `<div class="tbl-card">
                      <span>${tbl.name} (${tbl.seats} seats)</span>
                      <span class="tbl-status ${statusClass}">${status[0].toUpperCase()+status.slice(1)}</span>
                    </div>`;
          }).join('');
          document.getElementById('tableStatusResult').innerHTML = html || '<div>No tables found for this date.</div>';
        } catch (err) {
          document.getElementById('tableStatusResult').innerHTML = `<div>Network error: ${err.message||err}</div>`;
        }
      });
    });
  </script>
  <button id="fabNew" class="fab" aria-label="New reservation" title="New reservation">＋</button>
</body>
</html>