<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "../config.php";

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

try {
    if ($category_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? ORDER BY id ASC");
        $stmt->execute([$category_id]);
    } else {
        $stmt = $pdo->query("SELECT * FROM products ORDER BY id ASC");
    }

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $products]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}