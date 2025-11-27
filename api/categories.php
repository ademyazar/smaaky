<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "../config.php";

try {
    $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY id ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $categories
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}