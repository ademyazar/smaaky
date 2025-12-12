<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "../config.php";

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

try {
    if ($product_id < 1) {
        throw new Exception("Invalid product_id");
    }

    $stmt = $pdo->prepare("
        SELECT e.id, e.name, e.price
        FROM product_extras pe
        JOIN extras e ON e.id = pe.extra_id
        WHERE pe.product_id = ?
    ");
    $stmt->execute([$product_id]);
    $extras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $extras
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}