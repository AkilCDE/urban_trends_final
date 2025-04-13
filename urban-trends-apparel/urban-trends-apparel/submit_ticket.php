<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit;
}

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$subject = trim($_POST['subject']);
$message = trim($_POST['message']);
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;

// Validate input
if (empty($subject) || empty($message)) {
    $_SESSION['error_message'] = "Subject and message are required";
    header("Location: profile.php#support");
    exit;
}

// Verify order belongs to user if provided
if ($order_id) {
    $stmt = $db->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    if (!$stmt->fetch()) {
        $_SESSION['error_message'] = "Invalid order specified";
        header("Location: profile.php#support");
        exit;
    }
}

try {
    // Insert ticket
    $stmt = $db->prepare("INSERT INTO support_tickets (user_id, order_id, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $order_id, $subject, $message]);
    $ticket_id = $db->lastInsertId();

    // Send email notification to admin
    $admin_email = "admin@urbantrends.com";
    $user_email = $auth->getCurrentUser()['email'];
    
    $email_subject = "New Support Ticket #$ticket_id: $subject";
    $email_message = "A new support ticket has been submitted:\n\n";
    $email_message .= "Ticket ID: $ticket_id\n";
    $email_message .= "User: $user_email\n";
    if ($order_id) $email_message .= "Order: #$order_id\n";
    $email_message .= "Subject: $subject\n";
    $email_message .= "Message:\n$message\n\n";
    $email_message .= "Please respond to this ticket at your earliest convenience.";
    
    // Insert email notification
    $stmt = $db->prepare("INSERT INTO email_notifications (recipient_email, subject, message, status) VALUES (?, ?, ?, 'queued')");
    $stmt->execute([$admin_email, $email_subject, $email_message]);

    // Log the ticket creation
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        'create',
        'support_tickets',
        $ticket_id,
        json_encode(['subject' => $subject, 'status' => 'open'])
    ]);

    $_SESSION['success_message'] = "Your support ticket has been submitted successfully! Ticket ID: #$ticket_id";
    header("Location: profile.php#support");
    exit;

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Failed to submit ticket: " . $e->getMessage();
    header("Location: profile.php#support");
    exit;
}