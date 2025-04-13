<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to add items to cart']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? 0;
    $size_id = $_POST['size_id'] ?? null;
    $quantity = $_POST['quantity'] ?? 1;
    
    // Validate product exists
    $product = $db->prepare("SELECT * FROM products WHERE product_id = ?");
    $product->execute([$product_id]);
    $product = $product->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Invalid product']);
        exit;
    }
    
    // Check stock (for products with sizes)
    if ($size_id) {
        if (!$productSize->checkStock($size_id, $quantity)) {
            echo json_encode(['success' => false, 'message' => 'Selected size out of stock']);
            exit;
        }
    } 
    // Check stock (for products without sizes)
    elseif ($product['stock'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
        exit;
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Generate unique cart item ID
    $cart_item_id = $size_id ? "{$product_id}-{$size_id}" : $product_id;
    
    // Check if item already in cart
    if (isset($_SESSION['cart'][$cart_item_id])) {
        $_SESSION['cart'][$cart_item_id]['quantity'] += $quantity;
    } else {
        // Get size info if applicable
        $size = null;
        if ($size_id) {
            $size = $productSize->getSizeById($size_id);
        }
        
        $_SESSION['cart'][$cart_item_id] = [
            'product_id' => $product_id,
            'size_id' => $size_id,
            'size' => $size ? $size['size'] : null,
            'name' => $product['name'],
            'image' => $product['image'],
            'price' => $product['price'],
            'quantity' => $quantity
        ];
    }
    
    echo json_encode(['success' => true, 'count' => count($_SESSION['cart'])]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>