<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');

header('Content-Type: application/json');

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$productId = (int)$_GET['id'];

try {
    // Get product stock
    $stmt = $db->prepare("SELECT stock FROM products WHERE product_id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // Get product variations
    $stmt = $db->prepare("SELECT * FROM product_variations WHERE product_id = ? AND stock > 0");
    $stmt->execute([$productId]);
    $variations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'variations' => $variations,
        'max_quantity' => $product['stock']
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}