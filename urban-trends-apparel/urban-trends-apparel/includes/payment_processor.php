<?php
require_once 'config.php';

class PaymentProcessor {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function processPayment($order_id, $amount, $method, $details = []) {
        $transaction_id = $this->generateTransactionId();
        $status = 'pending';
        
        // For COD, mark as pending (will be completed when delivered)
        if ($method === 'cod') {
            $status = 'pending';
        }
        
        // For online payments, we would integrate with payment gateways here
        if ($method === 'gcash' || $method === 'paypal' || 
            $method === 'credit_card' || $method === 'debit_card') {
            // In a real app, this would call the payment gateway API
            $status = 'completed'; // Assuming success for demo
            $transaction_id = 'PAY-' . strtoupper($method) . '-' . uniqid();
        }
        
        $stmt = $this->db->prepare("INSERT INTO payments 
                                   (order_id, amount, payment_method, transaction_id, status, payment_date) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
        $success = $stmt->execute([$order_id, $amount, $method, $transaction_id, $status]);
        
        if ($success && $status === 'completed') {
            $this->updateOrderStatus($order_id, 'processing');
        }
        
        return [
            'success' => $success,
            'transaction_id' => $transaction_id,
            'status' => $status
        ];
    }
    
    private function generateTransactionId() {
        return 'TXN-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
    }
    
    private function updateOrderStatus($order_id, $status) {
        $stmt = $this->db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        
        // Record in status history
        $stmt = $this->db->prepare("INSERT INTO order_status_history 
                                   (order_id, status) VALUES (?, ?)");
        $stmt->execute([$order_id, $status]);
    }
}

$payment = new PaymentProcessor($db);

// Example usage in checkout process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $order_id = $_POST['order_id'];
    $amount = $_POST['amount'];
    $method = $_POST['payment_method'];
    
    $result = $payment->processPayment($order_id, $amount, $method);
    
    if ($result['success']) {
        // Redirect to thank you page or show success message
        header("Location: order_confirmation.php?order_id=$order_id");
        exit;
    } else {
        // Show error message
        $_SESSION['error'] = "Payment processing failed. Please try again.";
        header("Location: checkout.php?order_id=$order_id");
        exit;
    }
}
?>