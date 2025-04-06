<?php
require_once 'config.php';

class ShippingTracker {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function createShipping($order_id, $method, $pickup_location = null) {
        $tracking_number = $this->generateTrackingNumber();
        $carrier = $this->getCarrier($method);
        $estimated_delivery = $this->calculateEstimatedDelivery($method);
        
        $stmt = $this->db->prepare("INSERT INTO shipping 
                                   (order_id, tracking_number, carrier, shipping_method, 
                                   estimated_delivery, pickup_location, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'processing')");
        $success = $stmt->execute([
            $order_id, 
            $tracking_number, 
            $carrier, 
            $method,
            $estimated_delivery,
            $pickup_location
        ]);
        
        if ($success) {
            $this->updateOrderStatus($order_id, 'processing');
            $this->sendShippingNotification($order_id);
        }
        
        return $success;
    }
    
    public function updateShippingStatus($order_id, $status, $tracking_data = []) {
        $this->db->beginTransaction();
        try {
            // Update shipping table
            $stmt = $this->db->prepare("UPDATE shipping SET status = ?, 
                                       actual_delivery = IF(? = 'delivered', NOW(), actual_delivery) 
                                       WHERE order_id = ?");
            $stmt->execute([$status, $status, $order_id]);
            
            // Update order status
            $this->updateOrderStatus($order_id, $status);
            
            // Record in status history
            $notes = isset($tracking_data['notes']) ? $tracking_data['notes'] : null;
            $stmt = $this->db->prepare("INSERT INTO order_status_history 
                                       (order_id, status, notes) VALUES (?, ?, ?)");
            $stmt->execute([$order_id, $status, $notes]);
            
            $this->db->commit();
            
            // Send notification to customer
            $this->sendStatusNotification($order_id, $status);
            
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    private function generateTrackingNumber() {
        return 'TRK-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
    }
    
    private function getCarrier($method) {
        $carriers = [
            'standard' => 'Standard Shipping Co.',
            'express' => 'Express Delivery Inc.',
            'pickup' => 'Store Pickup'
        ];
        return $carriers[$method] ?? 'Standard Shipping Co.';
    }
    
    private function calculateEstimatedDelivery($method) {
        $days = [
            'standard' => 7,
            'express' => 2,
            'pickup' => 1
        ];
        $delivery_date = new DateTime();
        $delivery_date->add(new DateInterval('P' . ($days[$method] ?? 5) . 'D'));
        return $delivery_date->format('Y-m-d');
    }
    
    private function updateOrderStatus($order_id, $status) {
        $stmt = $this->db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
    }
    
    private function sendShippingNotification($order_id) {
        // In a real app, this would send an email/SMS
        $stmt = $this->db->prepare("INSERT INTO email_notifications 
                                   (recipient_email, subject, message, status) 
                                   SELECT u.email, 'Your Order Has Been Shipped', 
                                   CONCAT('Your order #', o.id, ' has been shipped.'), 'queued' 
                                   FROM orders o JOIN users u ON o.user_id = u.id 
                                   WHERE o.id = ?");
        $stmt->execute([$order_id]);
    }
    
    private function sendStatusNotification($order_id, $status) {
        $status_messages = [
            'processing' => 'is being processed',
            'shipped' => 'has been shipped',
            'delivered' => 'has been delivered',
            'cancelled' => 'has been cancelled'
        ];
        
        if (isset($status_messages[$status])) {
            $stmt = $this->db->prepare("INSERT INTO email_notifications 
                                       (recipient_email, subject, message, status) 
                                       SELECT u.email, 'Order Status Update', 
                                       CONCAT('Your order #', o.id, ' ', ?), 'queued' 
                                       FROM orders o JOIN users u ON o.user_id = u.id 
                                       WHERE o.id = ?");
            $stmt->execute([$status_messages[$status], $order_id]);
        }
    }
}

$shipping = new ShippingTracker($db);

// Example webhook for shipping updates (would be called by carrier API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['webhook'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $tracking_data = json_decode($_POST['tracking_data'], true);
    
    $success = $shipping->updateShippingStatus($order_id, $status, $tracking_data);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
    exit;
}
?>