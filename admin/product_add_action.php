<?php
session_start();
require_once __DIR__ . '/../config.php';

$name = trim($_POST['name']);
$description = trim($_POST['description'] ?? '');
$category_id = (int)$_POST['category_id'];
$price = (float)$_POST['price'];

$image = trim($_POST['image'] ?? '');
$image_url = trim($_POST['image_url'] ?? '');

$has_toppings = isset($_POST['has_toppings']) ? 1 : 0;
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (!$name || !$category_id || !$price) {
    die("Missing required fields");
}

$stmt = $pdo->prepare("
    INSERT INTO products (category_id, name, description, price, image, image_url, has_toppings, is_active)
    VALUES (:category_id, :name, :description, :price, :image, :image_url, :has_toppings, :is_active)
");

$stmt->execute([
    ':category_id' => $category_id,
    ':name' => $name,
    ':description' => $description,
    ':price' => $price,
    ':image' => $image,
    ':image_url' => $image_url,
    ':has_toppings' => $has_toppings,
    ':is_active' => $is_active
]);

header("Location: products.php");
exit;
