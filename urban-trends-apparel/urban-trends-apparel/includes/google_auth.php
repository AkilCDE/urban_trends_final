<?php
require_once 'config.php';

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', 'http://localhost/urban-trends-apparel/google_callback.php');

// Google OAuth URLs
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v3/userinfo');

/**
 * Get Google OAuth URL for login
 */
function getGoogleAuthUrl() {
    $params = array(
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    );
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Handle Google OAuth callback
 */
function handleGoogleCallback($code) {
    global $db;
    
    // Exchange code for access token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = array(
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_response = json_decode($response, true);
    
    if (!isset($token_response['access_token'])) {
        return array('success' => false, 'error' => 'Failed to get access token');
    }
    
    // Get user info using access token
    $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $user_info_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token_response['access_token']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $user_info = json_decode($response, true);
    
    if (!isset($user_info['email'])) {
        return array('success' => false, 'error' => 'Failed to get user info');
    }
    
    // Check if user exists
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$user_info['email']]);
    $user = $stmt->fetch();
    
    if ($user) {
        // User exists, log them in
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        return array(
            'success' => true,
            'user' => $user,
            'is_new' => false
        );
    } else {
        // New user, return info for registration
        return array(
            'success' => true,
            'user_info' => $user_info,
            'is_new' => true
        );
    }
}

/**
 * Register a user from Google OAuth
 * 
 * @param PDO $db Database connection
 * @param array $userData User data from Google
 * @param string $address User address (required)
 * @return array Result
 */
function registerGoogleUser($db, $userData, $address) {
    if (empty($address)) {
        return ['error' => 'Address is required'];
    }
    
    // Generate a random password
    $password = bin2hex(random_bytes(8));
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (email, password, firstname, lastname, address, is_admin, google_id) VALUES (?, ?, ?, ?, ?, 0, ?)");
        $stmt->execute([
            $userData['email'],
            $hashedPassword,
            $userData['firstname'],
            $userData['lastname'],
            $address,
            $userData['google_id']
        ]);
        
        $userId = $db->lastInsertId();
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $userData['email'];
        $_SESSION['user_firstname'] = $userData['firstname'];
        $_SESSION['user_lastname'] = $userData['lastname'];
        $_SESSION['user_address'] = $address;
        $_SESSION['is_admin'] = 0;
        
        return ['success' => true, 'user_id' => $userId];
    } catch (PDOException $e) {
        return ['error' => 'Failed to register user: ' . $e->getMessage()];
    }
}
?> 