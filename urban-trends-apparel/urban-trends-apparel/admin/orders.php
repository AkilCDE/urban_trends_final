<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

class OrderDelivery {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    public function updateOrderStatus($order_id, $status, $notes = null) {
        try {
            $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'return_requested', 'returned', 'refunded'];
            if (!in_array($status, $valid_statuses)) {
                throw new Exception("Invalid status provided");
            }
            
            $stmt = $this->db->prepare("SELECT status FROM orders WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $current_status = $stmt->fetchColumn();
            
            if ($current_status === false) {
                throw new Exception("Order not found");
            }
            
            if ($current_status == 'cancelled' && $status != 'cancelled') {
                throw new Exception("Cannot change status from cancelled");
            }
            
            if ($current_status == 'delivered' && $status != 'delivered') {
                throw new Exception("Cannot change status from delivered");
            }
            
            $stmt = $this->db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->execute([$status, $order_id]);
            
            $this->recordStatusHistory($order_id, $status, $notes);
            
            if ($status == 'shipped' || $status == 'delivered') {
                $shipping_status = $status == 'shipped' ? 'shipped' : 'delivered';
                $this->updateShippingStatus($order_id, $shipping_status);
            }
            
            $stmt = $this->db->prepare("SELECT u.email, u.firstname, o.order_id as order_number 
                                      FROM orders o 
                                      JOIN users u ON o.user_id = u.user_id 
                                      WHERE o.order_id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                $this->sendStatusNotification($order['email'], $order['firstname'], $order['order_number'], $status);
            }
            
            return true;
        } catch(PDOException $e) {
            error_log("Database error updating order status: " . $e->getMessage());
            throw new Exception("Database error occurred");
        } catch(Exception $e) {
            error_log("Error updating order status: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function updateShippingStatus($order_id, $status) {
        try {
            $stmt = $this->db->prepare("UPDATE shipping SET status = ?, updated_at = NOW() WHERE order_id = ?");
            return $stmt->execute([$status, $order_id]);
        } catch(PDOException $e) {
            error_log("Error updating shipping status: " . $e->getMessage());
            return false;
        }
    }
    
    private function recordStatusHistory($order_id, $status, $notes = null) {
        try {
            $stmt = $this->db->prepare("INSERT INTO order_status_history (order_id, status, notes) VALUES (?, ?, ?)");
            return $stmt->execute([$order_id, $status, $notes]);
        } catch(PDOException $e) {
            error_log("Error recording status history: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendStatusNotification($email, $name, $order_number, $status) {
        $subject = "Order #$order_number Status Update";
        $status_text = ucwords(str_replace('_', ' ', $status));
        
        $message = "
        <html>
        <head>
            <title>Order Status Update</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4361ee; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 10px; text-align: center; font-size: 0.8em; color: #666; }
                .status { font-weight: bold; color: #4361ee; }
                .button { display: inline-block; padding: 10px 20px; background-color: #4361ee; color: white; text-decoration: none; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Urban Trends Apparel</h1>
                </div>
                <div class='content'>
                    <h2>Hello $name,</h2>
                    <p>Your order <strong>#$order_number</strong> status has been updated to: <span class='status'>$status_text</span></p>
                    
                    <p>Here's what to expect next:</p>";
                    
        switch($status) {
            case 'processing':
                $message .= "<p>Our team is preparing your order for shipment. You'll receive another notification when it's on its way.</p>";
                break;
            case 'shipped':
                $message .= "<p>Your order has been shipped! It should arrive within the estimated delivery time.</p>";
                break;
            case 'delivered':
                $message .= "<p>Your order has been delivered! We hope you're happy with your purchase.</p>";
                break;
            default:
                $message .= "<p>Thank you for shopping with us!</p>";
        }
        
        $message .= "
                    <p>If you have any questions, please contact our support team.</p>
                    <p style='text-align: center; margin-top: 20px;'>
                        <a href='https://urbantrends.com/contact' class='button'>Contact Support</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>Urban Trends Apparel © " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Urban Trends <no-reply@urbantrends.com>" . "\r\n";
        
        return mail($email, $subject, $message, $headers);
    }
    
    public function scheduleDelivery($order_id, $delivery_data) {
        try {
            if (!is_numeric($order_id) || $order_id <= 0) {
                throw new Exception("Invalid order ID");
            }
            
            $is_pickup = !empty($delivery_data['is_pickup']);
            
            if ($is_pickup && empty($delivery_data['pickup_location'])) {
                throw new Exception("Pickup location is required");
            }
            
            if (!$is_pickup) {
                if (empty($delivery_data['delivery_date'])) {
                    throw new Exception("Delivery date is required");
                }
                if (empty($delivery_data['carrier'])) {
                    throw new Exception("Carrier is required");
                }
            }
            
            $stmt = $this->db->prepare("SELECT shipping_id FROM shipping WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $shipping_id = $stmt->fetchColumn();
            
            if ($shipping_id) {
                $stmt = $this->db->prepare("UPDATE shipping SET 
                                           pickup_location = ?,
                                           estimated_delivery = ?,
                                           carrier = ?,
                                           tracking_number = ?,
                                           shipping_method = ?,
                                           status = 'processing',
                                           updated_at = NOW()
                                           WHERE order_id = ?");
                $stmt->execute([
                    $is_pickup ? $delivery_data['pickup_location'] : null,
                    $is_pickup ? null : $delivery_data['delivery_date'],
                    $is_pickup ? null : $delivery_data['carrier'],
                    $is_pickup ? null : $delivery_data['tracking_number'],
                    $is_pickup ? 'Pickup' : 'Delivery',
                    $order_id
                ]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO shipping 
                                           (order_id, pickup_location, estimated_delivery, carrier, tracking_number, shipping_method, status)
                                           VALUES (?, ?, ?, ?, ?, ?, 'processing')");
                $stmt->execute([
                    $order_id,
                    $is_pickup ? $delivery_data['pickup_location'] : null,
                    $is_pickup ? null : $delivery_data['delivery_date'],
                    $is_pickup ? null : $delivery_data['carrier'],
                    $is_pickup ? null : $delivery_data['tracking_number'],
                    $is_pickup ? 'Pickup' : 'Delivery'
                ]);
            }
            
            $this->updateOrderStatus($order_id, 'processing', 'Delivery scheduled');
            
            if ($is_pickup) {
                $this->sendPickupInstructions($order_id);
            }
            
            return true;
        } catch(PDOException $e) {
            error_log("Database error scheduling delivery: " . $e->getMessage());
            throw new Exception("Database error occurred");
        } catch(Exception $e) {
            error_log("Error scheduling delivery: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function sendPickupInstructions($order_id) {
        $stmt = $this->db->prepare("SELECT u.email, u.firstname, o.order_id as order_number, s.pickup_location
                                  FROM orders o 
                                  JOIN users u ON o.user_id = u.user_id
                                  JOIN shipping s ON o.order_id = s.order_id
                                  WHERE o.order_id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $subject = "Order #{$order['order_number']} Ready for Pickup";
            
            $message = "
            <html>
            <head>
                <title>Pickup Instructions</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #4361ee; color: white; padding: 15px; text-align: center; }
                    .content { padding: 20px; background-color: #f9f9f9; }
                    .footer { padding: 10px; text-align: center; font-size: 0.8em; color: #666; }
                    .info-box { background-color: #fff; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 4px; }
                    .button { display: inline-block; padding: 10px 20px; background-color: #4361ee; color: white; text-decoration: none; border-radius: 4px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Urban Trends Apparel</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$order['firstname']},</h2>
                        <p>Your order <strong>#{$order['order_number']}</strong> is ready for pickup at our store!</p>
                        
                        <div class='info-box'>
                            <h3>Pickup Information:</h3>
                            <p><strong>Location:</strong> {$order['pickup_location']}</p>
                            <p><strong>Pickup Hours:</strong> Monday-Friday, 9AM-6PM</p>
                        </div>
                        
                        <p>Please bring your order confirmation email and a valid ID when picking up your order.</p>
                        
                        <p style='text-align: center; margin-top: 20px;'>
                            <a href='https://urbantrends.com/contact' class='button'>Contact Support</a>
                        </p>
                    </div>
                    <div class='footer'>
                        <p>Urban Trends Apparel © " . date('Y') . "</p>
                    </div>
                </div>
            </body>
            </html>";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Urban Trends <no-reply@urbantrends.com>" . "\r\n";
            
            return mail($order['email'], $subject, $message, $headers);
        }
        return false;
    }
    
    public function getOrderStatusHistory($order_id) {
        try {
            $stmt = $this->db->prepare("SELECT status, changed_at as updated_at, notes
                                      FROM order_status_history 
                                      WHERE order_id = ? 
                                      ORDER BY changed_at DESC");
            $stmt->execute([$order_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting order status history: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAllOrders($status = null, $date_from = null, $date_to = null) {
        try {
            $query = "SELECT o.*, u.email, u.firstname, u.lastname, 
                     s.pickup_location, s.estimated_delivery, s.carrier, s.tracking_number,
                     p.payment_method, p.status as payment_status
                     FROM orders o 
                     JOIN users u ON o.user_id = u.user_id
                     LEFT JOIN shipping s ON o.order_id = s.order_id
                     LEFT JOIN payments p ON o.order_id = p.order_id";
            
            $conditions = [];
            $params = [];
            
            if ($status) {
                $conditions[] = "o.status = ?";
                $params[] = $status;
            }
            
            if ($date_from) {
                $conditions[] = "o.order_date >= ?";
                $params[] = $date_from;
            }
            
            if ($date_to) {
                $conditions[] = "o.order_date <= ?";
                $params[] = $date_to . ' 23:59:59';
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY o.order_date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting orders: " . $e->getMessage());
            return [];
        }
    }
    
    public function getOrderDetails($order_id) {
        try {
            $stmt = $this->db->prepare("SELECT o.*, u.email, u.firstname, u.lastname, u.address, u.phone,
                                      s.tracking_number, s.carrier, s.shipping_method, s.estimated_delivery, 
                                      s.actual_delivery, s.pickup_location, s.status as shipping_status,
                                      p.payment_method, p.status as payment_status, p.transaction_id,
                                      ds.preferred_date, ds.preferred_time_slot, ds.pickup_option, ds.pickup_location as customer_pickup_location
                                      FROM orders o 
                                      JOIN users u ON o.user_id = u.user_id
                                      LEFT JOIN shipping s ON o.order_id = s.order_id
                                      LEFT JOIN payments p ON o.order_id = p.order_id
                                      LEFT JOIN delivery_schedules ds ON o.order_id = ds.order_id
                                      WHERE o.order_id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return null;
            }
            
            $order['is_pickup'] = !empty($order['pickup_location']) ? 1 : 0;
            $order['delivery_date'] = $order['estimated_delivery'] ?? null;
            $order['delivery_time'] = null;
            
            if (!empty($order['preferred_date'])) {
                $order['customer_preferred_date'] = $order['preferred_date'];
                $order['customer_preferred_time'] = $order['preferred_time_slot'];
                $order['is_customer_pickup'] = $order['pickup_option'];
            }
            
            $stmt = $this->db->prepare("SELECT oi.*, p.name, p.image, (p.price + COALESCE(pv.price_adjustment, 0)) as unit_price, pv.size
                                      FROM order_items oi
                                      JOIN products p ON oi.product_id = p.product_id
                                      LEFT JOIN product_variations pv ON oi.variation_id = pv.variation_id
                                      WHERE oi.order_id = ?");
            $stmt->execute([$order_id]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($order['total_amount']) && !empty($order['items'])) {
                $total = 0;
                foreach ($order['items'] as $item) {
                    $total += $item['price'] * $item['quantity'];
                }
                $order['total_amount'] = $total;
            }
            
            $order['status_history'] = $this->getOrderStatusHistory($order_id);
            
            return $order;
        } catch(PDOException $e) {
            error_log("Error getting order details: " . $e->getMessage());
            return null;
        }
    }
    
    public function updatePayment($order_id, $payment_data) {
        try {
            if (!is_numeric($order_id) || $order_id <= 0) {
                throw new Exception("Invalid order ID");
            }

            if (empty($payment_data['payment_method'])) {
                throw new Exception("Payment method is required");
            }

            if (empty($payment_data['status'])) {
                throw new Exception("Payment status is required");
            }

            $stmt = $this->db->prepare("SELECT total_amount FROM orders WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $amount = $stmt->fetchColumn();

            if ($amount === false) {
                throw new Exception("Order not found");
            }

            $stmt = $this->db->prepare("SELECT payment_id FROM payments WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $payment_exists = $stmt->fetchColumn();

            if ($payment_exists) {
                $stmt = $this->db->prepare("UPDATE payments SET 
                                          payment_method = ?,
                                          status = ?,
                                          transaction_id = ?,
                                          updated_at = NOW()
                                          WHERE order_id = ?");
                $result = $stmt->execute([
                    $payment_data['payment_method'],
                    $payment_data['status'],
                    $payment_data['transaction_id'] ?? null,
                    $order_id
                ]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO payments 
                                          (order_id, amount, payment_method, status, transaction_id, payment_date)
                                          VALUES (?, ?, ?, ?, ?, NOW())");
                $result = $stmt->execute([
                    $order_id,
                    $amount,
                    $payment_data['payment_method'],
                    $payment_data['status'],
                    $payment_data['transaction_id'] ?? null
                ]);
            }

            if (!$result) {
                throw new Exception("Failed to update payment in database");
            }

            if ($payment_data['status'] == 'completed') {
                $this->updateOrderStatus($order_id, 'processing', 'Payment completed');
            }

            return true;
        } catch(PDOException $e) {
            error_log("Database error updating payment: " . $e->getMessage());
            throw new Exception("Database error occurred: " . $e->getMessage());
        } catch(Exception $e) {
            error_log("Error updating payment: " . $e->getMessage());
            throw $e;
        }
    }

    public function getDeliverySchedule($order_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM delivery_schedules WHERE order_id = ?");
            $stmt->execute([$order_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting delivery schedule: " . $e->getMessage());
            return null;
        }
    }

    public function updateDeliverySchedule($order_id, $delivery_data) {
        try {
            $stmt = $this->db->prepare("SELECT delivery_schedule_id FROM delivery_schedules WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                $stmt = $this->db->prepare("UPDATE delivery_schedules SET 
                                          preferred_date = ?,
                                          preferred_time_slot = ?,
                                          pickup_option = ?,
                                          pickup_location = ?
                                          WHERE order_id = ?");
                return $stmt->execute([
                    $delivery_data['preferred_date'] ?? null,
                    $delivery_data['preferred_time_slot'] ?? null,
                    $delivery_data['pickup_option'] ?? 0,
                    $delivery_data['pickup_location'] ?? null,
                    $order_id
                ]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO delivery_schedules 
                                          (order_id, preferred_date, preferred_time_slot, pickup_option, pickup_location)
                                          VALUES (?, ?, ?, ?, ?)");
                return $stmt->execute([
                    $order_id,
                    $delivery_data['preferred_date'] ?? null,
                    $delivery_data['preferred_time_slot'] ?? null,
                    $delivery_data['pickup_option'] ?? 0,
                    $delivery_data['pickup_location'] ?? null
                ]);
            }
        } catch(PDOException $e) {
            error_log("Error updating delivery schedule: " . $e->getMessage());
            return false;
        }
    }

    public function getOrderReviews($order_id) {
        try {
            // Get all products in the order
            $stmt = $this->db->prepare("SELECT oi.product_id, p.name as product_name, p.image as product_image
                                      FROM order_items oi
                                      JOIN products p ON oi.product_id = p.product_id
                                      WHERE oi.order_id = ?");
            $stmt->execute([$order_id]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $reviews = [];
            
            // Get reviews for each product in the order
            foreach ($products as $product) {
                $stmt = $this->db->prepare("SELECT pr.*, u.firstname, u.lastname, u.email
                                          FROM product_reviews pr
                                          JOIN users u ON pr.user_id = u.user_id
                                          WHERE pr.product_id = ? AND pr.order_id = ?
                                          ORDER BY pr.created_at DESC");
                $stmt->execute([$product['product_id'], $order_id]);
                $product_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($product_reviews)) {
                    $reviews[$product['product_id']] = [
                        'product' => $product,
                        'reviews' => $product_reviews
                    ];
                }
            }
            
            return $reviews;
        } catch(PDOException $e) {
            error_log("Error getting order reviews: " . $e->getMessage());
            return [];
        }
    }
    
    public function getProductReviews($product_id) {
        try {
            $stmt = $this->db->prepare("SELECT pr.*, u.firstname, u.lastname, u.email, o.order_id, o.created_at as order_date
                                      FROM product_reviews pr
                                      JOIN users u ON pr.user_id = u.user_id
                                      JOIN orders o ON pr.order_id = o.order_id
                                      WHERE pr.product_id = ?
                                      ORDER BY pr.created_at DESC");
            $stmt->execute([$product_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting product reviews: " . $e->getMessage());
            return [];
        }
    }
    
    public function approveReview($review_id) {
        try {
            $stmt = $this->db->prepare("UPDATE product_reviews SET is_approved = 1 WHERE review_id = ?");
            return $stmt->execute([$review_id]);
        } catch(PDOException $e) {
            error_log("Error approving review: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteReview($review_id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM product_reviews WHERE review_id = ?");
            return $stmt->execute([$review_id]);
        } catch(PDOException $e) {
            error_log("Error deleting review: " . $e->getMessage());
            return false;
        }
    }

    public function updateOrderLocation($order_id, $latitude, $longitude, $location_notes = null) {
        try {
            $stmt = $this->db->prepare("UPDATE orders SET 
                latitude = ?, 
                longitude = ?, 
                location_notes = ?,
                location_updated_at = NOW()
                WHERE order_id = ?");
            
            $result = $stmt->execute([$latitude, $longitude, $location_notes, $order_id]);
            
            if ($result) {
                // Record this update in the status history
                $this->recordStatusHistory($order_id, 'shipped', 'Location updated: ' . $location_notes);
            }
            
            return $result;
        } catch(PDOException $e) {
            error_log("Error updating order location: " . $e->getMessage());
            return false;
        }
    }
}

$orderDelivery = new OrderDelivery($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_status'])) {
            $order_id = (int)$_POST['order_id'];
            $status = $_POST['status'];
            $notes = $_POST['notes'] ?? null;
            
            if ($orderDelivery->updateOrderStatus($order_id, $status, $notes)) {
                $_SESSION['success_message'] = "Order status updated successfully!";
            }
        }
        
        if (isset($_POST['update_location'])) {
            $order_id = (int)$_POST['order_id'];
            $latitude = (float)$_POST['latitude'];
            $longitude = (float)$_POST['longitude'];
            $location_notes = $_POST['location_notes'] ?? null;
            
            if ($orderDelivery->updateOrderLocation($order_id, $latitude, $longitude, $location_notes)) {
                $_SESSION['success_message'] = "Order location updated successfully!";
            }
        }
        
        if (isset($_POST['schedule_delivery'])) {
            $order_id = (int)$_POST['order_id'];
            $is_pickup = isset($_POST['is_pickup']) ? 1 : 0;
            
            $delivery_data = [
                'is_pickup' => $is_pickup,
                'pickup_location' => $is_pickup ? ($_POST['pickup_location'] ?? '') : null,
                'delivery_date' => !$is_pickup ? ($_POST['delivery_date'] ?? null) : null,
                'carrier' => !$is_pickup ? ($_POST['carrier'] ?? null) : null,
                'tracking_number' => !$is_pickup ? ($_POST['tracking_number'] ?? null) : null
            ];
            
            if ($orderDelivery->scheduleDelivery($order_id, $delivery_data)) {
                $_SESSION['success_message'] = "Delivery scheduled successfully!";
            }
        }
        
        if (isset($_POST['update_payment'])) {
            $order_id = (int)$_POST['order_id'];
            
            $payment_data = [
                'payment_method' => $_POST['payment_method'],
                'status' => $_POST['payment_status'],
                'transaction_id' => $_POST['transaction_id'] ?? null
            ];

            if ($orderDelivery->updatePayment($order_id, $payment_data)) {
                $_SESSION['success_message'] = "Payment information updated successfully!";
            }
        }
        
    } catch(Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header("Location: orders.php" . (isset($_GET['id']) ? '?id='.$_GET['id'] : ''));
    exit;
}

$order = null;
if (isset($_GET['id'])) {
    $order = $orderDelivery->getOrderDetails($_GET['id']);
    if (!$order) {
        $_SESSION['error_message'] = "Order not found.";
        header("Location: orders.php");
        exit;
    }
}

$status_filter = $_GET['status'] ?? null;
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;

$orders = $orderDelivery->getAllOrders($status_filter, $date_from, $date_to);

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$time_slot_map = [
    'morning' => 'Morning (8AM-12PM)',
    'afternoon' => 'Afternoon (1PM-5PM)',
    'evening' => 'Evening (6PM-9PM)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Trends Apparel - Order Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --success-color: #4cc9f0;
            --dark-color: #2b2d42;
            --light-color: #f8f9fa;
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            width: var(--sidebar-width);
            background-color: var(--dark-color);
            color: white;
            padding: 20px 0;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            z-index: 100;
        }
        
        .admin-sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .admin-sidebar-header h2 {
            display: flex;
            align-items: center;
            font-size: 1.2rem;
        }
        
        .admin-sidebar-header h2 i {
            margin-right: 10px;
            color: var(--accent-color);
        }
        
        .admin-sidebar ul {
            list-style: none;
        }
        
        .admin-sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .admin-sidebar ul li a:hover, 
        .admin-sidebar ul li a.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-left: 3px solid var(--accent-color);
        }
        
        .admin-sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .admin-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .admin-header h2 {
            color: var(--dark-color);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .admin-header h2 i {
            margin-right: 10px;
        }
        
        .admin-actions a {
            color: var(--dark-color);
            text-decoration: none;
            margin-left: 15px;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }
        
        .admin-actions a:hover {
            color: var(--danger-color);
        }
        
        .admin-actions a i {
            margin-right: 5px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f8f9fa;
            color: #555;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status.pending {
            background-color: #FFF3CD;
            color: #856404;
        }
        
        .status.processing {
            background-color: #CCE5FF;
            color: #004085;
        }
        
        .status.shipped {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .status.delivered {
            background-color: #D1ECF1;
            color: #0C5460;
        }
        
        .status.cancelled {
            background-color: #F8D7DA;
            color: #721C24;
        }
        
        .status.return_requested,
        .status.returned,
        .status.refunded {
            background-color: #E2E3E5;
            color: #383D41;
        }
        
        .payment-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            display: inline-block;
        }
        
        .payment-status.pending {
            background-color: #FFF3CD;
            color: #856404;
        }
        
        .payment-status.completed {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .payment-status.failed {
            background-color: #F8D7DA;
            color: #721C24;
        }
        
        .payment-status.refunded {
            background-color: #E2E3E5;
            color: #383D41;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #d63384;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #3aa8d1;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .order-details {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-header h3 {
            color: var(--dark-color);
        }
        
        .order-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .order-meta-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        
        .customer-preferred {
            border: 1px solid #4361ee;
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .customer-preferred h4 {
            color: #4361ee;
        }
        
        .order-meta-item h4 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .order-meta-item p {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 3px;
        }
        
        .order-meta-item p:last-child {
            margin-bottom: 0;
        }
        
        .order-items {
            margin-top: 20px;
        }
        
        .order-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            margin-right: 15px;
            border-radius: 4px;
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .order-item-meta {
            color: #666;
            font-size: 0.9rem;
        }
        
        .order-item-price {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .order-summary {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .order-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .order-summary-row.total {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark-color);
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .status-history {
            margin-top: 30px;
        }
        
        .status-history h4 {
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        .status-timeline {
            position: relative;
            padding-left: 20px;
            border-left: 2px solid #eee;
        }
        
        .status-event {
            position: relative;
            padding-bottom: 20px;
            padding-left: 20px;
        }
        
        .status-event:last-child {
            padding-bottom: 0;
        }
        
        .status-event::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--primary-color);
        }
        
        .status-event-date {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .status-event-status {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .status-event-notes {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .delivery-schedule, .payment-update, .status-update {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-top: 20px;
        }
        
        .delivery-schedule h4, .payment-update h4, .status-update h4 {
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .admin-sidebar-header h2 span,
            .admin-sidebar ul li a span {
                display: none;
            }
            
            .admin-sidebar ul li a {
                justify-content: center;
                padding: 12px 0;
            }
            
            .admin-sidebar ul li a i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .admin-main {
                margin-left: 70px;
            }
            
            .order-meta {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: static;
                display: flex;
                flex-direction: column;
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .admin-sidebar-header {
                display: none;
            }
            
            .admin-sidebar ul {
                display: flex;
                overflow-x: auto;
            }
            
            .admin-sidebar ul li {
                flex: 0 0 auto;
            }
            
            .admin-sidebar ul li a {
                padding: 10px 15px;
                border-left: none;
                border-bottom: 3px solid transparent;
            }
            
            .admin-sidebar ul li a:hover, 
            .admin-sidebar ul li a.active {
                border-left: none;
                border-bottom: 3px solid var(--accent-color);
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Product Reviews Styles */
        .product-reviews {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .product-reviews h4 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .product-reviews h4 i {
            color: #f1c40f;
        }

        .no-reviews {
            color: #7f8c8d;
            font-style: italic;
            text-align: center;
            padding: 1rem;
        }

        .product-review-group {
            margin-bottom: 2rem;
            border-bottom: 1px solid #ecf0f1;
            padding-bottom: 1.5rem;
        }

        .product-review-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .product-review-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .product-review-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        .product-review-header h5 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .review-card {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .reviewer-info {
            display: flex;
            flex-direction: column;
        }

        .reviewer-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .review-date {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .review-rating {
            color: #f1c40f;
        }

        .review-title {
            color: #2c3e50;
            margin: 0.5rem 0;
            font-size: 1rem;
        }

        .review-content {
            color: #34495e;
            line-height: 1.5;
            margin: 0.75rem 0;
        }

        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid #ecf0f1;
        }

        .review-status {
            font-size: 0.85rem;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
        }

        .review-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .review-status.approved {
            background: #d4edda;
            color: #155724;
        }

        .review-actions {
            display: flex;
            gap: 0.5rem;
        }

        .review-actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        .review-actions .btn i {
            margin-right: 0.25rem;
        }

        /* Order Tracking Map Styles */
        .order-tracking-map {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .order-tracking-map h4 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-tracking-map h4 i {
            color: #e74c3c;
        }

        .map-container {
            margin-bottom: 1rem;
            border: 1px solid #ecf0f1;
            border-radius: 8px;
            overflow: hidden;
        }

        .map-controls {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
        }

        .map-controls .form-group {
            margin-bottom: 1rem;
        }

        .map-controls label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .map-controls textarea {
            resize: vertical;
            min-height: 60px;
        }

        .map-controls .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .price-breakdown {
            display: inline-block;
            margin-left: 5px;
            color: var(--text-muted);
            font-size: 0.9em;
        }
        
        .order-item-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-muted);
            font-size: 0.9em;
        }
        
        .order-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-summary-row.total {
            font-weight: bold;
            font-size: 1.1em;
            border-bottom: none;
            margin-top: 10px;
        }
    </style>
    <!-- Add this before the closing </head> tag -->
    <!-- PASTE YOUR GOOGLE MAPS API KEY BELOW (replace YOUR_GOOGLE_MAPS_API_KEY) -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAgLyBmsBfFsoW7zzmIS8Aj-XRk2-mwYd8&callback=initMap" async defer></script>
    <script>
        // Replace YOUR_GOOGLE_MAPS_API_KEY above with your actual Google Maps API key
        // To get an API key:
        // 1. Go to https://console.cloud.google.com/
        // 2. Create a new project or select an existing one
        // 3. Enable the Maps JavaScript API
        // 4. Create credentials (API key)
        // 5. Restrict the API key to your domain for security
        // 6. Replace YOUR_GOOGLE_MAPS_API_KEY with your actual key
        
        function initMap() {
            // Default to Bohol, Philippines if no coordinates are set
            const defaultLocation = { lat: 9.8499, lng: 124.1435 };
            const orderLocation = {
                lat: <?php echo isset($order['latitude']) ? $order['latitude'] : '9.8499'; ?>,
                lng: <?php echo isset($order['longitude']) ? $order['longitude'] : '124.1435'; ?>
            };
            
            const map = new google.maps.Map(document.getElementById('orderMap'), {
                zoom: 12,
                center: orderLocation,
                restriction: {
                    latLngBounds: {
                        north: 10.5,
                        south: 9.0,
                        east: 125.0,
                        west: 123.0
                    },
                    strictBounds: true
                }
            });
            
            const marker = new google.maps.Marker({
                position: orderLocation,
                map: map,
                draggable: true,
                title: 'Order Location'
            });
            
            // Update hidden inputs when marker is dragged
            marker.addListener('dragend', function() {
                const position = marker.getPosition();
                document.getElementById('latitude').value = position.lat();
                document.getElementById('longitude').value = position.lng();
            });
            
            // Add click listener to map
            map.addListener('click', function(event) {
                marker.setPosition(event.latLng);
                document.getElementById('latitude').value = event.latLng.lat();
                document.getElementById('longitude').value = event.latLng.lng();
            });
        }
    </script>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="admin-sidebar-header">
            <h2><i class="fas fa-crown"></i> <span>Admin Panel</span></h2>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="products.php"><i class="fas fa-tshirt"></i> <span>Products</span></a></li>
            <li><a href="orders.php" class="active"><i class="fas fa-shopping-bag"></i> <span>Orders</span></a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> <span>Customers</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <div class="admin-header">
            <h2><i class="fas fa-shopping-bag"></i> <?php echo isset($order) ? "Order #" . $order['order_id'] : "Order Management"; ?></h2>
            <div class="admin-actions">
                <a href="../index.php"><i class="fas fa-home"></i> View Site</a>
                <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($order)): ?>
            <!-- Single Order View -->
            <div class="order-details">
                <div class="order-header">
                    <h3>Order Details</h3>
                    <div>
                        <span class="status <?php echo $order['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </div>
                </div>
                
                <div class="order-meta">
                    <div class="order-meta-item">
                        <h4>Customer</h4>
                        <p><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></p>
                        <p><?php echo htmlspecialchars($order['email']); ?></p>
                        <p><?php echo htmlspecialchars($order['phone'] ?? 'Not provided'); ?></p>
                    </div>
                    
                    <div class="order-meta-item">
                        <h4>Shipping Address</h4>
                        <p><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                    </div>
                    
                    <?php if (!empty($order['customer_preferred_date'])): ?>
                    <div class="order-meta-item customer-preferred">
                        <h4>Customer's Preferred Delivery</h4>
                        <?php if ($order['is_customer_pickup']): ?>
                            <p><strong>Type:</strong> Customer Pickup</p>
                            <?php if (!empty($order['customer_pickup_location'])): ?>
                                <p><strong>Pickup Location:</strong> <?php echo htmlspecialchars($order['customer_pickup_location']); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p><strong>Type:</strong> Delivery</p>
                            <p><strong>Preferred Date:</strong> <?php echo date('M d, Y', strtotime($order['customer_preferred_date'])); ?></p>
                            <p><strong>Time Slot:</strong> <?php echo $time_slot_map[$order['customer_preferred_time']] ?? ucfirst($order['customer_preferred_time']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="order-meta-item">
                        <h4>Order Information</h4>
                        <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></p>
                        <p><strong>Number:</strong> <?php echo $order['order_id']; ?></p>
                        <p><strong>Payment:</strong> 
                            <?php if (!empty($order['payment_method'])): ?>
                                <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?>
                                <span class="payment-status <?php echo $order['payment_status'] ?? 'pending'; ?>">
                                    (<?php echo ucfirst($order['payment_status'] ?? 'pending'); ?>)
                                </span>
                                <?php if (!empty($order['transaction_id'])): ?>
                                    <p><strong>Transaction ID:</strong> <?php echo $order['transaction_id']; ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                Not recorded
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($order['pickup_location']) || !empty($order['estimated_delivery'])): ?>
                        <div class="order-meta-item">
                            <h4>Delivery Information</h4>
                            <?php if (!empty($order['pickup_location'])): ?>
                                <p><strong>Type:</strong> In-Store Pickup</p>
                                <p><strong>Pickup Location:</strong> <?php echo htmlspecialchars($order['pickup_location']); ?></p>
                            <?php else: ?>
                                <p><strong>Type:</strong> Delivery</p>
                                <?php if (!empty($order['estimated_delivery'])): ?>
                                    <p><strong>Estimated Delivery:</strong> <?php echo date('M d, Y', strtotime($order['estimated_delivery'])); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($order['carrier'])): ?>
                                    <p><strong>Carrier:</strong> <?php echo htmlspecialchars($order['carrier']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($order['tracking_number'])): ?>
                                    <p><strong>Tracking Number:</strong> <?php echo htmlspecialchars($order['tracking_number']); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($order['shipping_status'])): ?>
                                <p><strong>Shipping Status:</strong> 
                                    <span class="status <?php echo $order['shipping_status']; ?>">
                                        <?php echo ucfirst($order['shipping_status']); ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="order-items">
                    <h4>Order Items</h4>
                    <?php foreach ($order['items'] as $item): ?>
                        <div class="order-item">
                            <img src="../assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="order-item-image">
                            <div class="order-item-details">
                                <div class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="order-item-meta">
                                    Size: <?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?>,
                                    Quantity: <?php echo $item['quantity']; ?> × 
                                    <span class="price-breakdown">
                                        Original Price: ₱<?php echo number_format($item['unit_price'], 2); ?> +
                                        Shipping: ₱<?php echo number_format(50, 2); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="order-item-price">
                                ₱<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Product Reviews Section -->
                <div class="product-reviews">
                    <h4><i class="fas fa-star"></i> Product Reviews</h4>
                    <?php 
                    // Get reviews for this order
                    $order_reviews = $orderDelivery->getOrderReviews($order['order_id']);
                    
                    if (empty($order_reviews)): 
                    ?>
                        <p class="no-reviews">No reviews submitted for products in this order yet.</p>
                    <?php else: ?>
                        <?php foreach ($order_reviews as $product_id => $review_data): ?>
                            <div class="product-review-group">
                                <div class="product-review-header">
                                    <img src="../assets/images/products/<?php echo htmlspecialchars($review_data['product']['product_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($review_data['product']['product_name']); ?>" 
                                         class="product-review-image">
                                    <h5><?php echo htmlspecialchars($review_data['product']['product_name']); ?></h5>
                                </div>
                                
                                <?php foreach ($review_data['reviews'] as $review): ?>
                                    <div class="review-card">
                                        <div class="review-header">
                                            <div class="reviewer-info">
                                                <span class="reviewer-name"><?php echo htmlspecialchars($review['firstname'] . ' ' . $review['lastname']); ?></span>
                                                <span class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                            </div>
                                            <div class="review-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($review['title'])): ?>
                                            <h6 class="review-title"><?php echo htmlspecialchars($review['title']); ?></h6>
                                        <?php endif; ?>
                                        
                                        <div class="review-content">
                                            <?php echo nl2br(htmlspecialchars($review['review'])); ?>
                                        </div>
                                        
                                        <div class="review-footer">
                                            <span class="review-status <?php echo $review['is_approved'] ? 'approved' : 'pending'; ?>">
                                                <?php echo $review['is_approved'] ? 'Approved' : 'Pending Approval'; ?>
                                            </span>
                                            
                                            <div class="review-actions">
                                                <?php if (!$review['is_approved']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                                        <button type="submit" name="approve_review" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                                    <button type="submit" name="delete_review" class="btn btn-sm btn-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this review?');">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="order-summary">
                    <div class="order-summary-row">
                        <span>Subtotal:</span>
                        <span>₱<?php echo number_format($order['total_amount'] - ($shipping = 50 * array_sum(array_column($order['items'], 'quantity'))), 2); ?></span>
                    </div>
                    <div class="order-summary-row">
                        <span>Shipping:</span>
                        <span>₱<?php echo number_format($shipping, 2); ?></span>
                    </div>
                    <div class="order-summary-row total">
                        <span>Total:</span>
                        <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>
                
                <!-- Payment Update Form -->
                <div class="payment-update">
                    <h4>Update Payment Information</h4>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" class="form-control" required>
                                <option value="">Select Payment Method</option>
                                <option value="cod" <?php echo isset($order['payment_method']) && $order['payment_method'] == 'cod' ? 'selected' : ''; ?>>Cash on Delivery</option>
                                <option value="gcash" <?php echo isset($order['payment_method']) && $order['payment_method'] == 'gcash' ? 'selected' : ''; ?>>GCash</option>
                                <option value="paypal" <?php echo isset($order['payment_method']) && $order['payment_method'] == 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                <option value="credit_card" <?php echo isset($order['payment_method']) && $order['payment_method'] == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="debit_card" <?php echo isset($order['payment_method']) && $order['payment_method'] == 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                                <option value="wallet" <?php echo isset($order['payment_method']) && $order['payment_method'] == 'wallet' ? 'selected' : ''; ?>>Wallet</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_status">Payment Status</label>
                            <select id="payment_status" name="payment_status" class="form-control" required>
                                <option value="pending" <?php echo isset($order['payment_status']) && $order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo isset($order['payment_status']) && $order['payment_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo isset($order['payment_status']) && $order['payment_status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo isset($order['payment_status']) && $order['payment_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="transaction_id">Transaction ID (if applicable)</label>
                            <input type="text" id="transaction_id" name="transaction_id" 
                                   value="<?php echo htmlspecialchars($order['transaction_id'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                        
                        <button type="submit" name="update_payment" class="btn btn-success">
                            <i class="fas fa-credit-card"></i> Update Payment
                        </button>
                    </form>
                </div>
                
                <!-- Status Update Form -->
                <div class="status-update">
                    <h4>Update Order Status</h4>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="return_requested" <?php echo $order['status'] == 'return_requested' ? 'selected' : ''; ?>>Return Requested</option>
                                <option value="returned" <?php echo $order['status'] == 'returned' ? 'selected' : ''; ?>>Returned</option>
                                <option value="refunded" <?php echo $order['status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes (optional)</label>
                            <textarea id="notes" name="notes" class="form-control" placeholder="Add any notes about this status change..."></textarea>
                        </div>
                        
                        <button type="submit" name="update_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </form>
                </div>
                
                <!-- Delivery Scheduling -->
                <?php if ($order['status'] != 'cancelled' && $order['status'] != 'delivered'): ?>
                    <div class="delivery-schedule">
                        <h4>Schedule Delivery</h4>
                        <?php if (!empty($order['customer_preferred_date'])): ?>
                            <div class="alert alert-info" style="margin-bottom: 20px;">
                                <i class="fas fa-info-circle"></i> Customer requested:
                                <?php if ($order['is_customer_pickup']): ?>
                                    Pickup at <?php echo htmlspecialchars($order['customer_pickup_location']); ?>
                                <?php else: ?>
                                    Delivery on <?php echo date('M d, Y', strtotime($order['customer_preferred_date'])); ?> 
                                    (<?php echo $time_slot_map[$order['customer_preferred_time']] ?? ucfirst($order['customer_preferred_time']); ?>)
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_pickup" id="is_pickup" <?php echo !empty($order['pickup_location']) ? 'checked' : ''; ?>>
                                    In-Store Pickup
                                </label>
                            </div>
                            
                            <div class="form-group" id="pickup_location_group" style="<?php echo empty($order['pickup_location']) ? 'display: none;' : ''; ?>">
                                <label for="pickup_location">Pickup Location</label>
                                <input type="text" id="pickup_location" name="pickup_location" 
                                       value="<?php echo htmlspecialchars($order['pickup_location'] ?? ''); ?>" 
                                       class="form-control" required>
                            </div>
                            
                            <div class="form-group" id="delivery_info_group" style="<?php echo !empty($order['pickup_location']) ? 'display: none;' : ''; ?>">
                                <label for="delivery_date">Estimated Delivery Date</label>
                                <input type="date" id="delivery_date" name="delivery_date" 
                                       value="<?php echo isset($order['estimated_delivery']) ? date('Y-m-d', strtotime($order['estimated_delivery'])) : ''; ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" class="form-control" required>
                                
                                <label for="carrier" style="margin-top: 10px;">Carrier</label>
                                <select id="carrier" name="carrier" class="form-control" required>
                                    <option value="">Select Carrier</option>
                                    <option value="LBC" <?php echo isset($order['carrier']) && $order['carrier'] == 'LBC' ? 'selected' : ''; ?>>LBC</option>
                                    <option value="J&T Express" <?php echo isset($order['carrier']) && $order['carrier'] == 'J&T Express' ? 'selected' : ''; ?>>J&T Express</option>
                                    <option value="Ninja Van" <?php echo isset($order['carrier']) && $order['carrier'] == 'Ninja Van' ? 'selected' : ''; ?>>Ninja Van</option>
                                    <option value="DHL" <?php echo isset($order['carrier']) && $order['carrier'] == 'DHL' ? 'selected' : ''; ?>>DHL</option>
                                    <option value="FedEx" <?php echo isset($order['carrier']) && $order['carrier'] == 'FedEx' ? 'selected' : ''; ?>>FedEx</option>
                                    <option value="UPS" <?php echo isset($order['carrier']) && $order['carrier'] == 'UPS' ? 'selected' : ''; ?>>UPS</option>
                                </select>
                                
                                <label for="tracking_number" style="margin-top: 10px;">Tracking Number</label>
                                <input type="text" id="tracking_number" name="tracking_number" 
                                       value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>" 
                                       class="form-control">
                            </div>
                            
                            <button type="submit" name="schedule_delivery" class="btn btn-success">
                                <i class="fas fa-calendar-check"></i> Schedule
                            </button>
                        </form>
                    </div>
                    
                    <script>
                        document.getElementById('is_pickup').addEventListener('change', function() {
                            const isPickup = this.checked;
                            document.getElementById('pickup_location_group').style.display = isPickup ? 'block' : 'none';
                            document.getElementById('delivery_info_group').style.display = isPickup ? 'none' : 'block';
                            
                            document.getElementById('pickup_location').required = isPickup;
                            document.getElementById('delivery_date').required = !isPickup;
                            document.getElementById('carrier').required = !isPickup;
                        });
                    </script>
                <?php endif; ?>
                
                <!-- Status History -->
                <div class="status-history">
                    <h4>Status History</h4>
                    <div class="status-timeline">
                        <?php if (empty($order['status_history'])): ?>
                            <p>No status history available.</p>
                        <?php else: ?>
                            <?php foreach ($order['status_history'] as $history): ?>
                                <div class="status-event">
                                    <div class="status-event-date">
                                        <?php echo date('M d, Y H:i', strtotime($history['updated_at'])); ?>
                                    </div>
                                    <div class="status-event-status">
                                        <?php echo ucfirst(str_replace('_', ' ', $history['status'])); ?>
                                        <?php if (!empty($history['notes'])): ?>
                                            <div class="status-event-notes">
                                                <?php echo htmlspecialchars($history['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Tracking Map -->
                <?php if ($order['status'] == 'shipped'): ?>
                <div class="order-tracking-map">
                    <h4><i class="fas fa-map-marker-alt"></i> Order Location</h4>
                    <div class="map-container">
                        <div id="orderMap" style="height: 400px; width: 100%; border-radius: 8px;"></div>
                    </div>
                    <div class="map-controls mt-3">
                        <form method="POST" id="updateLocationForm">
                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                            <input type="hidden" name="latitude" id="latitude" value="<?php echo $order['latitude'] ?? ''; ?>">
                            <input type="hidden" name="longitude" id="longitude" value="<?php echo $order['longitude'] ?? ''; ?>">
                            <div class="form-group">
                                <label for="location_notes">Location Notes</label>
                                <textarea id="location_notes" name="location_notes" class="form-control" rows="2" 
                                    placeholder="Add notes about the current location..."><?php echo htmlspecialchars($order['location_notes'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="update_location" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Location
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 20px;">
                    <a href="orders.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Order List View -->
            <div class="filters">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control" onchange="applyFilters()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="return_requested" <?php echo $status_filter == 'return_requested' ? 'selected' : ''; ?>>Return Requested</option>
                        <option value="returned" <?php echo $status_filter == 'returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="refunded" <?php echo $status_filter == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" class="form-control" onchange="applyFilters()">
                </div>
                
                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" class="form-control" onchange="applyFilters()">
                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()" style="height: 38px;">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <?php if (empty($orders)): ?>
                    <p>No orders found matching your criteria.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Delivery</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['email']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status <?php echo $order['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['payment_method'])): ?>
                                            <span class="payment-status <?php echo $order['payment_status'] ?? 'pending'; ?>">
                                                <?php echo ucfirst($order['payment_status'] ?? 'pending'); ?>
                                            </span>
                                        <?php else: ?>
                                            Not recorded
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['pickup_location'])): ?>
                                            Pickup
                                        <?php elseif (!empty($order['estimated_delivery'])): ?>
                                            Delivery
                                        <?php else: ?>
                                            Not scheduled
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="orders.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function applyFilters() {
            const status = document.getElementById('status').value;
            const date_from = document.getElementById('date_from').value;
            const date_to = document.getElementById('date_to').value;
            
            let url = 'orders.php?';
            
            if (status) url += `status=${status}&`;
            if (date_from) url += `date_from=${date_from}&`;
            if (date_to) url += `date_to=${date_to}&`;
            
            window.location.href = url.slice(0, -1);
        }
        
        function resetFilters() {
            window.location.href = 'orders.php';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            if (dateFrom) {
                dateFrom.addEventListener('change', function() {
                    if (dateTo) {
                        dateTo.min = this.value;
                        if (dateTo.value && dateTo.value < this.value) {
                            dateTo.value = this.value;
                        }
                    }
                });
            }
            
            if (dateFrom && dateFrom.value && dateTo) {
                dateTo.min = dateFrom.value;
            }
        });
    </script>
</body>
</html>