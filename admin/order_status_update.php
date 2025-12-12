<?php
session_start();
require_once __DIR__ . '/../config.php';

// Admin kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Formdan gelen bilgiler
if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    header("Location: orders.php");
    exit;
}

$order_id = (int)$_POST['order_id'];
$new_status = $_POST['status'];

// Desteklenen durumlar
$allowed = ['nieuw', 'bezig', 'klaar', 'afgeleverd', 'geannuleerd'];

if (!in_array($new_status, $allowed)) {
    die("Ongeldige status.");
}

// Sipariş kontrolü
$stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
$stmt->execute([$order_id]);

if (!$stmt->fetch()) {
    die("Bestelling niet gevonden.");
}

// Güncelleme
$stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->execute([$new_status, $order_id]);

// Yönlendirme
header("Location: order_view.php?id=" . $order_id);
exit;