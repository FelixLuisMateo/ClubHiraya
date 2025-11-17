<?php
session_start();
include "db_connect.php"; // expects $conn (mysqli)

// ---------- FEEDBACK & CSRF ----------
$feedback = '';
$errors = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// ---------- FORM SUBMISSION ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $errors[] = "Invalid form submission.";
    }

    $name = trim($_POST['name'] ?? '');

    // Validate
    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 255) {
        $errors[] = "Category name must be 2–255 characters.";
    }

    if (empty($errors)) {
        // Prevent duplicate category
        $check_sql = "SELECT id FROM menu_category WHERE category = ?";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, 's', $name);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Category '$name' already exists.";
        } else {
            // Insert category
            $insert_sql = "INSERT INTO menu_category (category) VALUES (?)";
            $insert = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert, 's', $name);

            if (mysqli_stmt_execute($insert)) {
                $_SESSION['success'] = "New category added successfully!";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
                header("Location: create.php"); // back to item creation
                exit();
            } else {
                $errors[] = "Database error: " . mysqli_error($conn);
            }
        }

        mysqli_stmt_close($stmt);
        if (isset($insert)) mysqli_stmt_close($insert);
    }

    // Format errors
    if (!empty($errors)) {
        $feedback = "<div class='alert alert-error'><ul style='margin:0;padding-left:18px;'>";
        foreach ($errors as $e) $feedback .= "<li>" . htmlspecialchars($e) . "</li>";
        $feedback .= "</ul></div>";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Create New Category</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link rel="stylesheet" href="../css/inventory.css">
</head>

<body
<?php
  if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode']) echo ' class="dark-mode"';

  // Accent color logic
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

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="../assets/logos/logo1.png" class="sidebar-header-img">
    </div>

    <nav class="sidebar-menu" role="navigation" aria-label="Main menu">
          <a href="../admin_dashboard.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/home.png" alt="Home"></span><span>Home</span></a>
          <a href="../tables/tables.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/cabin.png" alt="Tables"></span><span>Cabins</span></a>
          <a href="inventory.php" class="sidebar-btn active"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/inventory.png" alt="Inventory"></span><span>Inventory</span></a>
          <a href="../SalesReport/sales_report.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../../clubtryara/assets/logos/sales.png" alt="Sales"></span><span>Sales Report</span></a>
          <a href="../Settings/settings.php" class="sidebar-btn"><span class="sidebar-icon"><img src="../assets/logos/setting.png" alt="Settings"></span><span>Settings</span></a>
      </nav>

    <button class="sidebar-logout">Logout</button>
</aside>

<main class="main-content">
<div class="inventory-container">
  <div class="form-card" style="max-width:600px;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <div>
        <div style="font-size:20px;font-weight:800;">Create New Category</div>
        <div style="color:#666;margin-top:6px;">Add a new category for your menu items</div>
      </div>

      <div>
        <a href="create.php" class="btn-cancel" 
           style="padding:10px 14px;display:inline-block;">
           Back
        </a>
      </div>
    </div>

    <?php if ($feedback) echo $feedback; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="form-group">
            <label for="name">Category Name</label>
            <input id="name" name="name" type="text" required 
                   placeholder="e.g. Desserts"
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
        </div>

        <div class="form-actions">
            <a href="create.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-save">Save Category</button>
        </div>
    </form>

  </div>
</div>
</main>

</body>
</html>
