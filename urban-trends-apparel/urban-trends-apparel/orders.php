<?php
require_once 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get filter parameters
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
$rating = isset($_GET['rating']) ? intval($_GET['rating']) : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Build query for reviews
$query = "SELECT r.*, p.name as product_name, p.image as product_image, 
          u.first_name, u.last_name, o.created_at as order_date
          FROM reviews r
          JOIN products p ON r.product_id = p.id
          JOIN users u ON r.user_id = u.id
          JOIN orders o ON r.order_id = o.id
          WHERE 1=1";
$params = [];

if ($product_id) {
    $query .= " AND r.product_id = ?";
    $params[] = $product_id;
}

if ($rating) {
    $query .= " AND r.rating = ?";
    $params[] = $rating;
}

if ($date_from) {
    $query .= " AND o.created_at >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND o.created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get review statistics
$stats_query = "SELECT 
    COUNT(*) as total_reviews,
    ROUND(AVG(rating), 1) as avg_rating,
    COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
    COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
    COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
    COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
    COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
    FROM reviews";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get all products for filter
$products = $db->query("SELECT id, name FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews - Urban Trends Apparel</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/reviews.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>Customer Reviews</h1>

        <!-- Review Statistics -->
        <div class="review-stats">
            <div class="stat-card">
                <h3>Overall Rating</h3>
                <div class="rating">
                    <?php
                    $avg_rating = round($stats['avg_rating'], 1);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $avg_rating) {
                            echo '<i class="fas fa-star"></i>';
                        } elseif ($i - 0.5 <= $avg_rating) {
                            echo '<i class="fas fa-star-half-alt"></i>';
                        } else {
                            echo '<i class="far fa-star"></i>';
                        }
                    }
                    ?>
                    <span><?php echo $avg_rating; ?> out of 5</span>
                </div>
                <p>Based on <?php echo $stats['total_reviews']; ?> reviews</p>
            </div>

            <div class="rating-distribution">
                <?php
                $ratings = [
                    5 => $stats['five_star'],
                    4 => $stats['four_star'],
                    3 => $stats['three_star'],
                    2 => $stats['two_star'],
                    1 => $stats['one_star']
                ];
                foreach ($ratings as $stars => $count) {
                    $percentage = $stats['total_reviews'] > 0 ? ($count / $stats['total_reviews']) * 100 : 0;
                    ?>
                    <div class="rating-bar">
                        <span class="stars"><?php echo $stars; ?> stars</span>
                        <div class="bar">
                            <div class="fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <span class="count"><?php echo $count; ?></span>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Review Filters -->
        <div class="review-filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="product_id">Product</label>
                    <select name="product_id" id="product_id">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rating">Rating</label>
                    <select name="rating" id="rating">
                        <option value="">All Ratings</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $rating == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> Stars
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                </div>

                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                </div>

                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="orders.php" class="btn btn-secondary">Clear Filters</a>
            </form>
        </div>

        <!-- Reviews List -->
        <div class="reviews-list">
            <?php if (empty($reviews)): ?>
                <p class="no-reviews">No reviews found matching your criteria.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="product-info">
                                <img src="assets/images/products/<?php echo htmlspecialchars($review['product_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($review['product_name']); ?>" 
                                     class="product-image">
                                <div>
                                    <h3><?php echo htmlspecialchars($review['product_name']); ?></h3>
                                    <p class="review-date">
                                        <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="review-content">
                            <?php if (!empty($review['comment'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="review-footer">
                            <p class="reviewer">
                                By <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                            </p>
                            <p class="order-info">
                                From Order #<?php echo $review['order_id']; ?> 
                                (<?php echo date('M d, Y', strtotime($review['order_date'])); ?>)
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Add any necessary JavaScript for interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date inputs with current month if not set
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            if (!dateFrom.value) {
                const firstDay = new Date();
                firstDay.setDate(1);
                dateFrom.value = firstDay.toISOString().split('T')[0];
            }
            
            if (!dateTo.value) {
                const lastDay = new Date();
                lastDay.setMonth(lastDay.getMonth() + 1);
                lastDay.setDate(0);
                dateTo.value = lastDay.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html> 