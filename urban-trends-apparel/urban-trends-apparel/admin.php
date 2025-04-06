<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Admin account details
    $email = 'admin@urbantrends.com';
    $password = 'StrongAdminPassword123!';
    $firstname = 'Admin';
    $lastname = 'User';
    $address = '123 Admin Street, Admin City';
    
    // Check if admin already exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        die("Admin account already exists");
    }
    
    // Hash password and create admin
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (email, password, firstname, lastname, address, is_admin) VALUES (?, ?, ?, ?, ?, 1)");
    
    if ($stmt->execute([$email, $hashed_password, $firstname, $lastname, $address])) {
        echo "Admin account created successfully!\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
        echo "IMPORTANT: Save these credentials and delete this file immediately!\n";
    } else {
        echo "Failed to create admin account";
    }
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}