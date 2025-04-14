<?php
require_once 'Database/datab.php';

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