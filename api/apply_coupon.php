<?php
header('Content-Type: application/json');
require_once '../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';

// Basit Demo Mantığı (Bunu veritabanına bağlayabilirsiniz)
if ($code === 'SMAAKY10') {
    // %10 İndirim veya Sabit tutar
    $discount = 2.50; // Örnek: 2.50 Euro indirim
    
    echo json_encode([
        'status' => 'success',
        'coupon' => ['code' => $code, 'type' => 'fixed'],
        'discount' => $discount,
        'message' => 'Kupon toegepast!'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Ongeldige code'
    ]);
}
?>