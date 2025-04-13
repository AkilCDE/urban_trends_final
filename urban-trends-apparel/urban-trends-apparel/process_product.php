<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');

// Create database connection
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];
$quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
$variation_id = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;

try {
    // Check product and variation stock
    if ($variation_id) {
        $stmt = $db->prepare("SELECT v.stock, p.stock as product_stock 
                             FROM product_variations v
                             JOIN products p ON v.product_id = p.product_id
                             WHERE v.variation_id = ?");
        $stmt->execute([$variation_id]);
        $stock = $stmt->fetch();
        
        if (!$stock || $stock['stock'] <= 0) {
            $_SESSION['error_message'] = "This product variation is out of stock";
            header("Location: shop.php");
            exit;
        }
        
        if ($quantity > $stock['stock']) {
            $_SESSION['error_message'] = "Only {$stock['stock']} items available in stock for this size";
            header("Location: shop.php");
            exit;
        }
    } else {
        $stmt = $db->prepare("SELECT stock FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product || $product['stock'] <= 0) {
            $_SESSION['error_message'] = "This product is out of stock";
            header("Location: shop.php");
            exit;
        }
        
        if ($quantity > $product['stock']) {
            $_SESSION['error_message'] = "Only {$product['stock']} items available in stock";
            header("Location: shop.php");
            exit;
        }
    }

    if (isset($_POST['buy_now'])) {
        // Clear current cart
        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Add the selected product to cart
        $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity, variation_id) 
                             VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $product_id, $quantity, $variation_id]);
        
        header("Location: checkout.php");
        exit;
    }
    
    if (isset($_POST['add_to_cart'])) {
        // Check if product (with same variation) already in cart
        $stmt = $db->prepare("SELECT * FROM cart 
                             WHERE user_id = ? AND product_id = ? 
                             AND (variation_id = ? OR (? IS NULL AND variation_id IS NULL))");
        $stmt->execute([$user_id, $product_id, $variation_id, $variation_id]);
        $existing_item = $stmt->fetch();
        
        if ($existing_item) {
            // Update quantity if already in cart
            $new_quantity = $existing_item['quantity'] + $quantity;
            
            // Check stock again with new quantity
            $max_stock = $variation_id ? $stock['stock'] : $product['stock'];
            if ($new_quantity > $max_stock) {
                $_SESSION['error_message'] = "You can't add more than available stock";
                header("Location: shop.php");
                exit;
            }
            
            $stmt = $db->prepare("UPDATE cart SET quantity = quantity + ? 
                                 WHERE cart_id = ?");
            $stmt->execute([$quantity, $existing_item['cart_id']]);
        } else {
            // Add new item to cart
            $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity, variation_id) 
                                 VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $product_id, $quantity, $variation_id]);
        }
        
        $_SESSION['success_message'] = "Product added to cart successfully!";
        header("Location: shop.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error processing your request: " . $e->getMessage();
    header("Location: shop.php");
    exit;
}