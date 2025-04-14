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
    die("Database connection failed");
}

session_start();

$searchTerm = $_POST['search_term'] ?? '';

$stmt = $db->prepare("SELECT * FROM products WHERE name LIKE ? OR description LIKE ?");
$stmt->execute(["%$searchTerm%", "%$searchTerm%"]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

function isInWishlist($db, $user_id, $product_id) {
    if (!isset($user_id)) return false;
    $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    return $stmt->fetchColumn() > 0;
}

function getTotalStock($db, $product_id) {
    $stmt = $db->prepare("SELECT SUM(stock) as total_stock FROM product_variations WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total_stock'] ?? 0;
}

function getProductVariations($db, $product_id) {
    $stmt = $db->prepare("SELECT * FROM product_variations WHERE product_id = ?");
    $stmt->execute([$product_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($products as $product):
    $total_stock = getTotalStock($db, $product['product_id']);
    $has_variations = false;
    $variations = [];
    
    // Check if product has variations
    $stmt = $db->prepare("SELECT COUNT(*) FROM product_variations WHERE product_id = ?");
    $stmt->execute([$product['product_id']]);
    $has_variations = $stmt->fetchColumn() > 0;
    
    if ($has_variations) {
        $variations = getProductVariations($db, $product['product_id']);
    }
    
    // Get primary image
    $primary_image = $product['image'] ?: 'default-product.jpg';
?>
    <div class="product-card" data-id="<?php echo $product['product_id']; ?>">
        <?php if($total_stock > 0 && $total_stock < 10): ?>
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
                        <?php foreach ($variations as $variation): ?>
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
                                <?php echo $has_variations ? 'disabled' : ''; ?> 
                                data-action="buy">
                            <i class="fas fa-bolt"></i> Buy Now
                        </button>
                        <button type="submit" 
                                name="add_to_cart" 
                                class="action-btn add-to-cart" 
                                <?php echo $has_variations ? 'disabled' : ''; ?> 
                                data-action="cart">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                        <button type="button" 
                                class="wishlist-btn <?php echo isInWishlist($db, $_SESSION['user_id'] ?? null, $product['product_id']) ? 'active' : ''; ?>" 
                                data-id="<?php echo $product['product_id']; ?>">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="out-of-stock">
                        <p>Out of Stock</p>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php endforeach; ?>