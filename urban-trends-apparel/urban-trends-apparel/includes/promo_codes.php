<?php
require_once 'config.php';

class Promotion {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function validatePromoCode($code, $user_id, $order_amount) {
        $stmt = $this->db->prepare("SELECT * FROM promotions WHERE code = ? AND is_active = 1 
                                   AND start_date <= NOW() AND end_date >= NOW() 
                                   AND (max_uses IS NULL OR current_uses < max_uses)");
        $stmt->execute([$code]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$promo) {
            return ['valid' => false, 'message' => 'Invalid or expired promo code'];
        }
        
        if ($order_amount < $promo['min_order_amount']) {
            return ['valid' => false, 'message' => 'Minimum order amount not met'];
        }
        
        // Check if user has already used this promo (optional)
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM order_promotions op 
                                   JOIN orders o ON op.order_id = o.id 
                                   WHERE op.promotion_id = ? AND o.user_id = ?");
        $stmt->execute([$promo['id'], $user_id]);
        $uses = $stmt->fetchColumn();
        
        if ($uses >= 1) { // Adjust as needed for your business rules
            return ['valid' => false, 'message' => 'You have already used this promo code'];
        }
        
        return [
            'valid' => true,
            'promo' => $promo,
            'discount_amount' => $this->calculateDiscount($promo, $order_amount)
        ];
    }
    
    private function calculateDiscount($promo, $order_amount) {
        if ($promo['discount_type'] === 'percentage') {
            return min($order_amount * ($promo['discount_value'] / 100), $order_amount);
        }
        return min($promo['discount_value'], $order_amount);
    }
    
    public function recordPromoUse($promo_id, $order_id, $discount_amount) {
        $this->db->beginTransaction();
        try {
            // Add to order_promotions
            $stmt = $this->db->prepare("INSERT INTO order_promotions 
                                       (order_id, promotion_id, discount_amount) 
                                       VALUES (?, ?, ?)");
            $stmt->execute([$order_id, $promo_id, $discount_amount]);
            
            // Increment usage count
            $stmt = $this->db->prepare("UPDATE promotions SET current_uses = current_uses + 1 
                                       WHERE id = ?");
            $stmt->execute([$promo_id]);
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }
}

$promotion = new Promotion($db);

// Example API endpoint for validating promo codes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'validate_promo') {
        $code = $_POST['code'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 0;
        $order_amount = $_POST['order_amount'] ?? 0;
        
        $result = $promotion->validatePromoCode($code, $user_id, $order_amount);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}
?>