<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS Club Hiraya </title>
    <link rel="stylesheet" href="css/style.css">
    <script defer src="js/app.js"></script>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/clublogo.png" alt="Club Hiraya Logo" class="logo-img">
            <div class="sidebar-brand">
                <span class="sidebar-brand-club">CLUB</span><br>
                <span class="sidebar-brand-hiraya">HIRAYA</span>
            </div>
        </div>
        <nav class="sidebar-menu">
            <button class="sidebar-btn active">
                <span class="sidebar-icon"><img src="assets/home.svg"></span>
                <span>Home</span>
            </button>
            <button class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/table.svg"></span>
                <span>Tables</span>
            </button>
            <button class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/inventory.svg"></span>
                <span>Inventory</span>
            </button>
            <button class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/report.svg"></span>
                <span>Sales Report</span>
            </button>
            <button class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/settings.svg"></span>
                <span>Settings</span>
            </button>
        </nav>
        <div style="flex:1"></div>
        <button class="sidebar-logout">
            <span>Logout</span>
        </button>
    </div>
    <div class="main-content">
        <div class="topbar">
            <div class="search-section">
                <input type="text" class="search-input" placeholder="Search products" id="searchBox">
                <span class="search-icon">&#128269;</span>
            </div>
            <button class="select-table-btn">
                Select Table <span class="table-icon"><img src="assets/table-btn.svg"></span>
            </button>
        </div>
        <div class="content-area">
            <div class="products-section">
                <div class="category-tabs" id="categoryTabs">
                    <button class="category-btn active" data-category="Main Course">Main Course</button>
                    <button class="category-btn" data-category="Seafood Platter">Seafood Platter</button>
                    <button class="category-btn" data-category="Appetizer">Appetizer</button>
                    <button class="category-btn" data-category="Side dish">Side dish</button>
                    <button class="category-btn" data-category="Drinks">Drinks</button>
                    <!-- Add more categories as needed, will scroll horizontally -->
                </div>
                <div class="foods-grid" id="foodsGrid">
                    <!-- Foods will be loaded by JS -->
                </div>
            </div>
            <div class="order-section">
                <div class="order-actions">
                    <button class="order-action-btn plus" id="newOrderBtn">+</button>
                    <button class="order-action-btn draft" id="draftBtn"><img src="assets/draft.svg"></button>
                    <button class="order-action-btn refresh" id="refreshBtn"><img src="assets/refresh.svg"></button>
                </div>
                <div class="order-list" id="orderList"></div>
                <div class="order-compute" id="orderCompute"></div>
                <div class="order-buttons">
                    <button class="hold-btn">Hold Order</button>
                    <button class="proceed-btn">Proceed</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Draft Modal -->
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