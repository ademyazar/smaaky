<?php
require_once __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: categories.php");
exit;
