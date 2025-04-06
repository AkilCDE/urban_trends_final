<?php
class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Register new user
    public function register($email, $password, $firstname, $lastname, $address) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (id, email, password, firstname, lastname, address, is_admin) VALUES (NULL, ?, ?, ?, ?, ?, 0)");
        return $stmt->execute([$email, $hashed_password, $firstname, $lastname, $address]);
    }
    
    // Login user
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_firstname'] = $user['firstname'];
            $_SESSION['user_lastname'] = $user['lastname'];
            $_SESSION['user_address'] = $user['address'];
            $_SESSION['is_admin'] = $user['is_admin'];
            return true;
        }
        return false;
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    // Check if user is admin
    public function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
    
    // Logout user
    public function logout() {
        session_unset();
        session_destroy();
    }
    
    // Get current user
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'firstname' => $_SESSION['user_firstname'],
                'lastname' => $_SESSION['user_lastname'],
                'address' => $_SESSION['user_address']
            ];
        }
        return null;
    }
}

// Initialize Auth
$auth = new Auth($db);
?>