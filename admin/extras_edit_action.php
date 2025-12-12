<?php
require_once __DIR__ . '/../config.php';

$id = (int)$_POST['id'];
$name = trim($_POST['name']);
$price = (float)$_POST['price'];
$is_active = isset($_POST['is_active']) ? 1 : 0;

$stmt = $pdo->prepare("
    UPDATE extras
    SET name = :name, price = :price, is_active = :is_active
    WHERE id = :id
");

$stmt->execute([
    ':id' => $id,
    ':name' => $name,
    ':price' => $price,
    ':is_active' => $is_active
]);

header("Location: extras.php");
exit;
