<?php
require_once 'Database/datab.php';

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