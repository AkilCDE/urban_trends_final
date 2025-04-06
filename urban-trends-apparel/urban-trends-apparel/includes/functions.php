<?php
// Redirect to another page
function redirect($url) {
    header("Location: $url");
    exit();
}

// Display error message
function display_error($message) {
    return '<div class="error">'.$message.'</div>';
}

// Display success message
function display_success($message) {
    return '<div class="success">'.$message.'</div>';
}

// Sanitize input data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Get all products
function getProducts($db, $category = null) {
    if ($category) {
        $stmt = $db->prepare("SELECT * FROM products WHERE category = ?");
        $stmt->execute([$category]);
    } else {
        $stmt = $db->query("SELECT * FROM products");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get product by ID
function getProductById($db, $id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Add product to cart
function addToCart($product_id, $quantity = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

// Remove product from cart
function removeFromCart($product_id) {
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
}

// Get cart items
function getCartItems($db) {
    if (empty($_SESSION['cart'])) {
        return [];
    }
    
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as &$product) {
        $product['quantity'] = $_SESSION['cart'][$product['id']];
    }
    
    return $products;
}

// Get cart total
function getCartTotal($db) {
    $items = getCartItems($db);
    $total = 0;
    
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    return $total;
}

// Add to wishlist
function addToWishlist($db, $user_id, $product_id) {
    $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    return $stmt->execute([$user_id, $product_id]);
}

// Remove from wishlist
function removeFromWishlist($db, $user_id, $product_id) {
    $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    return $stmt->execute([$user_id, $product_id]);
}

// Get wishlist items
function getWishlistItems($db, $user_id) {
    $stmt = $db->prepare("SELECT p.* FROM products p JOIN wishlist w ON p.id = w.product_id WHERE w.user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if product is in wishlist
function isInWishlist($db, $user_id, $product_id) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    return $stmt->fetchColumn() > 0;
}
?>