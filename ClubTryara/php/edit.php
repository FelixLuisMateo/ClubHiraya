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

// Get item ID
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($item_id <= 0) {
    $_SESSION['error'] = "Invalid item ID";
    header("Location: inventory.php");
    exit();
}

// Fetch item details
try {
    $stmt = $pdo->prepare("SELECT * FROM foods WHERE id = :id");
    $stmt->execute([':id' => $item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        $_SESSION['error'] = "Item not found";
        header("Location: inventory.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error fetching item: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = ($_POST['name']);
    $price = ($_POST['price']);
    $category = ($_POST['category']);
    $image = ($_POST['image']);
    $stock = ($_POST['stock']);
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required";
    }

    if (empty($image)) {
        $errors[] = "Category is required";
    }
    
    if ($stock < 0) {
        $errors[] = "Stock cannot be negative";
    }
    
    if (empty($errors)) {
        try {
            $sql = "UPDATE foods SET name = :name, price = :price, category = :category, stock = :stock, image = :image WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':price' => $price,
                ':category' => $category,
                ':stock' => $stock,
                ':image' => $image,
                ':id' => $item_id
            ]);
            
            $_SESSION['success'] = "Item updated successfully!";
            header("Location: inventory.php");
            exit();
        } catch(PDOException $e) {
            $errors[] = "Error updating item: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 500px;
            width: 100%;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        h1 {
            margin-bottom: 30px;
            color: #333;
            font-size: 28px;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }
        
        input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }
        
        input:focus {
            outline: none;
            border-color: #10b981;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .error-messages {
            background-color: #fee;
            border-left: 4px solid #ef4444;
            color: #c33;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        
        .error-messages ul {
            list-style: none;
        }
        
        .error-messages li {
            margin-bottom: 5px;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 35px;
        }
        
        button, .btn-cancel {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-save {
            background-color: #10b981;
            color: white;
        }
        
        .btn-save:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-cancel {
            background-color: #6b7280;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #4b5563;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }

        .btn-save:active, .btn-cancel:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Item</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($item['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : htmlspecialchars($item['price']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : htmlspecialchars($item['category']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="stock">Stock</label>
                <input type="number" id="stock" name="stock" min="0" value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : htmlspecialchars($item['stock']); ?>" required>
            </div>

            <div class="form-group">
                <label for="image">Image</label>
                <input type="text" id="image" name="image" value="<?php echo isset($_POST['image']) ? htmlspecialchars($_POST['image']) : htmlspecialchars($item['image']); ?>" required>
            </div>

            <div class="button-group">
                <button type="submit" class="btn-save">Save</button>
                <a href="inventory.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>