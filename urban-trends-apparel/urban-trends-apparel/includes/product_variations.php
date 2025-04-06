<?php
class ProductVariation {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getSizes($product_id) {
        $stmt = $this->db->prepare("SELECT * FROM product_sizes WHERE product_id = ? ORDER BY size");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSizeById($size_id) {
        $stmt = $this->db->prepare("SELECT * FROM product_sizes WHERE id = ?");
        $stmt->execute([$size_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function checkStock($size_id, $quantity = 1) {
        $stmt = $this->db->prepare("SELECT stock FROM product_sizes WHERE id = ?");
        $stmt->execute([$size_id]);
        $stock = $stmt->fetchColumn();
        return $stock >= $quantity;
    }
    
    public function reduceStock($size_id, $quantity) {
        $stmt = $this->db->prepare("UPDATE product_sizes SET stock = stock - ? WHERE id = ? AND stock >= ?");
        return $stmt->execute([$quantity, $size_id, $quantity]);
    }
    
    public function getAvailableSizes($product_id) {
        $stmt = $this->db->prepare("SELECT id, size FROM product_sizes WHERE product_id = ? AND stock > 0 ORDER BY size");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSizeId($product_id, $size) {
        $stmt = $this->db->prepare("SELECT id FROM product_sizes WHERE product_id = ? AND size = ?");
        $stmt->execute([$product_id, $size]);
        return $stmt->fetchColumn();
    }
    
    public function getProductId($size_id) {
        $stmt = $this->db->prepare("SELECT product_id FROM product_sizes WHERE id = ?");
        $stmt->execute([$size_id]);
        return $stmt->fetchColumn();
    }
}
?>