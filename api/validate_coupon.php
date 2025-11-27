<?php
header('Content-Type: application/json');
require_once '../config.php';

// POST verisini al
$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';
$cart = $input['cart'] ?? [];
$total = $input['total'] ?? 0;

if (!$code) { echo json_encode(['status'=>'error', 'message'=>'Geen code ingevoerd']); exit; }

// Kuponu bul
$stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
$stmt->execute([$code]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) { echo json_encode(['status'=>'error', 'message'=>'Ongeldige code']); exit; }
if ($total < $coupon['min_order_amount']) { echo json_encode(['status'=>'error', 'message'=>'Minimum bestelbedrag is €'.$coupon['min_order_amount']]); exit; }

$discount = 0;

// --- HESAPLAMA MANTIĞI ---

// 1. Yüzde İndirim (%20 vb.)
if ($coupon['type'] == 'percent') {
    if ($coupon['target_type'] == 'all') {
        $discount = $total * ($coupon['value'] / 100);
    } else {
        // Sadece belirli kategori veya ürün için
        foreach ($cart as $item) {
            $isMatch = false;
            if ($coupon['target_type'] == 'product' && $item['id'] == $coupon['target_id']) $isMatch = true;
            if ($coupon['target_type'] == 'category' && $item['category_id'] == $coupon['target_id']) $isMatch = true; // category_id front-end'den gelmeli
            
            if ($isMatch) {
                $itemTotal = $item['finalPrice'] * $item['qty'];
                $discount += $itemTotal * ($coupon['value'] / 100);
            }
        }
    }
}
// 2. Sabit İndirim (5 Euro vb.)
elseif ($coupon['type'] == 'fixed') {
    $discount = $coupon['value'];
}
// 3. BOGO (2 Al 1 Öde)
elseif ($coupon['type'] == 'bogo') {
    foreach ($cart as $item) {
        $isMatch = false;
        // Ürün bazlı (Örn: Sadece Big King'de geçerli)
        if ($coupon['target_type'] == 'product' && $item['id'] == $coupon['target_id']) $isMatch = true;
        // Kategori bazlı (Örn: Tüm Burgerlerde geçerli) - Not: category_id verisi cart item'da olmalı
        if ($coupon['target_type'] == 'category' && isset($item['category_id']) && $item['category_id'] == $coupon['target_id']) $isMatch = true; 
        
        if ($isMatch) {
            // Her 2 adette 1'i bedava
            $freeItems = floor($item['qty'] / 2);
            $discount += $freeItems * $item['finalPrice'];
        }
    }
}

// İndirim toplam tutardan büyük olamaz
if ($discount > $total) $discount = $total;

echo json_encode([
    'status' => 'success',
    'discount' => $discount,
    'new_total' => $total - $discount,
    'message' => 'Korting toegepast: €'.number_format($discount, 2)
]);
?>