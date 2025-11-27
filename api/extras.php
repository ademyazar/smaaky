<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "../config.php";

try {
    $stmt = $pdo->query("SELECT * FROM extras ORDER BY id ASC");
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