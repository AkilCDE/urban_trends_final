<?php
require_once 'config.php';

class ReviewSystem {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function addReview($product_id, $user_id, $order_id, $rating, $title, $review) {
        // Check if user has purchased this product
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM order_items oi 
                                   JOIN orders o ON oi.order_id = o.id 
                                   WHERE oi.product_id = ? AND o.user_id = ? AND o.id = ?");
        $stmt->execute([$product_id, $user_id, $order_id]);
        $has_purchased = $stmt->fetchColumn();
        
        if (!$has_purchased) {
            return ['success' => false, 'message' => 'You can only review purchased products'];
        }
        
        // Check if user has already reviewed this product from this order
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM product_reviews 
                                   WHERE product_id = ? AND user_id = ? AND order_id = ?");
        $stmt->execute([$product_id, $user_id, $order_id]);
        $has_reviewed = $stmt->fetchColumn();
        
        if ($has_reviewed) {
            return ['success' => false, 'message' => 'You have already reviewed this product from this order'];
        }
        
        // Add review
        $stmt = $this->db->prepare("INSERT INTO product_reviews 
                                   (product_id, user_id, order_id, rating, title, review) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$product_id, $user_id, $order_id, $rating, $title, $review]);
        
        if ($success) {
            $this->updateProductRating($product_id);
            return ['success' => true, 'message' => 'Thank you for your review!'];
        }
        
        return ['success' => false, 'message' => 'Failed to submit review'];
    }
    
    public function getProductReviews($product_id, $approved_only = true) {
        $sql = "SELECT pr.*, u.firstname, u.lastname 
                FROM product_reviews pr 
                JOIN users u ON pr.user_id = u.id 
                WHERE pr.product_id = ?";
        
        if ($approved_only) {
            $sql .= " AND pr.is_approved = 1";
        }
        
        $sql .= " ORDER BY pr.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserReviews($user_id) {
        $stmt = $this->db->prepare("SELECT pr.*, p.name as product_name, p.image 
                                   FROM product_reviews pr 
                                   JOIN products p ON pr.product_id = p.id 
                                   WHERE pr.user_id = ? 
                                   ORDER BY pr.created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAverageRating($product_id) {
        $stmt = $this->db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                                   FROM product_reviews 
                                   WHERE product_id = ? AND is_approved = 1");
        $stmt->execute([$product_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateProductRating($product_id) {
        $rating_info = $this->getAverageRating($product_id);
        
        // In a real app, you might update a denormalized rating field in the products table
        // $stmt = $this->db->prepare("UPDATE products SET average_rating = ?, review_count = ? WHERE id = ?");
        // $stmt->execute([$rating_info['avg_rating'], $rating_info['review_count'], $product_id]);
    }
    
    public function approveReview($review_id) {
        $stmt = $this->db->prepare("UPDATE product_reviews SET is_approved = 1 WHERE id = ?");
        return $stmt->execute([$review_id]);
    }
    
    public function deleteReview($review_id) {
        $stmt = $this->db->prepare("DELETE FROM product_reviews WHERE id = ?");
        return $stmt->execute([$review_id]);
    }
}

$review = new ReviewSystem($db);

// Example API endpoint for submitting reviews
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $product_id = $_POST['product_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    $order_id = $_POST['order_id'] ?? 0;
    $rating = $_POST['rating'] ?? 0;
    $title = $_POST['title'] ?? '';
    $review_text = $_POST['review'] ?? '';
    
    $result = $review->addReview($product_id, $user_id, $order_id, $rating, $title, $review_text);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
?>