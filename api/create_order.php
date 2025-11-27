<?php
// PHP hatalarını tarayıcıya göndermeyi kapat (JSON'u bozmaması için)
// Hata loglaması hala açıktır (error_log)
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// config.php dosyasını bir üst dizinden çağır (Burada $pdo, getMollieApiKey ve $mollieClient yüklenir)
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Config dosyası bulunamadı.']);
    exit;
}

// getMollieApiKey ve $pdo değişkenlerinin config.php'den geldiği varsayılır.

try {
    // 3. JSON verisini al ve kontrol et
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) throw new Exception('Geen data ontvangen. (JSON veri hatası)');

    // 4. Gelen Verileri Değişkenlere Ata
    $name = $data['name'];
    $phone = $data['phone'];
    $email = $data['email'] ?? '';
    $street = $data['address'] ?? ''; 
    $houseNumber = $data['houseno'] ?? '';
    $zip = $data['zip'] ?? '';
    $city = 'Rotterdam';
    $note = $data['note'] ?? '';
    $deliveryType = $data['delivery_type'];
    $paymentMethod = $data['payment_method'];
    $total = (float)$data['total']; 
    $subtotal = 0; 
    if (isset($data['cart']) && is_array($data['cart'])) {
        foreach ($data['cart'] as $item) {
            $subtotal += ($item['finalPrice'] * $item['qty']);
        }
    }
    $deliveryFee = $data['delivery_fee'] ?? 0;
    $couponCode = $data['coupon_code'] ?? null;
    $discountAmount = $data['discount_amount'] ?? 0;
    $status = 'pending';

    // 5. Siparişi ve Kalemleri Veritabanına Kaydet
    $sql = "INSERT INTO orders (
        customer_name, email, phone, zip, city, street, house_number, delivery_fee, subtotal, 
        total, created_at, status, total_price, payment_method, delivery_type, note, 
        discount_amount, coupon_code
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $name, $email, $phone, $zip, $city, $street, $houseNumber, $deliveryFee, $subtotal, 
        $total, $status, $total, $paymentMethod, $deliveryType, $note, 
        $discountAmount, $couponCode
    ]);
    $orderId = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit_price, total_price, qty, extras) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($data['cart'] as $item) {
        $extrasStr = '';
        if (!empty($item['selectedExtras'])) {
            $eNames = array_map(function($e) { return $e['name']; }, $item['selectedExtras']);
            $extrasStr = implode(', ', $eNames);
        }
        $unitPrice = $item['finalPrice'];
        $itemTotalPrice = $unitPrice * $item['qty'];

        $stmtItem->execute([
            $orderId, $item['id'], $item['name'], $unitPrice, $itemTotalPrice, $item['qty'], $extrasStr
        ]);
    }


    // 6. Mollie Ödeme Entegrasyonu
    if ($paymentMethod === 'ideal') {
        
        // $mollieClient global değişkeni config.php'den gelir.
        global $mollieClient; 
        
        // KRİTİK KONTROL: $mollieClient'ın başarılı bir şekilde config.php'de oluşturulup oluşturulmadığını kontrol et
        if ($mollieClient) {
            
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $domain = $_SERVER['HTTP_HOST'];
            $baseUrl = "$protocol://$domain";

            // KRİTİK KONTROL 1: Tutar 0'dan büyük olmalı
            if ($total <= 0.01) {
                 throw new Exception("Ödeme tutarı 0.01 EUR'dan küçük olamaz.");
            }
            
            // Sizin MollieClient sınıfınızın createPayment metodunu çağırıyoruz
            $payment = $mollieClient->createPayment(
                $total, // float amount
                "Bestelling #" . $orderId, // description
                "$baseUrl/order_success.php?order_id=" . $orderId, // redirectUrl
                "$baseUrl/api/mollie_webhook.php", // webhookUrl
                $orderId // orderId (metadata için)
            );
            
            // KRİTİK KONTROL 2: Mollie'den gelen yanıtı detaylı kontrol et
            if ($payment) {
                if (isset($payment->_links->checkout->href)) {
                    echo json_encode(['success' => true, 'payment_type' => 'mollie', 'redirect_url' => $payment->_links->checkout->href]);
                    exit; 
                } elseif (isset($payment->status) && $payment->status === 'paid') {
                    // Örneğin 0 tutarlı kupondan sonra paid gelirse
                    echo json_encode(['success' => true, 'payment_type' => 'mollie_paid', 'redirect_url' => 'order_success.php?order_id=' . $orderId]);
                    exit;
                } elseif (isset($payment->detail)) {
                    // Mollie API'sinden gelen spesifik hata mesajını yakala
                    throw new Exception("Mollie API Hatası: " . $payment->detail);
                }
            }
            
            // Hiçbir koşul sağlanmazsa hata fırlat.
            throw new Exception("Mollie ödeme objesi oluşturulamadı. Lütfen tutarın geçerli olduğundan ve API anahtarınızın doğru olduğundan emin olun.");
        } 
        
        // Eğer $mollieClient null ise, config.php'de bir hata oluşmuştur.
        throw new Exception("Mollie ödeme sistemi başlatılamadı. Lütfen 'config.php' dosyasındaki logları kontrol edin.");
    }
    
    // 7. Nakit/Kapıda Ödeme
    echo json_encode([
        'success' => true,
        'payment_type' => 'cash',
        'redirect_url' => 'order_success.php?order_id=' . $orderId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    // Hatanın detayını logla
    error_log("Sipariş Oluşturma Kritik Hata (create_order.php): " . $e->getMessage());
    
    // Tarayıcıya bağlantı hatasının detayını göndermeye çalışır.
    echo json_encode(['success' => false, 'message' => 'Verbindingsfout. (Detail: ' . $e->getMessage() . ')']); 
}
?>