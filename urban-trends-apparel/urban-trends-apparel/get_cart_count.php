<?php
session_start();

echo json_encode([
    'count' => count($_SESSION['cart'] ?? [])
]);