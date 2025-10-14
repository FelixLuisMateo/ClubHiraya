<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Tryara</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- ✅ Load the fixed app.js file -->
    <script defer src="js/app.js"></script>
</head>
<body>
    <!-- ✅ Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="assets/logo1.png" alt="Club Hiraya Logo" class="sidebar-header-img">
        </div>

        <nav class="sidebar-menu">
            <a href="index.php" class="sidebar-btn active">
                <span class="sidebar-icon"><img src="assets/home.png" alt=""></span>
                <span>Home</span>
            </a>
            <a href="tables.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/table.png" alt=""></span>
                <span>Tables</span>
            </a>
            <a href="inventory.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/inventory.png" alt=""></span>
                <span>Inventory</span>
            </a>
            <a href="sales_report.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/sales.png" alt=""></span>
                <span>Sales Report</span>
            </a>
            <a href="settings.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/setting.png" alt=""></span>
                <span>Settings</span>
            </a>
        </nav>

        <div style="flex:1"></div>
        <button class="sidebar-logout">
            <span>Logout</span>
        </button>
    </div>

    <!-- ✅ Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="search-section">
                <input type="text" class="search-input" placeholder="Search products" id="searchBox">
                <span class="search-icon">&#128269;</span>
            </div>
            <button class="select-table-btn">
                Select Table <span class="table-icon"><img src="assets/table.png"></span>
            </button>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- ✅ Products Section -->
            <div class="products-section">
                <div class="category-tabs" id="categoryTabs">
                    <button class="category-btn active" data-category="Main Course">Main Course</button>
                    <button class="category-btn" data-category="Seafood Platter">Seafood Platter</button>
                    <button class="category-btn" data-category="Appetizer">Appetizer</button>
                    <button class="category-btn" data-category="Side Dish">Side Dish</button>
                    <button class="category-btn" data-category="Drinks">Drinks</button>
                </div>

                <div class="foods-grid" id="foodsGrid">
                    <!-- ✅ Foods will be loaded dynamically from MySQL by app.js -->
                </div>
            </div>

            <!-- ✅ Order Section -->
            <div class="order-section">
                <div class="order-actions">
                    <button class="order-action-btn plus" id="newOrderBtn">+</button>
                    <button class="order-action-btn draft" id="draftBtn"><img src="assets/draft.png"></button>
                    <button class="order-action-btn refresh" id="refreshBtn"><img src="assets/reset.png"></button>
                </div>

                <div class="order-list" id="orderList"></div>
                <div class="order-compute" id="orderCompute"></div>

                <div class="order-buttons">
                    <button class="hold-btn" id="billOutBtn">Bill Out</button>
                    <button class="proceed-btn">Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ Draft Modal -->
    <div class="modal hidden" id="draftModal">
        <div class="modal-content">
            <span class="close-btn" id="closeDraftModal">&times;</span>
            <h3>Save Current Order to Draft</h3>
            <input type="text" id="draftNameInput" placeholder="Draft name or note..." style="width:95%;margin-bottom:12px;">
            <button id="saveDraftBtn" style="padding:6px 24px;font-size:16px;background:#d51ecb;color:#fff;border:none;border-radius:7px;">Save Draft</button>
        </div>
    </div>
</body>
</html>
