<?php
// Database configuration
require_once 'Database/datab.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No product ID provided']);
    exit;
}

$product_id = intval($_GET['id']);

// Get product details
$stmt = $db->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

// Get product variations
$stmt = $db->prepare("SELECT variation_id, size, stock FROM product_variations WHERE product_id = ?");
$stmt->execute([$product_id]);
$variations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'product' => $product,
    'variations' => $variations
]);
?>