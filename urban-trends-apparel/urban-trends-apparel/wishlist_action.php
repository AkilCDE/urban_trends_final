<?php
session_start();

require_once 'Database/datab.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Please login first']));
}

$productId = $_POST['product_id'] ?? 0;
$action = $_POST['action'] ?? 'add';

if ($action === 'add') {
    // Check if already in wishlist
    $stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $productId]);
    
    if ($stmt->fetchColumn() > 0) {
        die(json_encode(['success' => false, 'message' => 'Product already in wishlist']));
    }
    
    // Add to wishlist
    $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $productId]);
    
    echo json_encode(['success' => true, 'message' => 'Product added to wishlist']);
} else {
    // Remove from wishlist
    $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $productId]);
    
    echo json_encode(['success' => true, 'message' => 'Product removed from wishlist']);
}