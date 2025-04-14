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

// Start session
session_start();

class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
    
    public function logout() {
        session_unset();
        session_destroy();
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'firstname' => $_SESSION['user_firstname'],
                'lastname' => $_SESSION['user_lastname'],
                'address' => $_SESSION['user_address']
            ];
        }
        return null;
    }
}

$auth = new Auth($db);

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: login.php");
    exit;
}

// Handle Add to Cart and Buy Now actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart']) || isset($_POST['buy_now'])) {
        $product_id = $_POST['product_id'];
        $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 0;
        $variation_id = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;
        
        if ($quantity <= 0) {
            $_SESSION['error_message'] = "Please select a valid quantity";
            header("Location: shop.php" . (isset($_GET['category']) ? '?category='.$_GET['category'] : ''));
            exit;
        }

        try {
            // Check if product has variations
            $stmt = $db->prepare("SELECT COUNT(*) FROM product_variations WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $has_variations = $stmt->fetchColumn() > 0;

            if ($has_variations && !$variation_id) {
                $_SESSION['error_message'] = "Please select a size";
                header("Location: shop.php" . (isset($_GET['category']) ? '?category='.$_GET['category'] : ''));
                exit;
            }

            if ($variation_id) {
                // Check variation stock
                $stmt = $db->prepare("SELECT stock FROM product_variations WHERE variation_id = ?");
                $stmt->execute([$variation_id]);
                $variation = $stmt->fetch();
                
                if (!$variation || $variation['stock'] <= 0) {
                    $_SESSION['error_message'] = "This product variation is out of stock";
                    header("Location: shop.php" . (isset($_GET['category']) ? '?category='.$_GET['category'] : ''));
                    exit;
                }
                
                if ($quantity > $variation['stock']) {
                    $_SESSION['error_message'] = "Only {$variation['stock']} items available in stock for this size";
                    header("Location: shop.php" . (isset($_GET['category']) ? '?category='.$_GET['category'] : ''));
                    exit;
                }
            } else {
                // Check product stock (if no variations)
                $stmt = $db->prepare("SELECT SUM(stock) as total_stock FROM product_variations WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $stock = $stmt->fetch();
                
                if (!$stock || $stock['total_stock'] <= 0) {
                    $_SESSION['error_message'] = "This product is out of stock";
                    header("Location: shop.php" . (isset($_GET['category']) ? '?category='.$_GET['category'] : ''));
                    exit;
                }
            }
            
            if (isset($_POST['add_to_cart'])) {
                // Check if product already in cart with same variation
                $stmt = $db->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ? AND variation_id " . 
                                    ($variation_id ? "= ?" : "IS NULL"));
                $params = [$_SESSION['user_id'], $product_id];
                if ($variation_id) $params[] = $variation_id;
                $stmt->execute($params);
                $existing_item = $stmt->fetch();
                
                if ($existing_item) {
                    // Update quantity if already in cart
                    $new_quantity = $existing_item['quantity'] + $quantity;
                    $max_stock = $variation_id ? $variation['stock'] : $stock['total_stock'];
                    
                    if ($new_quantity > $max_stock) {
                        $_SESSION['error_message'] = "You can't add more than available stock";
                        header("Location: shop.php" . (isset($_GET['category']) ? '?category='.$_GET['category'] : ''));
                        exit;
                    }
                    
                    $stmt = $db->prepare("UPDATE cart SET quantity = quantity + ? WHERE cart_id = ?");
                    $stmt->execute([$quantity, $existing_item['cart_id']]);
                } else {
                    // Add new item to cart
                    $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, variation_id, quantity) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $product_id, $variation_id, $quantity]);
                }
                
                $_SESSION['success_message'] = "Product added to cart successfully!";
            }
            
            if (isset($_POST['buy_now'])) {
                // Clear current cart (optional, depends on your business logic)
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                // Add the selected product to cart
                $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, variation_id, quantity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $product_id, $variation_id, $quantity]);
                
                header("Location: checkout.php");
                exit;
            }
            
            header("Location: shop.php" . (isset($_GET['category']) ? '?category='.$_GET['category'] : ''));
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error processing your request: " . $e->getMessage();
            header("Location: shop.php" . (isset($_GET['category']) ? '?category='.$_GET['category'] : ''));
            exit;
        }
    }

    // Handle Wishlist Actions
    if (isset($_POST['wishlist_action'])) {
        $product_id = $_POST['product_id'];
        $action = $_POST['wishlist_action'];
        
        if ($action === 'add') {
            try {
                // Check if product is already in wishlist
                $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$_SESSION['user_id'], $product_id]);
                $exists = $stmt->fetchColumn();
                
                if (!$exists) {
                    $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $product_id]);
                    $_SESSION['success_message'] = "Product added to wishlist!";
                } else {
                    $_SESSION['info_message'] = "Product is already in your wishlist";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error adding to wishlist: " . $e->getMessage();
            }
        } elseif ($action === 'remove') {
            try {
                $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$_SESSION['user_id'], $product_id]);
                $_SESSION['success_message'] = "Product removed from wishlist!";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error removing from wishlist: " . $e->getMessage();
            }
        }
        
        // Redirect back to the same page with category parameter if exists
        $redirect_url = 'shop.php';
        if (isset($_GET['category'])) {
            $redirect_url .= '?category=' . $_GET['category'];
        }
        header("Location: $redirect_url");
        exit;
    }
}

