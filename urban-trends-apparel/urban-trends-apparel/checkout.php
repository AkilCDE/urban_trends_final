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
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }
    
    public function getWalletBalance($user_id) {
        $stmt = $this->db->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['balance'] : 0;
    }
    
    public function addToWallet($user_id, $amount) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_wallet (user_id, balance) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE balance = balance + ?
            ");
            $stmt->execute([$user_id, $amount, $amount]);
            return true;
        } catch(PDOException $e) {
            error_log("Wallet error: " . $e->getMessage());
            throw new Exception("Failed to update wallet: " . $e->getMessage());
        }
    }
}

$auth = new Auth($db);

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$user = $auth->getCurrentUser();

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: login.php");
    exit;
}

// Get cart items
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("
    SELECT c.*, p.name, p.price, p.image 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = 50; // Flat rate shipping
$total = $subtotal + $shipping;

// Get user's wallet balance
$wallet_balance = $auth->getWalletBalance($user_id);

// Handle add funds to wallet
if (isset($_POST['add_funds'])) {
    $amount = floatval($_POST['fund_amount']);
    if ($amount > 0) {
        if ($auth->addToWallet($user_id, $amount)) {
            $_SESSION['success_message'] = "Successfully added ₱" . number_format($amount, 2) . " to your wallet!";
            header("Location: checkout.php");
            exit;
        } else {
            $error = "Failed to add funds to wallet. Please try again.";
        }
    } else {
        $error = "Please enter a valid amount to add to your wallet.";
    }
}

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    try {
        // Validate all required fields
        $required_fields = [
            'fullname' => 'Full Name',
            'email' => 'Email',
            'shipping_address' => 'Shipping Address',
            'phone' => 'Phone Number',
            'payment_method' => 'Payment Method'
        ];
        
        $missing_fields = [];
        foreach ($required_fields as $field => $name) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $name;
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception("Please fill in all required fields: " . implode(', ', $missing_fields));
        }
        
        $payment_method = $_POST['payment_method'];
        $transaction_id = null;
        $payment_status = 'pending';
        
        // Validate payment method specific fields
        switch ($payment_method) {
            case 'gcash':
                if (empty($_POST['gcash_number'])) {
                    throw new Exception("Please provide your GCash number.");
                }
                $transaction_id = 'GC' . time() . rand(100, 999);
                break;
                
            case 'paypal':
                $transaction_id = 'PP' . time() . rand(100, 999);
                break;
                
            case 'credit_card':
                if (empty($_POST['card_number']) || empty($_POST['card_name']) || 
                    empty($_POST['card_expiry']) || empty($_POST['card_cvv'])) {
                    throw new Exception("Please provide complete credit card information.");
                }
                $transaction_id = 'CC' . time() . rand(100, 999);
                break;
                
            case 'wallet':
                if ($wallet_balance < $total) {
                    throw new Exception("Insufficient funds in wallet. Please add more funds or choose another payment method.");
                }
                $payment_status = 'completed';
                $transaction_id = 'WL' . time() . rand(100, 999);
                break;
                
            case 'cod':
                $payment_status = 'pending';
                break;
                
            default:
                throw new Exception("Invalid payment method selected.");
        }
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // 1. Create order
            $stmt = $db->prepare("
                INSERT INTO orders (user_id, total_amount, shipping_address, status) 
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $user_id,
                $total,
                $_POST['shipping_address']
            ]);
            $order_id = $db->lastInsertId();
            
            // 2. Add order items
            foreach ($cart_items as $item) {
                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price']
                ]);
                
                // 3. Update product stock
                $stmt = $db->prepare("
                    UPDATE products SET stock = stock - ? WHERE id = ?
                ");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // 4. Create payment record
            $stmt = $db->prepare("
                INSERT INTO payments (order_id, amount, payment_method, transaction_id, status) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $total,
                $payment_method,
                $transaction_id,
                $payment_status
            ]);
            
            // 5. Add delivery schedule if provided
            if (!empty($_POST['delivery_date'])) {
                $stmt = $db->prepare("
                    INSERT INTO delivery_schedules 
                    (order_id, preferred_date, preferred_time_slot, pickup_option, pickup_location) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id,
                    $_POST['delivery_date'],
                    $_POST['time_slot'],
                    isset($_POST['pickup_option']) ? 1 : 0,
                    $_POST['pickup_location'] ?? null
                ]);
            }
            
            // 6. If payment is wallet, deduct from wallet
            if ($payment_method === 'wallet') {
                $stmt = $db->prepare("
                    UPDATE user_wallet SET balance = balance - ? 
                    WHERE user_id = ? AND balance >= ?
                ");
                $stmt->execute([$total, $user_id, $total]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception("Insufficient wallet balance.");
                }
            }
            
            // 7. Clear cart
            $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // 8. Add initial order status
            $stmt = $db->prepare("
                INSERT INTO order_status_history (order_id, status, notes) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                'pending',
                'Order created successfully'
            ]);
            
            // Commit transaction
            $db->commit();
            
            // Redirect to confirmation
            header("Location: order_confirmation.php?order_id=" . $order_id);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error = "Checkout failed: " . $e->getMessage();
    }
}

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
    <title>Urban Trends Apparel - Checkout</title>
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
            gap: 28rem;
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
        
        .alert-danger {
            background-color: rgba(255, 51, 51, 0.2);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }
        
        .alert-success {
            background-color: rgba(75, 181, 67, 0.2);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }
        
        .alert-info {
            background-color: rgba(0, 123, 255, 0.2);
            border: 1px solid #007bff;
            color: #007bff;
        }

        /* Checkout specific styles */
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .checkout-section {
            background-color: var(--primary-color);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .checkout-section h2 {
            margin-bottom: 1.5rem;
            color: var(--accent-color);
            border-bottom: 1px solid #444;
            padding-bottom: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid #444;
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }
        
        /* Payment Methods */
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 1rem;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .payment-method:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .payment-method.selected {
            background-color: rgba(255, 107, 107, 0.2);
            border: 1px solid var(--accent-color);
        }
        
        .payment-method input {
            display: none;
        }
        
        .payment-method i {
            font-size: 1.5rem;
        }
        
        /* Wallet Section */
        .wallet-section {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }
        
        .wallet-balance {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        
        .wallet-balance span {
            color: var(--accent-color);
            font-weight: bold;
        }
        
        .wallet-form {
            display: flex;
            gap: 1rem;
        }
        
        .wallet-form input {
            flex: 1;
        }
        
        /* Order Summary */
        .order-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #444;
        }
        
        .order-total {
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 1rem;
            color: var(--accent-color);
        }
        
        .checkout-btn {
            width: 100%;
            padding: 1rem;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1rem;
        }
        
        .checkout-btn:hover {
            background-color: #ff5252;
            transform: translateY(-2px);
        }
        
        .checkout-btn:disabled {
            background-color: #666;
            cursor: not-allowed;
            transform: none;
        }
        
        .cart-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #444;
        }
        
        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .cart-item-details {
            flex: 1;
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
            .checkout-container {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .header-right {
                flex-direction: column;
                gap: 1rem;
            }
            
            nav ul {
                gap: 1rem;
            }
            
            .wallet-form {
                flex-direction: column;
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
    <h1 style="margin: 2rem 0 1rem;">Checkout</h1>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    
    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Your cart is empty. <a href="shop.php">Continue shopping</a>
        </div>
    <?php else: ?>
        <form method="POST" class="checkout-container" id="checkoutForm">
            <div class="checkout-section">
                <h2>Shipping Information</h2>
                
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" class="form-control" required 
                           value="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required 
                           value="<?php echo htmlspecialchars($user['email']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="shipping_address">Shipping Address</label>
                    <textarea id="shipping_address" name="shipping_address" class="form-control" rows="4" required><?php 
                        echo htmlspecialchars($user['address']); 
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                
                <h2>Delivery Options</h2>
                
                <div class="form-group">
                    <label for="delivery_date">Preferred Delivery Date</label>
                    <input type="date" id="delivery_date" name="delivery_date" class="form-control" 
                           min="<?php echo date('Y-m-d', strtotime('+2 days')); ?>">
                </div>
                
                <div class="form-group">
                    <label for="time_slot">Preferred Time Slot</label>
                    <select id="time_slot" name="time_slot" class="form-control">
                        <option value="morning">Morning (9AM - 12PM)</option>
                        <option value="afternoon">Afternoon (1PM - 5PM)</option>
                        <option value="evening">Evening (6PM - 9PM)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="pickup_option" id="pickup_option"> 
                        I prefer to pick up in-store
                    </label>
                </div>
                
                <div class="form-group" id="pickup_location_group" style="display: none;">
                    <label for="pickup_location">Pickup Location</label>
                    <select id="pickup_location" name="pickup_location" class="form-control">
                        <option value="Main Store">Main Store - 123 Urban Street</option>
                        <option value="Mall Branch">Mall Branch - Fashion District</option>
                        <option value="Downtown Branch">Downtown Branch - City Center</option>
                    </select>
                </div>
                
                <h2>Payment Method</h2>
                
                <div class="payment-methods">
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="cod" required>
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Cash on Delivery</span>
                    </label>
                    
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="gcash">
                        <i class="fas fa-mobile-alt"></i>
                        <span>GCash</span>
                    </label>
                    
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="paypal">
                        <i class="fab fa-paypal"></i>
                        <span>PayPal</span>
                    </label>
                    
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="credit_card">
                        <i class="far fa-credit-card"></i>
                        <span>Credit Card</span>
                    </label>
                    
                    <?php if ($wallet_balance > 0): ?>
                    <label class="payment-method">
                        <input type="radio" name="payment_method" value="wallet">
                        <i class="fas fa-wallet"></i>
                        <span>Wallet (₱<?php echo number_format($wallet_balance, 2); ?>)</span>
                    </label>
                    <?php endif; ?>
                </div>
                
                <!-- Wallet section -->
                <?php if ($wallet_balance < $total): ?>
                <div class="wallet-section">
                    <div class="wallet-balance">
                        Your wallet balance: <span>₱<?php echo number_format($wallet_balance, 2); ?></span>
                    </div>
                    <p>Add funds to your wallet to complete your purchase:</p>
                    <form method="POST" class="wallet-form">
                        <input type="number" name="fund_amount" class="form-control" 
                               placeholder="Amount to add" min="1" step="0.01"
                               value="<?php echo max(1, ceil($total - $wallet_balance)); ?>">
                        <button type="submit" name="add_funds" class="btn">
                            <i class="fas fa-plus-circle"></i> Add Funds
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Payment details (shown based on selection) -->
                <div id="payment-details"></div>
            </div>
            
            <div class="checkout-section">
                <h2>Order Summary</h2>
                
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image">
                        <div class="cart-item-details">
                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                            <p>₱<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?></p>
                        </div>
                        <div>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
                
                <div class="order-summary-item">
                    <span>Subtotal</span>
                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div class="order-summary-item">
                    <span>Shipping</span>
                    <span>₱<?php echo number_format($shipping, 2); ?></span>
                </div>
                
                <div class="order-summary-item order-total">
                    <span>Total</span>
                    <span>₱<?php echo number_format($total, 2); ?></span>
                </div>
                
                <button type="submit" name="checkout" class="checkout-btn" id="completeOrderBtn">
                    <i class="fas fa-credit-card"></i> Complete Order
                </button>
                
                <a href="cart.php" class="btn btn-outline" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-arrow-left"></i> Back to Cart
                </a>
            </div>
        </form>
    <?php endif; ?>
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
            &copy; <?php echo date('Y'); ?> Urban Trends Apparel. All rights reserved.
        </div>
    </div>
</footer>

<script>
    // Show/hide pickup location based on checkbox
    document.getElementById('pickup_option').addEventListener('change', function() {
        const pickupLocationGroup = document.getElementById('pickup_location_group');
        pickupLocationGroup.style.display = this.checked ? 'block' : 'none';
    });

    // Set minimum delivery date (2 days from now)
    document.getElementById('delivery_date').min = new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    // Show payment details based on selection
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const paymentDetails = document.getElementById('payment-details');
            let html = '';
            
            switch(this.value) {
                case 'gcash':
                    html = `
                        <div class="form-group">
                            <label for="gcash_number">GCash Number</label>
                            <input type="text" id="gcash_number" name="gcash_number" class="form-control" placeholder="09XXXXXXXXX" required>
                        </div>
                        <div class="form-group">
                            <label for="gcash_name">Account Name</label>
                            <input type="text" id="gcash_name" name="gcash_name" class="form-control" required>
                        </div>
                    `;
                    break;
                    
                case 'paypal':
                    html = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You will be redirected to PayPal to complete your payment.
                        </div>
                    `;
                    break;
                    
                case 'credit_card':
                    html = `
                        <div class="form-group">
                            <label for="card_number">Card Number</label>
                            <input type="text" id="card_number" name="card_number" class="form-control" placeholder="1234 5678 9012 3456" required>
                        </div>
                        <div class="form-group">
                            <label for="card_name">Name on Card</label>
                            <input type="text" id="card_name" name="card_name" class="form-control" required>
                        </div>
                        <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label for="card_expiry">Expiry Date</label>
                                <input type="text" id="card_expiry" name="card_expiry" class="form-control" placeholder="MM/YY" required>
                            </div>
                            <div>
                                <label for="card_cvv">CVV</label>
                                <input type="text" id="card_cvv" name="card_cvv" class="form-control" placeholder="123" required>
                            </div>
                        </div>
                    `;
                    break;
                    
                default:
                    html = '';
            }
            
            paymentDetails.innerHTML = html;
        });
    });

    // Form validation before submission
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        if (e.submitter && e.submitter.name === 'checkout') {
            // Validate required fields
            const requiredFields = [
                'fullname', 'email', 'shipping_address', 'phone', 'payment_method'
            ];
            
            let isValid = true;
            let missingFields = [];
            
            requiredFields.forEach(field => {
                const element = document.querySelector(`[name="${field}"]`);
                if (!element || !element.value.trim()) {
                    isValid = false;
                    missingFields.push(field.replace('_', ' '));
                    element.classList.add('error');
                } else {
                    element.classList.remove('error');
                }
            });
            
            // Validate payment method specific fields
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                isValid = false;
                alert('Please select a payment method.');
                return false;
            }
            
            switch(paymentMethod.value) {
                case 'gcash':
                    if (!document.getElementById('gcash_number') || !document.getElementById('gcash_number').value.trim() ||
                        !document.getElementById('gcash_name') || !document.getElementById('gcash_name').value.trim()) {
                        isValid = false;
                        alert('Please provide your GCash number and account name.');
                    }
                    break;
                    
                case 'credit_card':
                    if (!document.getElementById('card_number') || !document.getElementById('card_number').value.trim() ||
                        !document.getElementById('card_name') || !document.getElementById('card_name').value.trim() ||
                        !document.getElementById('card_expiry') || !document.getElementById('card_expiry').value.trim() ||
                        !document.getElementById('card_cvv') || !document.getElementById('card_cvv').value.trim()) {
                        isValid = false;
                        alert('Please provide complete credit card information.');
                    }
                    break;
            }
            
            if (!isValid) {
                e.preventDefault();
                if (missingFields.length > 0) {
                    alert('Please fill in all required fields: ' + missingFields.join(', '));
                }
                return false;
            }
        }
    });

    // Highlight selected payment method
    document.querySelectorAll('.payment-method').forEach(method => {
        method.addEventListener('click', function() {
            document.querySelectorAll('.payment-method').forEach(m => {
                m.classList.remove('selected');
            });
            this.classList.add('selected');
        });
    });
</script>
</body>
</html>