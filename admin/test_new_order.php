<?php
require_once __DIR__ . '/../config.php';

// Sahte müşteri bilgileri
$name = "Test Klant";
$phone = "0612345678";
$zip = "3067AB";
$street = "Teststraat";
$house = "10A";
$note = "Test bestelling – door admin test script";
$total = rand(12, 25);

// Sipariş ekle
$stmt = $pdo->prepare("
    INSERT INTO orders (customer_name, phone, zip, street, house, note, total_price, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'nieuw')
");
$stmt->execute([$name, $phone, $zip, $street, $house, $note, $total]);

// Sipariş ID
$order_id = $pdo->lastInsertId();

// Bir ürün ekle
$stmt2 = $pdo->prepare("
    INSERT INTO order_items (order_id, product_name, quantity, total_price, extras)
    VALUES (?, 'Test Burger', 1, ?, 'Cheddar, Bacon')
");
$stmt2->execute([$order_id, $total]);

echo "<h2 style='font-family:Arial'>Nieuwe test bestelling toegevoegd!<br>
Order ID: <b>$order_id</b></h2>";

echo "<p><a href='orders.php' style='font-size:18px'>Ga terug naar bestellingen</a></p>";