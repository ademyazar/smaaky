<?php
// --- Session + Config ---
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_functions.php';
requireAdmin();

// --- Yalnızca POST isteklerine izin ver ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: orders.php");
    exit;
}

// Gerekli alanları al
$orderId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status  = isset($_POST['status']) ? trim($_POST['status']) : "";

// Geçerli mi?
$validStatuses = [
    "nieuw",
    "bereiden",
    "klaar",
    "bezorgen",
    "afgeleverd",
    "geannuleerd"
];

if ($orderId < 1 || !in_array($status, $validStatuses)) {
    error_log("Invalid status update request: ID={$orderId}, status={$status}");
    header("Location: orders.php?error=invalid");
    exit;
}

// Güncelleme
try {
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $orderId]);

    header("Location: order_view.php?id=" . $orderId . "&updated=1");
    exit;

} catch (Exception $e) {
    error_log("Order status update failed: " . $e->getMessage());
    header("Location: order_view.php?id=" . $orderId . "&error=db");
    exit;
}