// Get products with category filtering and stock check
$category = isset($_GET['category']) ? $_GET['category'] : null;

$category_mapping = [
    'men' => 'men_tshirts',
    'women' => 'women', 
    'shoes' => 'shoes',
    'accessories' => 'access' 
];

$db_category = isset($category_mapping[$category]) ? $category_mapping[$category] : null;

// Get products with available stock (either in variations or base product)
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM product_variations pv WHERE pv.product_id = p.product_id) as has_variations,
          (SELECT SUM(stock) FROM product_variations pv WHERE pv.product_id = p.product_id) as total_stock
          FROM products p";

$params = [];

if ($db_category) {
    if ($db_category === 'women') {
        $query .= " WHERE p.category LIKE 'women%'";
    } elseif ($db_category === 'access') {
        $query .= " WHERE p.category LIKE 'access%'";
    } else {
        $query .= " WHERE p.category = ?";
        $params[] = $db_category;
    }
} else {
    $query .= " WHERE 1=1";
}

// Only show products with available stock
$query .= " AND ((SELECT SUM(stock) FROM product_variations pv WHERE pv.product_id = p.product_id) > 0 
          OR (SELECT COUNT(*) FROM product_variations pv WHERE pv.product_id = p.product_id) = 0)";

$query .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get variations for each product
foreach ($products as &$product) {
    $stmt = $db->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY size");
    $stmt->execute([$product['product_id']]);
    $product['variations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($product);

// Get cart count
$cart_count = 0;
if ($auth->isLoggedIn()) {
    $stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_count = $stmt->fetchColumn() ?: 0;
}

// Check if product is in wishlist
function isInWishlist($db, $user_id, $product_id) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    return $stmt->fetchColumn() > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Trends Apparel - Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --secondary-color: #121212;
            --accent-color: #ff6b6b;
            --light-color: #f8f9fa;
            --dark-color: #0d0d0d;
            --text-color: #e0e0e0;
            --text-muted: #b0b0b0;
            --success-color: #4bb543;
            --error-color: #ff3333;
            --warning-color: #ffcc00;
            --info-color: #007bff;
            --border-radius: 8px;
            --box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Header Styles */
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 2rem;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo a {
            color: white;
            text-decoration: none;
        }

        .logo i {
            color: var(--accent-color);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background-color: var(--accent-color);
            transition: var(--transition);
        }

        nav a:hover::after {
            width: 70%;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-actions a {
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            transition: var(--transition);
        }

        .user-actions a:hover {
            color: var(--accent-color);
            transform: translateY(-2px);
        }

        .cart-count {
            position: relative;
        }

        .cart-count span {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }

        /* Shop Content */
        .shop-header {
            text-align: center;
            padding: 4rem 0 2rem;
            position: relative;
        }

        .shop-header h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--text-color);
            position: relative;
            display: inline-block;
        }

        .shop-header h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background-color: var(--accent-color);
        }

        .search-filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-bar {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 3rem;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid #444;
            border-radius: var(--border-radius);
            font-size: 1rem;
            color: var(--text-color);
            transition: var(--transition);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }

        .search-bar i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .filter-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.8rem 1.5rem;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .filter-btn:hover {
            background-color: #ff5252;
            transform: translateY(-2px);
        }

        .categories {
            display: flex;
 to justify-content: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .category-btn {
            padding: 0.6rem 1.2rem;
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .category-btn:hover, .category-btn.active {
            background-color: var(--accent-color);
            color: white;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            padding: 1rem 0 3rem;
        }

        .product-card {
            background-color: var(--primary-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background-color: var(--accent-color);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            z-index: 2;
        }

        .out-of-stock-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }
        
        .out-of-stock-overlay span {
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            background-color: var(--error-color);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
        }

        .product-image-container {
            height: 300px;
            position: relative;
            overflow: hidden;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-name {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .product-actions {
            display: flex;
            gap: 0.8rem;
        }

        .action-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.8rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .buy-now {
            background-color: var(--accent-color);
            color: white;
        }

        .buy-now:hover {
            background-color: #ff5252;
        }

        .add-to-cart {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
        }

        .add-to-cart:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .wishlist-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            color: var(--text-muted);
            transition: var(--transition);
            font-size: 1.1rem;
        }

        .wishlist-btn:hover, .wishlist-btn.active {
            color: var(--accent-color);
            background-color: rgba(255, 107, 107, 0.1);
        }

        button[disabled] {
            opacity: 0.6;
            cursor: not-allowed;
            background-color: #666 !important;
        }

        /* Size selector styles */
        .size-selector {
            margin: 10px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .size-button {
            padding: 8px 12px;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid #444;
            border-radius: 4px;
            cursor: pointer;
            color: var(--text-color);
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .size-button:hover, .size-button.selected {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }

        .size-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Quantity selector styles */
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0;
        }

        .quantity-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: var(--text-color);
            transition: var(--transition);
        }

        .quantity-btn:hover {
            background-color: var(--accent-color);
            color: white;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 8px;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid #444;
            border-radius: 4px;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 3rem 0;
            margin-top: 3rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-column h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            color: var(--accent-color);
        }

        .footer-column h3::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent-color);
        }

        .footer-column p {
            margin-bottom: 1rem;
            color: var(--text-muted);
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column li {
            margin-bottom: 0.8rem;
        }

        .footer-column a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-column a:hover {
            color: white;
            transform: translateX(5px);
        }

        .footer-column a i {
            width: 20px;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            transition: var(--transition);
        }

        .social-links a:hover {
            background-color: var(--accent-color);
            transform: translateY(-3px);
        }

        .copyright {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Alert messages */
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: var(--border-radius);
            font-weight: 500;
        }

        .alert-success {
            background-color: rgba(75, 181, 67, 0.2);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .alert-error {
            background-color: rgba(255, 51, 51, 0.2);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        .alert-info {
            background-color: rgba(0, 123, 255, 0.2);
            color: var(--info-color);
            border: 1px solid var(--info-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                padding: 1rem;
                gap: 1rem;
            }

            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }

            .user-actions {
                margin-top: 1rem;
            }

            .shop-header h1 {
                font-size: 2.5rem;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 576px) {
            .shop-header h1 {
                font-size: 2rem;
            }

            .product-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <a href="index.php"><i class="fas fa-tshirt"></i> Urban Trends</a>
            </div>
            
            <div style="display: flex; align-items: center;">
                <nav>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="shop.php"><i class="fas fa-store"></i> Shop</a></li>
                        <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                        <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                    </ul>
                </nav>
                
                <div class="user-actions" style="margin-left: auto;">
                    <?php if ($auth->isLoggedIn()): ?>
                        <?php if ($auth->isAdmin()): ?>
                            <a href="admin/dashboard.php" title="Admin"><i class="fas fa-cog"></i></a>
                        <?php endif; ?>
                        <a href="profile.php" title="Profile"><i class="fas fa-user"></i> Profile</a>
                        <a href="?logout=1" title="Logout"><i class="fas fa-sign-out-alt"></i> logout</a>
                        <a href="cart.php" title="Cart" class="cart-count">
                            <i class="fas fa-shopping-cart"></i>
                            <span id="cart-counter"><?php echo $cart_count; ?></span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" title="Login"><i class="fas fa-sign-in-alt"></i>Login first</a>
                        <a href="register.php" title="Register"><i class="fas fa-user-plus"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <main class="container">
        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info">
                <?php echo $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
            </div>
        <?php endif; ?>

        <section class="shop-section">
            <div class="shop-header">
                <h1>
                    <?php 
                    if ($db_category) {
                        switch($db_category) {
                            case 'men_tshirts': echo "MEN'S FASHION"; break;
                            case 'women': echo "WOMEN'S FASHION"; break;
                            case 'shoes': echo "FOOTWEAR"; break;
                            case 'access': echo "ACCESSORIES"; break;
                            default: echo "SHOP COLLECTION";
                        }
                    } else {
                        echo "SHOP COLLECTION";
                    }
                    ?>
                </h1>
            </div>
            
            <div class="search-filter-container">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search products..." id="searchInput">
                </div>
                <button class="filter-btn">
                    <i class="fas fa-filter"></i> Filters
                </button>
            </div>
            
            <div class="categories">
                <button class="category-btn <?php echo !$db_category ? 'active' : ''; ?>" data-category="all">All Products</button>
                <button class="category-btn <?php echo $db_category === 'men_tshirts' ? 'active' : ''; ?>" data-category="men">Men's Fashion</button>
                <button class="category-btn <?php echo $db_category === 'women' ? 'active' : ''; ?>" data-category="women">Women's Fashion</button>
                <button class="category-btn <?php echo $db_category === 'shoes' ? 'active' : ''; ?>" data-category="shoes">Footwear</button>
                <button class="category-btn <?php echo $db_category === 'access' ? 'active' : ''; ?>" data-category="accessories">Accessories</button>
            </div>
            
            <div class="products-grid" id="productsContainer">
                <?php foreach ($products as $product): 
                    $has_variations = $product['has_variations'] > 0;
                    $total_stock = $product['total_stock'] ?? 0;
                    // Modified image path to ensure correct resolution
                    $primary_image = file_exists("assets/images/products/" . $product['image']) 
                        ? $product['image'] 
                        : 'default-product.jpg';
                ?>
                    <div class="product-card" data-id="<?php echo $product['product_id']; ?>">
                        <?php if($total_stock <= 0): ?>
                            <div class="out-of-stock-overlay">
                                <span>OUT OF STOCK</span>
                            </div>
                        <?php elseif($total_stock < 10): ?>
                            <span class="product-badge">Only <?php echo $total_stock; ?> left</span>
                        <?php endif; ?>
                        
                        <div class="product-image-container">
                            <img src="assets/images/products/<?php echo htmlspecialchars($primary_image); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="product-image"
                                 onerror="this.src='assets/images/products/default-product.jpg'">
                        </div>
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-price" data-base-price="<?php echo $product['price']; ?>">
                                ₱<?php echo number_format($product['price'], 2); ?>
                            </p>
                            
                            <form method="POST" class="product-form" data-id="<?php echo $product['product_id']; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                
                                <?php if($has_variations && $total_stock > 0): ?>
                                    <div class="size-selector">
                                        <?php foreach ($product['variations'] as $variation): ?>
                                            <?php if ($variation['stock'] > 0): ?>
                                                <button type="button" 
                                                        class="size-button" 
                                                        data-variation-id="<?php echo $variation['variation_id']; ?>"
                                                        data-stock="<?php echo $variation['stock']; ?>"
                                                        data-price-adjustment="<?php echo $variation['price_adjustment']; ?>">
                                                    <?php echo $variation['size']; ?>
                                                </button>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <input type="hidden" name="variation_id" class="selected-variation">
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($total_stock > 0): ?>
                                    <div class="quantity-selector">
                                        <button type="button" class="quantity-btn minus">-</button>
                                        <input type="number" 
                                               name="quantity" 
                                               class="quantity-input" 
                                               value="1" 
                                               min="1" 
                                               max="<?php echo $total_stock; ?>" 
                                               readonly>
                                        <button type="button" class="quantity-btn plus">+</button>
                                    </div>
                                
                                    <div class="product-actions">
                                        <button type="submit" 
                                                name="buy_now" 
                                                class="action-btn buy-now" 
                                                disabled 
                                                data-action="buy">
                                            <i class="fas fa-bolt"></i> Buy Now
                                        </button>
                                        <button type="submit" 
                                                name="add_to_cart" 
                                                class="action-btn add-to-cart" 
                                                disabled 
                                                data-action="cart">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                        <button type="button" 
                                                class="wishlist-btn <?php echo isInWishlist($db, $_SESSION['user_id'], $product['product_id']) ? 'active' : ''; ?>" 
                                                onclick="toggleWishlist(<?php echo $product['product_id']; ?>, this)">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="product-actions">
                                        <button class="action-btn buy-now" disabled>
                                            <i class="fas fa-ban"></i> Out of Stock
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>About Urban Trends</h3>
                    <p>Your premier destination for the latest in urban fashion trends. We offer high-quality apparel and accessories for the modern urban lifestyle.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="shop.php"><i class="fas fa-chevron-right"></i> Shop</a></li>
                        <li><a href="about.php"><i class="fas fa-chevron-right"></i> About</a></li>
                        <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact</a></li>
                        <li><a href="faq.php"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Customer Service</h3>
                    <ul>
                        <li><a href="profile.php"><i class="fas fa-chevron-right"></i> My Account</a></li>
                        <li><a href="orders.php"><i class="fas fa-chevron-right"></i> Order Tracking</a></li>
                        <li><a href="returns.php"><i class="fas fa-chevron-right"></i> Returns & Refunds</a></li>
                        <li><a href="privacy.php"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                        <li><a href="terms.php"><i class="fas fa-chevron-right"></i> Terms & Conditions</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> 123 Urban Street, Fashion District, City</li>
                        <li><i class="fas fa-phone"></i> +1 (123) 456-7890</li>
                        <li><i class="fas fa-envelope"></i> info@urbantrends.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9AM - 6PM</li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                © <?php echo date('Y'); ?> Urban Trends Apparel. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // Update cart counter
        function updateCartCounter() {
            fetch('get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('cart-counter').textContent = data.count;
                })
                .catch(error => {
                    console.error('Error fetching cart count:', error);
                });
        }

        // Category filter
        document.querySelectorAll('.category-btn').forEach(button => {
            button.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                window.location.href = category === 'all' ? 'shop.php' : `shop.php?category=${category}`;
            });
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            fetch('search_products.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `search_term=${searchTerm}`
            })
            .then(response => response.text())
            .then(html => {
                document.getElementById('productsContainer').innerHTML = html;
                initializeProductControls();
            });
        });

        // Initialize product controls
        function initializeProductControls() {
            document.querySelectorAll('.product-card').forEach(card => {
                const form = card.querySelector('.product-form');
                const sizeButtons = card.querySelectorAll('.size-button');
                const quantityInput = card.querySelector('.quantity-input');
                const minusBtn = card.querySelector('.quantity-btn.minus');
                const plusBtn = card.querySelector('.quantity-btn.plus');
                const buyBtn = card.querySelector('.buy-now');
                const cartBtn = card.querySelector('.add-to-cart');
                const variationInput = card.querySelector('.selected-variation');

                // Size selection
                sizeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        sizeButtons.forEach(btn => btn.classList.remove('selected'));
                        this.classList.add('selected');
                        
                        // Update variation ID
                        if (variationInput) {
                            variationInput.value = this.dataset.variationId;
                        }
                        
                        // Update quantity max
                        if (quantityInput) {
                            const maxStock = parseInt(this.dataset.stock);
                            quantityInput.max = maxStock;
                            if (parseInt(quantityInput.value) > maxStock) {
                                quantityInput.value = maxStock;
                            }
                        }
                        
                        // Update price
                        const priceAdjustment = parseFloat(this.dataset.priceAdjustment) || 0;
                        const basePrice = parseFloat(card.querySelector('.product-price').dataset.basePrice);
                        const newPrice = basePrice + priceAdjustment;
                        card.querySelector('.product-price').textContent = '₱' + newPrice.toFixed(2);
                        
                        // Enable buttons
                        if (buyBtn) buyBtn.disabled = false;
                        if (cartBtn) cartBtn.disabled = false;
                    });
                });
                
                // Quantity controls
                if (minusBtn && quantityInput) {
                    minusBtn.addEventListener('click', function() {
                        const currentValue = parseInt(quantityInput.value);
                        if (currentValue > 1) {
                            quantityInput.value = currentValue - 1;
                        }
                    });
                }
                
                if (plusBtn && quantityInput) {
                    plusBtn.addEventListener('click', function() {
                        const currentValue = parseInt(quantityInput.value);
                        const maxValue = parseInt(quantityInput.max);
                        if (currentValue < maxValue) {
                            quantityInput.value = currentValue + 1;
                        }
                    });
                }
            });
        }

        // Wishlist toggle
        function toggleWishlist(productId, button) {
            const action = button.classList.contains('active') ? 'remove' : 'add';
            
            fetch('shop.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&wishlist_action=${action}`
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                }
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initializeProductControls);
    </script>
</body>
</html>