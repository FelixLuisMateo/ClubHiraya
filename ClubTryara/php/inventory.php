<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'restaurant';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch items with search
try {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM foods WHERE name LIKE :search OR category LIKE :search ORDER BY id ASC");
        $stmt->execute([':search' => "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT * FROM foods ORDER BY id ASC");
    }
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching items: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Club Hiraya</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        background: #f4f4f6;
        }

        /* Sidebar */
        .sidebar {
        position: fixed;
        left: 0; top: 0; bottom: 0;
        width: 150px;
        background: linear-gradient(to bottom, #e44ac2, #a00080);
        box-shadow: 2px 0 10px #0002;
        display: flex;
        flex-direction: column;
        align-items: center;
        z-index: 100;
        }
        .sidebar-header {
        width: 100%;
        padding: 16px 0;
        display: flex;
        justify-content: center;
        align-items: center;
        }
        .sidebar-header-img {
        width: 120px; /* logo size */
        height: auto;
        object-fit: contain;
        }

        /* Sidebar brand and menu */
        .sidebar-logo {
        width: 100%;
        padding: 14px 0 4px 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        font-family: Abhaya Libre SemiBold;
        }
        .logo-img { width: 38px; height: 38px; border-radius: 10px; margin-bottom: 4px; }
        .sidebar-brand {
        text-align: center;
        font-size: 10px;
        font-weight: bold;
        color: #111;
        margin-bottom: 8px;
        line-height: 1.1;
        }
        .sidebar-menu {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 0;
        margin-top: 10px;
        flex: 1;
        }
        .sidebar-btn {
        width: 115px;
        height: 80px;
        margin: 0 auto 22px auto;
        background: #fff;
        color: #111;
        border: 5px solid #000000;
        border-radius: 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.2s, color 0.2s, border 0.2s;
        box-sizing: border-box;
        position: relative;
        gap: 0;
        padding: 0;
        }
        .sidebar-btn.active {
        background: #001B89;
        color: #fff;
        border: 7px solid #000;
        }
        .sidebar-btn .sidebar-icon { margin-bottom: 3px; }
        .sidebar-btn .sidebar-icon img { width: 26px; height: 26px; display: block; margin: 0 auto; filter: none; }
        .sidebar-btn span:last-child {
        font-size: 18px;
        margin-top: 2px;
        display: block;
        text-align: center;
        width: 100%;
        font-family: inter;
        }
        .sidebar-logout {
        width: 120px;
        height: 49px;
        margin: 36px auto 18px auto;
        background: #0066ff;
        color: #fff;
        border: none;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: bold;
        cursor: pointer;
        box-shadow: 0 1px 6px #2221;
        transition: background 0.2s;
        text-align: center;
        gap: 2px;
        }
        .sidebar-logout span:last-child {
        font-size: 20px;
        display: block;
        width: 100%;
        text-align: center;
        margin-top: 2px;
        }
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .topbar {
        height: 52px;
        background: #222;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 36px 0 158px;
        box-shadow: 0 4px 16px #0001;
        }
        .search-section {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }
        .search-input {
            width: 270px;
            height: 30px;
            border-radius: 15px;
            border: none;
            font-size: 14px;
            padding: 0 32px 0 13px;
            outline: none;
            background: #fff;
            box-sizing: border-box;
        }
        .search-icon {
            position: absolute;
            right: 10px;
            top: 6px;
            font-size: 15px;
            color: #555;
            pointer-events: none;
        }
        .select-table-btn {
            background: #1b6cf2;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            padding: 7px 18px 7px 8px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
            margin-left: 17px;
            position: relative;
        }
        .select-table-btn:hover { background: #0053c2; }
        .table-icon img { width: 15px; margin-left: 7px; position: relative; top: 1px; }
        
        /* Inventory Content */
        .inventory-container {
            flex: 1;
            background: #a8a8a8;
            margin: 20px 20px 20px 160px;
            border-radius: 20px;
            padding: 20px;
            overflow-y: auto;
        }
        
        /* Table Header */
        .table-header {
            background: white;
            border-radius: 15px;
            padding: 20px 30px;
            display: grid;
            grid-template-columns: 80px 2fr 1fr 1fr 1fr 280px;
            gap: 20px;
            align-items: center;
            margin-bottom: 15px;
            font-weight: 600;
            font-style: italic;
            position: relative;
        }
        
        .add-btn {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #000000ff;
            transition: color 0.3s;
        }
        
        .add-btn:hover {
            color: #10b981;
        }
        
        /* Table Row */
        .table-row {
            background: #d4d4d4;
            border-radius: 15px;
            padding: 20px 30px;
            display: grid;
            grid-template-columns: 80px 2fr 1fr 1fr 1fr 280px;
            gap: 20px;
            align-items: center;
            margin-bottom: 10px;
            transition: background 0.3s;
        }
        
        .table-row:hover {
            background: #c4c4c4;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-edit {
            background: #fde047;
            color: #000;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit:hover {
            background: #facc15;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-delete:hover {
            background: #dc2626;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-size: 18px;
        }
        
        /* Custom Scrollbar */
        .inventory-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .inventory-container::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .inventory-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .inventory-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }
    </style>
</head>
<body>
        <!-- Sidebar -->
    <aside class="sidebar" role="complementary" aria-label="Sidebar">
        <div class="sidebar-header">
            <img src="assets/logo1.png" alt="Club Hiraya logo" class="sidebar-header-img">
        </div>

        <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
            <a href="index.php" class="sidebar-btn active" aria-current="page">
                <span class="sidebar-icon"><img src="assets/home.png" alt="Home icon"></span>
                <span>Home</span>
            </a>
            <a href="tables.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/table.png" alt="Tables icon"></span>
                <span>Tables</span>
            </a>
            <a href="php/inventory.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/inventory.png" alt="Inventory icon"></span>
                <span>Inventory</span>
            </a>
            <a href="sales_report.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/sales.png" alt="Sales report icon"></span>
                <span>Sales Report</span>
            </a>
            <a href="settings.php" class="sidebar-btn">
                <span class="sidebar-icon"><img src="assets/setting.png" alt="Settings icon"></span>
                <span>Settings</span>
            </a>
        </nav>

        <div style="flex:1" aria-hidden="true"></div>

        <button class="sidebar-logout" type="button" aria-label="Logout">
            <span>Logout</span>
        </button>
    </aside>

    <!-- Main Content -->
    <main class="main-content" role="main" aria-label="Main content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="search-section">
                <form class="search-container" method="GET" action="">
                <input type="text" name="search" class="search-input" placeholder="Search" value="<?php echo htmlspecialchars($search); ?>">
            </form>
            </div>
            <button class="select-table-btn" type="button" aria-haspopup="dialog">
                Select Table <span class="table-icon"><img src="ClubHiraya/ClubTryara/assets/table.png" alt="Table icon"></span>
            </button>
        </div>
        
        <!-- Inventory Container -->
        <div class="inventory-container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Table Header -->
            <div class="table-header">
                <div>ID</div>
                <div>Name</div>
                <div>Price</div>
                <div>Category</div>
                <div>Stock</div>
                <div></div>
                <a href="add.php" class="add-btn" title="Add New Item">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            
            <!-- Table Rows -->
            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <?php if ($search !== ''): ?>
                        No items found matching "<?php echo htmlspecialchars($search); ?>"
                    <?php else: ?>
                        No items in inventory. Click the + button to add items.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="table-row">
                        <div><?php echo htmlspecialchars($item['id']); ?></div>
                        <div><?php echo htmlspecialchars($item['name']); ?></div>
                        <div>₱<?php echo number_format($item['price'], 2); ?></div>
                        <div><?php echo htmlspecialchars($item['category']); ?></div>
                        <div><?php echo htmlspecialchars($item['stock']); ?></div>
                        <div class="action-buttons">
                            <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn-edit">Edit</a>
                            <button onclick="confirmDelete(<?php echo $item['id']; ?>)" class="btn-delete">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this item?')) {
                window.location.href = 'delete.php?id=' + id;
            }
        }
    </script>
</body>
</html>