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

foreach ($products as $product): ?>
    <div class="product-card" data-id="<?php echo $product['id']; ?>">
        <?php if($product['stock'] < 10): ?>
            <span class="product-badge">Only <?php echo $product['stock']; ?> left</span>
        <?php endif; ?>
        <div class="product-image-container">
            <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
        </div>
        <div class="product-info">
            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
            <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
            <div class="product-actions">
                <button class="action-btn buy-now" data-id="<?php echo $product['id']; ?>">
                    <i class="fas fa-bolt"></i> Buy Now
                </button>
                <button class="action-btn add-to-cart" data-id="<?php echo $product['id']; ?>">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
                <button class="wishlist-btn <?php echo isInWishlist($db, $_SESSION['user_id'] ?? null, $product['id']) ? 'active' : ''; ?>" 
                        data-id="<?php echo $product['id']; ?>">
                    <i class="fas fa-heart"></i>
                </button>
            </div>
        </div>
    </div>
<?php endforeach; ?>