<?php
require_once __DIR__ . '/../config.php';

$name = trim($_POST['name']);
$sort_order = (int)($_POST['sort_order'] ?? 0);

if (!$name) {
    die("Naam is verplicht.");
}

$stmt = $pdo->prepare("INSERT INTO categories (name, sort_order) VALUES (:name, :sort_order)");
$stmt->execute([
    ':name' => $name,
    ':sort_order' => $sort_order
]);

header("Location: categories.php");
exit;
