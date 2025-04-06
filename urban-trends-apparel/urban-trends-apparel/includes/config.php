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

// Include all necessary classes
require_once 'Auth.php';
require_once 'ProductVariation.php';
require_once 'Promotion.php';
require_once 'PaymentProcessor.php';
require_once 'ShippingTracker.php';
require_once 'SupportSystem.php';
require_once 'ReviewSystem.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrfToken() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
}

// Password hashing options
define('PASSWORD_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_OPTIONS', ['cost' => 12]);
// Initialize classes
$auth = new Auth($db);
$variation = new ProductVariation($db);
$promotion = new Promotion($db);
$payment = new PaymentProcessor($db);
$shipping = new ShippingTracker($db);
$support = new SupportSystem($db);
$review = new ReviewSystem($db);


// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: login.php");
    exit;
}
?>