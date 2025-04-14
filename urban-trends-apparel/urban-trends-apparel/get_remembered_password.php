<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_POST['token'])) {
    echo json_encode(['success' => false]);
    exit;
}

$token = $_POST['token'];

// Get the remembered credentials
$stmt = $db->prepare("SELECT * FROM remember_tokens WHERE token = ? AND expires > NOW()");
$stmt->execute([$token]);
$remember = $stmt->fetch(PDO::FETCH_ASSOC);

if ($remember) {
    // Get the user's current password hash to verify it hasn't changed
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$remember['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['password'] === $remember['password_hash']) {
        // Return success only if the password hasn't changed
        echo json_encode([
            'success' => true,
            'password' => '********' // For security, we only show asterisks in the field
        ]);
        exit;
    }
}

echo json_encode(['success' => false]);
?> 