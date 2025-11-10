<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Reservation Map - Club Hiraya</title>
  <link rel="stylesheet" href="cabins.css">
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <img src="hiraya.png" alt="Club Hiraya Logo" class="logo-img"
           onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;64&quot; height=&quot;64&quot;><text x=&quot;50%&quot; y=&quot;55%&quot; font-size=&quot;18&quot; text-anchor=&quot;middle&quot; fill=&quot;white&quot; font-family=&quot;Arial&quot;>HIRAYA</text></svg>';">
      <div class="site-title">
        <div class="club-name">CLUB HIRAYA</div>
        <div class="page-title">Reservation Map</div>
      </div>
    </div>

            <a href="../admin_dashboard.php" class="sidebar-btn" aria-current="page">
                <span class="navbtn"><img src="home.png" alt="Home icon"></span>
                <span>Home</span>
            </a>
            <a href="../tables/tables.php" class="sidebar-btn active">
                <span class="navbtn"><img src="../assets/foods/logos/table.png" alt="Cabins icon"></span>
                <span>Cabins</span>
            </a>
            <a href="../inventory/inventory.php" class="sidebar-btn">
                <span class="navbtn"><img src="../assets/foods/logos/inventory.png" alt="Inventory icon"></span>
                <span>Inventory</span>
            </a>
            <a href="../salesreport/../SalesReport/sales_report.php" class="sidebar-btn">
                <span class="navbtn"><img src="../assets/foods/logos/sales.png" alt="Sales report icon"></span>
                <span>Sales Report</span>
            </a>
            <a href="../settings/settings.php" class="sidebar-btn">
                <span class="navbtn"><img src="settings.png" alt="Settings icon"></span>
                <span>Settings</span>
            </a>
        </nav>
  </header>

  <main class="container">
    <section class="map-area" id="mapArea" aria-label="Reservation map">
      <div class="map-inner">

        <!-- CENTER BAR -->
        <div class="bar-wrapper" id="bar">
          <img class="bar-img" src="bar.png" alt="Bar"
               onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; viewBox=&quot;0 0 220 300&quot; width=&quot;220&quot; height=&quot;300&quot;><rect width=&quot;220&quot; height=&quot;300&quot; rx=&quot;110&quot; fill=&quot;white&quot; stroke=&quot;#333&quot; stroke-width=&quot;4&quot;/><text x=&quot;50%&quot; y=&quot;50%&quot; text-anchor=&quot;middle&quot; dominant-baseline=&quot;middle&quot; fill=&quot;#222&quot; font-size=&quot;20&quot;>Bar</text></svg>';">
        </div>

        <!-- Cabins C1..C8 (positions approximate to your image) -->
        <!-- Each object uses: data-id, data-type (cabin/hut/table), data-occupied, data-customer, data-days -->
        <div class="map-object cabin" data-id="C1" data-type="cabin" data-occupied="false" style="left:140px; bottom:30px;">
          <img class="obj-img" src="cabin.png" alt="cabin" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn" title="Toggle selection / show info">C1</button>
        </div>

        <div class="map-object hut" data-id="B1" data-type="hut" data-occupied="false" style="left:250px; bottom:18px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn" title="Toggle selection / show info">B1</button>
        </div>

        <div class="map-object cabin" data-id="C2" data-type="cabin" data-occupied="false" style="left:330px; bottom:30px;">
          <img class="obj-img" src="cabin.png" alt="cabin" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">C2</button>
        </div>

        <div class="map-object hut" data-id="B2" data-type="hut" data-occupied="false" style="left:450px; bottom:18px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">B2</button>
        </div>

        <div class="map-object cabin" data-id="C3" data-type="cabin" data-occupied="false" style="left:530px; bottom:30px;">
          <img class="obj-img" src="cabin.png" alt="cabin" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">C3</button>
        </div>

        <div class="map-object hut" data-id="B3" data-type="hut" data-occupied="false" style="left:660px; bottom:58px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">B3</button>
        </div>

        <div class="map-object hut" data-id="B4" data-type="hut" data-occupied="false" style="left:660px; bottom:-8px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">B4</button>
        </div>

        <div class="map-object cabin" data-id="C4" data-type="cabin" data-occupied="false" style="left:40px; top:420px;">
          <img class="obj-img" src="cabin.png" alt="cabin" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">C4</button>
        </div>

        <div class="map-object hut" data-id="B5" data-type="hut" data-occupied="false" style="left:50px; top:480px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">B5</button>
        </div>

        <div class="map-object cabin" data-id="C5" data-type="cabin" data-occupied="false" style="left:40px; top:270px;">
          <img class="obj-img" src="cabin.png" alt="cabin" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">C5</button>
        </div>

        <div class="map-object hut" data-id="B6" data-type="hut" data-occupied="false" style="left:50px; top:320px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">B6</button>
        </div>

        <div class="map-object cabin" data-id="C6" data-type="cabin" data-occupied="false" style="left:40px; top:120px;">
          <img class="obj-img" src="cabin.png" alt="cabin" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">C6</button>
        </div>

        <div class="map-object cabin" data-id="C7" data-type="cabin" data-occupied="false" style="left:160px; top:10px;">
          <img class="obj-img" src="cabin.png" alt="cabin" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">C7</button>
        </div>

        <div class="map-object hut" data-id="B7" data-type="hut" data-occupied="false" style="left:50px; top:170px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">B7</button>
        </div>

        <div class="map-object hut" data-id="B8" data-type="hut" data-occupied="false" style="left:300px; top:1px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">B8</button>
        </div>

        <div class="map-object cabin" data-id="C8" data-type="cabin" data-occupied="false" style="left:500px; top:10px;">
          <img class="obj-img" src="cabin.png" alt="cabin" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">C8</button>
        </div>

        <div class="map-object hut" data-id="B9" data-type="hut" data-occupied="false" style="left:400px; top:1px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">B9</button>
        </div>

        <div class="map-object hut" data-id="B10" data-type="hut" data-occupied="false" style="left:630px; top:1px;">
          <img class="obj-img" src="bahaykubo.png" alt="hut" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">B10</button>
        </div>

        <!-- Scatter tables T1..T10 -->
        <div class="map-object table" data-id="T1" data-type="table" data-occupied="false" style="left:250px; top:470px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T1</button>
        </div>

        <div class="map-object table" data-id="T2" data-type="table" data-occupied="false" style="left:450px; top:470px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T2</button>
        </div>

        <div class="map-object table" data-id="T3" data-type="table" data-occupied="false" style="left:650px; top:434px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T3</button>
        </div>

        <div class="map-object table" data-id="T4" data-type="table" data-occupied="false" style="left:620px; top:360px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T4</button>
        </div>

        <div class="map-object table" data-id="T5" data-type="table" data-occupied="false" style="left:810px; top:250px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T5</button>
        </div>

        <div class="map-object table" data-id="T6" data-type="table" data-occupied="false" style="left:630px; top:250px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T6</button>
        </div>

        <div class="map-object table" data-id="T7" data-type="table" data-occupied="false" style="left:620px; top:180px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T7</button>
        </div>

        <div class="map-object table" data-id="T8" data-type="table" data-occupied="false" style="left:850px; top:170px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T8</button>
        </div>

        <div class="map-object table" data-id="T9" data-type="table" data-occupied="false" style="left:580px; top:85px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T9</button>
        </div>

        <div class="map-object table" data-id="T10" data-type="table" data-occupied="false" style="left:700px; top:65px;">
          <img class="obj-img" src="table.png" alt="table" onerror="this.onerror=null;this.style.display='none'">
          <button class="state-btn">T10</button>
        </div>

        <!-- Vertical stack T11..T17 on right (vertical as requested) -->
        <!-- TABLE STACK (Move this anywhere you want on the map) -->
