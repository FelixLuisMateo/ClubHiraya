<?php
session_start();

// Use existing db_connect.php in the same folder which should create $conn (mysqli)
$dbConnectPath = __DIR__ . '/db_connect.php';
if (!file_exists($dbConnectPath)) {
    die("Missing db_connect.php in php/ folder. Please add it or restore it.");
}
require_once $dbConnectPath;

// Ensure $conn is available (mysqli)
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("db_connect.php must define a valid \$conn (mysqli) connection.");
}

// Handle search safely
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch items with search (using prepared statements)
$items = [];
if ($search !== '') {
    $like = "%{$search}%";
    $stmt = $conn->prepare("SELECT * FROM foods WHERE name LIKE ? OR category LIKE ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $items = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }
        $stmt->close();
    } else {
        // fallback: query without prepared statement (shouldn't happen)
        $escaped = $conn->real_escape_string($search);
        $res = $conn->query("SELECT * FROM foods WHERE name LIKE '%$escaped%' OR category LIKE '%$escaped%' ORDER BY id ASC");
        if ($res) $items = $res->fetch_all(MYSQLI_ASSOC);
    }
} else {
    $res = $conn->query("SELECT * FROM foods ORDER BY id ASC");
    if ($res) $items = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Inventory - Club Hiraya</title>
  <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
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
          <a href="../php/tables.php" class="sidebar-btn">
              <span class="sidebar-icon"><img src="../assets/table.png" alt="Tables icon"></span>
              <span>Tables</span>
          </a>
          <a href="inventory.php" class="sidebar-btn active">
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

  <!-- Main Content -->
  <main class="main-content" role="main" aria-label="Main content">
      <!-- Top Bar -->
      <div class="topbar">
          <div class="search-section">
              <form class="search-container" method="GET" action="">
                  <input type="text" name="search" class="search-input" placeholder="Search products" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>">
              </form>
          </div>
      </div>

      <!-- Inventory Container -->
      <div class="inventory-container" id="inventoryContainer">
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
              <div class="col-image" aria-hidden="true">Image</div>
              <div class="header-actions">
                <!-- Toggle button to show/hide Image column -->
                <button id="toggleImageBtn" class="btn-toggle" type="button" aria-pressed="false">Show File Name</button>
              </div>

              <a href="create.php" class="add-btn" title="Add New Item">
                  Add New
              </a>
          </div>

          <!-- Table Rows -->
          <?php if (empty($items)): ?>
              <div class="empty-state">
                  <?php if ($search !== ''): ?>
                      No items found matching "<?php echo htmlspecialchars($search, ENT_QUOTES); ?>"
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

                      <!-- Image filename cell (hidden by default) -->
                      <div class="col-image"><?php echo htmlspecialchars($item['image']); ?></div>

                      <div class="action-buttons">
                          <a href="edit.php?id=<?php echo urlencode($item['id']); ?>" class="btn-edit">Edit</a>
                          <button onclick="confirmDelete(<?php echo htmlspecialchars($item['id']); ?>)" class="btn-delete">Delete</button>
                      </div>
                  </div>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>
  </main>

  <script>
    // Toggle the Image column visibility
    (function() {
      const toggleBtn = document.getElementById('toggleImageBtn');
      const container = document.getElementById('inventoryContainer');

      function updateButton(isShown) {
        toggleBtn.textContent = isShown ? 'Hide File Name' : 'Show File Name';
        toggleBtn.setAttribute('aria-pressed', isShown ? 'true' : 'false');
      }

      // initialize from localStorage
      const saved = localStorage.getItem('inventory_show_images');
      if (saved === '1') {
        container.classList.add('show-images');
        updateButton(true);
      } else {
        updateButton(false);
      }

      toggleBtn.addEventListener('click', function() {
        const isShown = container.classList.toggle('show-images');
        updateButton(isShown);
        localStorage.setItem('inventory_show_images', isShown ? '1' : '0');
      });

      window.confirmDelete = function(id) {
        if (confirm('Are you sure you want to delete this item?')) {
          window.location.href = 'delete.php?id=' + encodeURIComponent(id);
        }
      };
    })();
  </script>
</body>
</html>