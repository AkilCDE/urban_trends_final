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
}

$auth = new Auth($db);

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    header("Location: shop.php");
    exit;
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];

// Get order details
$stmt = $db->prepare("
    SELECT o.*, p.payment_method, p.transaction_id, p.status as payment_status,
           d.preferred_date, d.preferred_time_slot, d.pickup_option, d.pickup_location
    FROM orders o
    LEFT JOIN payments p ON o.order_id = p.order_id
    LEFT JOIN delivery_schedules d ON o.order_id = d.order_id
    WHERE o.order_id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: shop.php");
    exit;
}

// Get order items with variation details
$stmt = $db->prepare("
    SELECT oi.*, p.name, p.image, pv.size
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    LEFT JOIN product_variations pv ON oi.variation_id = pv.variation_id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate subtotal
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = 50.00;
$total = $subtotal + $shipping;

// Get cart count
$cart_count = 0;
if ($auth->isLoggedIn()) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_count = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Trends Apparel - Order Confirmation</title>
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 2rem; /* Adjusted for consistency with checkout.php */
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

        /* Alert Styles */
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
        }
        
        .alert-success {
            background-color: rgba(75, 181, 67, 0.2);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        /* Order Confirmation Styles */
        .confirmation-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: var(--primary-color);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
        }
        
        .confirmation-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .confirmation-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-20px);}
            60% {transform: translateY(-10px);}
        }
        
        .order-details {
            background-color: var(--secondary-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .order-detail-group {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 1rem;
            border-radius: var(--border-radius);
        }
        
        .order-detail-group h4 {
            margin-bottom: 0.5rem;
            color: var(--accent-color);
            font-size: 1rem;
        }
        
        .order-items {
            margin-top: 2rem;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #444;
            gap: 1.5rem;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-price {
            font-weight: 600;
            color: var(--accent-color);
        }
        
        .order-item-size {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: rgba(255, 204, 0, 0.2);
            color: #ffcc00;
        }
        
        .status-completed {
            background-color: rgba(40, 167, 69, 0.2);
            color: #40c057;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn:hover {
            background-color: #ff5252;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--accent-color);
            color: var(--accent-color);
        }
        
        .btn-outline:hover {
            background-color: var(--accent-color);
            color: white;
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

        @media (max-width: 768px) {
            .header-right {
                flex-direction: column;
                gap: 1rem;
            }
            
            nav ul {
                gap: 1rem;
            }
            
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
        
        <div class="header-right"> 
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="shop.php"><i class="fas fa-store"></i> Shop</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                </ul>
            </nav>
           
            <div class="user-actions">
                <?php if ($auth->isLoggedIn()): ?>
                    <a href="profile.php" title="Profile"><i class="fas fa-user"></i></a>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="admin/dashboard.php" title="Admin"><i class="fas fa-cog"></i></a>
                    <?php endif; ?>
                    <a href="wishlist.php" title="Wishlist"><i class="fas fa-heart"></i></a>
                    <a href="cart.php" title="Cart" class="cart-count">
                        <i class="fas fa-shopping-cart"></i>
                        <span><?php echo $cart_count; ?></span>
                    </a>
                    <a href="?logout=1" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
                <?php else: ?>
                    <a href="login.php" title="Login"><i class="fas fa-sign-in-alt"></i></a>
                    <a href="register.php" title="Register"><i class="fas fa-user-plus"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
    
<main class="container">
    <div class="confirmation-container">
        <div class="confirmation-header">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Order Confirmation</h1>
            <p>Thank you for your order! Your order number is #<?php echo $order_id; ?></p>
        </div>
        
        <div class="order-details">
            <h2>Order Summary</h2>
            
            <div class="order-details-grid">
                <div class="order-detail-group">
                    <h4><i class="fas fa-info-circle"></i> Order Information</h4>
                    <p><strong>Order #:</strong> <?php echo $order_id; ?></p>
                    <p><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                    </span></p>
                </div>
                
                <div class="order-detail-group">
                    <h4><i class="fas fa-truck"></i> Delivery Information</h4>
                    <?php if ($order['pickup_option']): ?>
                        <p><strong>Pickup Location:</strong> <?php echo htmlspecialchars($order['pickup_location'] ?: 'N/A'); ?></p>
                        <p><strong>Ready for pickup on:</strong> <?php echo date('M d, Y', strtotime($order['preferred_date'] ?: date('Y-m-d'))); ?></p>
                    <?php else: ?>
                        <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                        <p><strong>Scheduled Delivery:</strong> 
                            <?php echo date('M d, Y', strtotime($order['preferred_date'] ?: date('Y-m-d'))); ?> 
                            (<?php echo ucfirst(htmlspecialchars($order['preferred_time_slot'] ?: 'N/A')); ?>)
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="order-detail-group">
                    <h4><i class="fas fa-credit-card"></i> Payment Information</h4>
                    <p><strong>Method:</strong> <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo $order['payment_status'] === 'completed' ? 'completed' : 'pending'; ?>">
                        <?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?>
                    </span></p>
                    <?php if (!empty($order['transaction_id'])): ?>
                        <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['transaction_id']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="order-items">
                <h4><i class="fas fa-box-open"></i> Order Items</h4>
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <img src="assets/images/products/<?php echo htmlspecialchars($item['image'] ?: 'default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="order-item-image"
                             onerror="this.src='assets/images/products/default-product.jpg'">
                        <div class="order-item-details">
                            <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                            <?php if ($item['size']): ?>
                                <p class="order-item-size">Size: <?php echo htmlspecialchars($item['size']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right;">
                            <p>₱<?php echo number_format($item['price'], 2); ?></p>
                            <p>x<?php echo $item['quantity']; ?></p>
                            <p class="order-item-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align: right; margin-top: 1rem;">
                    <p><strong>Subtotal:</strong> ₱<?php echo number_format($subtotal, 2); ?></p>
                    <p><strong>Shipping:</strong> ₱<?php echo number_format($shipping, 2); ?></p>
                    <p><strong>Total:</strong> ₱<?php echo number_format($total, 2); ?></p>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="profile.php#orders" class="btn"><i class="fas fa-clipboard-list"></i> View Order History</a>
            <a href="shop.php" class="btn btn-outline"><i class="fas fa-shopping-bag"></i> Continue Shopping</a>
        </div>
    </div>
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
                    <li><a href="about.php"><i class="fas fa-chevron-right"></i> About Us</a></li>
                    <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
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
</body>
</html>