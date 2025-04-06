<?php
require_once 'config.php';

class SupportSystem {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function createTicket($user_id, $subject, $message, $order_id = null) {
        $stmt = $this->db->prepare("INSERT INTO support_tickets 
                                   (user_id, order_id, subject, message) 
                                   VALUES (?, ?, ?, ?)");
        $success = $stmt->execute([$user_id, $order_id, $subject, $message]);
        
        if ($success) {
            $ticket_id = $this->db->lastInsertId();
            $this->notifySupportTeam($ticket_id);
            return $ticket_id;
        }
        
        return false;
    }
    
    public function addResponse($ticket_id, $user_id, $message, $is_admin = false) {
        $stmt = $this->db->prepare("INSERT INTO ticket_responses 
                                   (ticket_id, user_id, message, is_admin_response) 
                                   VALUES (?, ?, ?, ?)");
        $success = $stmt->execute([$ticket_id, $user_id, $message, $is_admin]);
        
        if ($success) {
            // Update ticket status and timestamp
            $new_status = $is_admin ? 'in_progress' : 'open';
            $stmt = $this->db->prepare("UPDATE support_tickets 
                                       SET status = ?, updated_at = NOW() 
                                       WHERE id = ?");
            $stmt->execute([$new_status, $ticket_id]);
            
            // Notify the other party
            $this->notifyTicketUpdate($ticket_id, $user_id);
            
            return true;
        }
        
        return false;
    }
    
    public function getTicket($ticket_id) {
        $stmt = $this->db->prepare("SELECT st.*, u.email, u.firstname, u.lastname 
                                   FROM support_tickets st 
                                   JOIN users u ON st.user_id = u.id 
                                   WHERE st.id = ?");
        $stmt->execute([$ticket_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getTicketResponses($ticket_id) {
        $stmt = $this->db->prepare("SELECT tr.*, u.firstname, u.lastname, u.email 
                                   FROM ticket_responses tr 
                                   JOIN users u ON tr.user_id = u.id 
                                   WHERE tr.ticket_id = ? 
                                   ORDER BY tr.created_at ASC");
        $stmt->execute([$ticket_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserTickets($user_id) {
        $stmt = $this->db->prepare("SELECT st.*, 
                                   (SELECT COUNT(*) FROM ticket_responses tr 
                                    WHERE tr.ticket_id = st.id AND tr.is_admin_response = 1 
                                    AND tr.created_at > (SELECT MAX(created_at) FROM support_tickets 
                                                         WHERE user_id = ? AND id = st.id)) AS new_responses 
                                   FROM support_tickets st 
                                   WHERE st.user_id = ? 
                                   ORDER BY st.created_at DESC");
        $stmt->execute([$user_id, $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function notifySupportTeam($ticket_id) {
        $ticket = $this->getTicket($ticket_id);
        $subject = "New Support Ticket: " . $ticket['subject'];
        $message = "A new support ticket has been created by " . $ticket['firstname'] . " " . $ticket['lastname'] . ".\n\n";
        $message .= "Subject: " . $ticket['subject'] . "\n";
        $message .= "Message: " . $ticket['message'] . "\n\n";
        $message .= "Please respond to the ticket in the admin panel.";
        
        // In a real app, this would be sent to all admin emails
        $stmt = $this->db->prepare("INSERT INTO email_notifications 
                                   (recipient_email, subject, message, status) 
                                   SELECT email, ?, ?, 'queued' 
                                   FROM users WHERE is_admin = 1");
        $stmt->execute([$subject, $message]);
    }
    
    private function notifyTicketUpdate($ticket_id, $responder_id) {
        $ticket = $this->getTicket($ticket_id);
        $responses = $this->getTicketResponses($ticket_id);
        $latest_response = end($responses);
        
        if ($latest_response['user_id'] == $ticket['user_id']) {
            // Customer responded, notify admin
            $subject = "Customer Response to Ticket #" . $ticket_id;
            $message = "The customer has responded to ticket #" . $ticket_id . ".\n\n";
            $message .= "Latest response: " . $latest_response['message'];
            
            $stmt = $this->db->prepare("INSERT INTO email_notifications 
                                       (recipient_email, subject, message, status) 
                                       SELECT email, ?, ?, 'queued' 
                                       FROM users WHERE is_admin = 1");
            $stmt->execute([$subject, $message]);
        } else {
            // Admin responded, notify customer
            $subject = "Response to Your Support Ticket #" . $ticket_id;
            $message = "We have responded to your support ticket #" . $ticket_id . ".\n\n";
            $message .= "Response: " . $latest_response['message'] . "\n\n";
            $message .= "You can reply to this email or through the support portal.";
            
            $stmt = $this->db->prepare("INSERT INTO email_notifications 
                                       (recipient_email, subject, message, status) 
                                       VALUES (?, ?, ?, 'queued')");
            $stmt->execute([$ticket['email'], $subject, $message]);
        }
    }
}

$support = new SupportSystem($db);

// Example API endpoint for creating tickets
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $order_id = $_POST['order_id'] ?? null;
    
    if (empty($subject) || empty($message)) {
        $_SESSION['error'] = "Subject and message are required";
        header("Location: contact.php");
        exit;
    }
    
    $ticket_id = $support->createTicket($user_id, $subject, $message, $order_id);
    
    if ($ticket_id) {
        $_SESSION['success'] = "Your support ticket has been created. We'll get back to you soon.";
        header("Location: ticket_view.php?id=$ticket_id");
        exit;
    } else {
        $_SESSION['error'] = "Failed to create support ticket. Please try again.";
        header("Location: contact.php");
        exit;
    }
}
?>