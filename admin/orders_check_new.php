<?php
require_once __DIR__ . '/../config.php';

// En son sipariş ID'si
$stmt = $pdo->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1");
$latest = $stmt->fetchColumn();

echo json_encode([
    "latest_id" => (int)$latest
]);