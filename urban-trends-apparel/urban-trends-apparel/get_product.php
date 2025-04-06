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

$productId = $_GET['id'] ?? 0;

$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product not found");
}
?>

<div class="modal-product-image-container">
    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="modal-product-image">
</div>
<div class="modal-product-info">
    <h2 class="modal-product-name"><?php echo htmlspecialchars($product['name']); ?></h2>
    <p class="modal-product-price">$<?php echo number_format($product['price'], 2); ?></p>
    <p class="modal-product-description"><?php echo htmlspecialchars($product['description']); ?></p>
    
    <div class="modal-product-options">
        <div class="option-group">
            <label>Size</label>
            <div class="size-options">
                <span class="size-option selected">S</span>
                <span class="size-option">M</span>
                <span class="size-option">L</span>
                <span class="size-option">XL</span>
            </div>
        </div>
        
        <div class="option-group">
            <label>Quantity</label>
            <div class="quantity-selector">
                <button class="quantity-btn minus">-</button>
                <input type="number" class="quantity-input" value="1" min="1" max="<?php echo $product['stock']; ?>">
                <button class="quantity-btn plus">+</button>
            </div>
        </div>
    </div>
    
    <div class="modal-actions">
        <button class="modal-action-btn add-to-cart-modal">
            <i class="fas fa-cart-plus"></i> Add to Cart
        </button>
        <button class="modal-action-btn buy-now-modal">
            <i class="fas fa-bolt"></i> Buy Now
        </button>
    </div>
</div>