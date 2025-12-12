<?php
session_start();
header('Content-Type: application/json');

// JSON verisini al
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data) {
    // Gelen verileri SESSION'a kaydet
    $_SESSION['cart'] = $data['cart'] ?? [];
    $_SESSION['subtotal'] = $data['subtotal'] ?? 0;
    $_SESSION['delivery_fee'] = $data['delivery_fee'] ?? 0;
    $_SESSION['total_price'] = $data['total_price'] ?? 0;
    $_SESSION['coupon_code'] = $data['coupon_code'] ?? null;
    $_SESSION['discount_amount'] = $data['discount_amount'] ?? 0;
    
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No data']);
}
?>