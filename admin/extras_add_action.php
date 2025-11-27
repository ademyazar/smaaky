<?php
require_once __DIR__ . '/../config.php';

$name = trim($_POST['name']);
$price = (float)$_POST['price'];
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (!$name || !$price) {
    die("Naam en prijs zijn verplicht");
}

$stmt = $pdo->prepare("
    INSERT INTO extras (name, price, is_active)
    VALUES (:name, :price, :is_active)
");

$stmt->execute([
    ':name' => $name,
    ':price' => $price,
    ':is_active' => $is_active
]);

header("Location: extras.php");
exit;
