<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');

class Auth {
    private $db;
    
    public function __construct() {
        try {
            $this->db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function requestPasswordReset($email) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Check if user exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() === 0) {
            return false;
        }
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        try {
            // Delete any existing tokens
            $this->db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            
            // Store new token
            $stmt = $this->db->prepare("
                INSERT INTO password_resets (email, token, expires_at) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$email, $token, $expires]);
            
            return $token;
        } catch(PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            return false;
        }
    }
    
    public function validateResetToken($token) {
        $stmt = $this->db->prepare("
            SELECT email FROM password_resets 
            WHERE token = ? AND expires_at > NOW()
        ");
        $stmt->execute([$token]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updatePassword($email, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $this->db->beginTransaction();
            
            // Update password
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $email]);
            
            // Delete token
            $this->db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            
            $this->db->commit();
            return true;
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Password update error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendResetEmail($email, $token) {
        $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                     "://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
        
        $subject = "Password Reset Request";
        $message = "
            <html>
            <head>
                <title>Password Reset</title>
            </head>
            <body>
                <h2>Password Reset Request</h2>
                <p>Please click the link below to reset your password:</p>
                <p><a href='$resetLink'>$resetLink</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
            </body>
            </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: no-reply@urban-trends.com" . "\r\n";
        
        return mail($email, $subject, $message, $headers);
    }
}
?>