<div class="table-stack" style="position: absolute; right: 260px; bottom:-4px;">
  <div class="map-object table small" data-id="T11" data-type="table" data-occupied="false">
    <img class="obj-img" src="table.png" alt="t11">
    <button class="state-btn">T11</button>
  </div>
  <div class="map-object table small" data-id="T12" data-type="table" data-occupied="false">
    <img class="obj-img" src="table.png" alt="t12">
    <button class="state-btn">T12</button>
  </div>
  <div class="map-object table small" data-id="T13" data-type="table" data-occupied="false">
    <img class="obj-img" src="table.png" alt="t13">
    <button class="state-btn">T13</button>
  </div>
  <div class="map-object table small" data-id="T14" data-type="table" data-occupied="false">
    <img class="obj-img" src="table.png" alt="t14">
    <button class="state-btn">T14</button>
  </div>
  <div class="map-object table small" data-id="T15" data-type="table" data-occupied="false">
    <img class="obj-img" src="table.png" alt="t15">
    <button class="state-btn">T15</button>
  </div>
  <div class="map-object table small" data-id="T16" data-type="table" data-occupied="false">
    <img class="obj-img" src="table.png" alt="t16">
    <button class="state-btn">T16</button>
  </div>
  <div class="map-object table small" data-id="T17" data-type="table" data-occupied="false">
    <img class="obj-img" src="table.png" alt="t17">
    <button class="state-btn">T17</button>
  </div>
</div>



        <!-- POOL / STAGE bottom-right -->
        <div class="map-stage-pool">
          <div class="pool top">Pool</div>
          <div class="stage">Stage</div>
          <div class="pool bottom">Pool</div>
        </div>
      </div>
    </section>

    <!-- RIGHT SIDE PANEL -->
    <aside class="side-panel">
      <div class="side-card">
        <div class="side-top-controls">
          <button id="btnAdd" class="icon-btn" title="Clear All">＋</button>
          <button id="btnDraft" class="icon-btn" title="Save Draft"><img src="draft.png" alt="Draft" onerror="this.onerror=null;this.style.display='none'"></button>
          <button id="btnRefresh" class="icon-btn" title="Refresh"><img src="refresh.png" alt="Refresh" onerror="this.onerror=null;this.style.display='none'"></button>
        </div>

        <!-- Occupied list (white tab) -->
        <div class="selected-list" id="selectedList" aria-live="polite">
          <!-- filled dynamically -->
        </div>

        <div class="side-actions">
          <form id="proceedForm" method="post" action="tables.php">
            <input type="hidden" name="payload" id="payload" value="">
              <button type="button" id="proceedBtn" class="proceed">Proceed</button>
              <button type="button" id="enterBtn" class="enter">Enter</button>
          </form>
        </div>
      </div>
    </aside>
  </main>

  <!-- Modal -->
  <div class="modal" id="modal" aria-hidden="true">
    <div class="modal-backdrop" id="modalBackdrop"></div>
    <div class="modal-content" role="dialog" aria-modal="true">
      <div class="modal-header">
        <h3 id="modalTitle">Details</h3>
        <button id="modalClose" class="modal-close">×</button>
      </div>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>