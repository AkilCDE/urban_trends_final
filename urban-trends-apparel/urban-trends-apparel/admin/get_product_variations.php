<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');

header('Content-Type: application/json');

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (!isset($_GET['product_id'])) {
        throw new Exception('Product ID not provided');
    }
    
    $product_id = intval($_GET['product_id']);
    $stmt = $db->prepare("SELECT * FROM product_variations WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $variations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($variations);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}