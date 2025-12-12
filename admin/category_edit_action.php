<?php
require_once __DIR__ . '/../config.php';

$id = (int)$_POST['id'];
$name = trim($_POST['name']);
$sort_order = (int)($_POST['sort_order'] ?? 0);

if (!$id || !$name) {
    die("Missing fields");
}

$stmt = $pdo->prepare("
    UPDATE categories 
    SET name = :name, sort_order = :sort_order 
    WHERE id = :id
");

$stmt->execute([
    ':id' => $id,
    ':name' => $name,
    ':sort_order' => $sort_order
]);

header("Location: categories.php");
exit;
