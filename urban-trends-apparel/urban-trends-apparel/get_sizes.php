<?php
header('Content-Type: application/json');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'urban_trends');

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['error' => "Connection failed: " . $e->getMessage()]));
}

$product_id = $_GET['product_id'] ?? '';
if (!$product_id) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("
    SELECT variation_id, size, stock, price_adjustment 
    FROM product_variations 
    WHERE product_id = ? AND stock > 0
    ORDER BY size
");
$stmt->execute([$product_id]);
$sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($sizes);
?>