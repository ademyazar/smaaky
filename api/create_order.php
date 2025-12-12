<?php
// Cache Buster: v_final_mollie_module_call_debugged_V2
// 1. ÇIKTI TAMPONLAMAYI BAŞLAT (Her türlü hatayı/boşluğu yakalar)
ob_start();

// Hataları ekrana basma, loga yaz
ini_set('display_errors', 0);
ini_set('log_errors', 1); // DÜZELTME: ini_init yerine ini_set kullanıldı.
error_reporting(E_ALL);

// Çökme Yakalayıcı (Fatal Error olursa bile JSON dön)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        if (ob_get_length()) ob_clean(); 
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Verbindingsfout. (Fatal Error: ' . $error['message'] . ')']);
        exit;
    }
});

header('Content-Type: application/json');

// 2. DOSYALARI YÜKLE (MODÜLLERİ ÇAĞIR)

// Config yükle
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Config dosyası bulunamadı (../config.php).']);
    exit;
}

// Veritabanı Kontrolü
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

// MOLLIE MODÜLÜ VE YARDIMCI SINIFLARI YÜKLE
if (file_exists('../mollie_client.php')) {
    require_once '../mollie_client.php';
}
if (file_exists('../payment_module.php')) {
    require_once '../payment_module.php';
}


// 3. İŞLEMLER
try {
    // JSON Verisini Al
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if ($data === null) {
        throw new Exception("Geçersiz veri formatı.");
    }

    // Değişkenleri Ata
    $name = $data['name'];
    $email = $data['email'] ?? '';
    $phone = $data['phone'];
    $zip = $data['zip'] ?? '';
    $city = 'Rotterdam';
    $street = $data['address'] ?? '';
    $houseNumber = $data['houseno'] ?? '';
    $deliveryType = $data['delivery_type'];
    $paymentMethod = $data['payment_method']; 
    $total = (float)$data['total'];
    $note = $data['note'] ?? '';
    $deliveryFee = $data['delivery_fee'] ?? 0;
    $couponCode = $data['coupon_code'] ?? null;
    $discountAmount = $data['discount_amount'] ?? 0;
    
    $status = 'pending';
    // Mollie'de başarılı ödeme gelene kadar "pending" kalır. Kapıda ödeme direkt "new" olur.
    if ($paymentMethod === 'cash') {
        $status = 'new';
    }

    // Subtotal
    $subtotal = 0;
    if (isset($data['cart']) && is_array($data['cart'])) {
        foreach ($data['cart'] as $item) {
            $subtotal += ($item['finalPrice'] * $item['qty']);
        }
    }

    $mollieMethods = ['ideal', 'creditcard', 'klarnapaylater'];
    $isMolliePayment = in_array($paymentMethod, $mollieMethods);

    // 4. VERİTABANI KAYDI
    $orderId = null;
    try {
        $sql = "INSERT INTO orders (
            customer_name, email, phone, zip, city, street, house_number, 
            delivery_fee, subtotal, total, created_at, status, 
            payment_method, delivery_type, note, discount_amount, coupon_code
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $name, $email, $phone, $zip, $city, $street, $houseNumber,
            $deliveryFee, $subtotal, $total, $status,
            $paymentMethod, $deliveryType, $note, $discountAmount, $couponCode
        ]);
        $orderId = $pdo->lastInsertId();

        // Ürünleri Kaydet
        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit_price, total_price, qty, extras) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if (isset($data['cart']) && is_array($data['cart'])) {
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
        }

    } catch (PDOException $e) {
        throw new Exception("Veritabanı Hatası: " . $e->getMessage());
    }

    if (!$orderId) {
        throw new Exception("Sipariş ID oluşturulamadı.");
    }

    // Yönlendirme Linki
    $redirectUrl = 'order_success.php?order_id=' . $orderId;

    // 5. MOLLIE MODÜLÜ ÇAĞRISI (Online Ödeme)
    if ($isMolliePayment) {
        
        // Modülün yüklenip yüklenmediğini kontrol et
        if (!class_exists('MolliePaymentModule')) {
            // Eğer modül yoksa, Kapıda ödeme gibi davranmak yerine HATA vermeliyiz.
            throw new Exception("MOLLIE_MODULE_NOT_LOADED: payment_module.php yüklenemedi. Online ödeme yapılamaz.");
        }

        try {
            // Modül başlatılıyor
            $paymentModule = new MolliePaymentModule();
            $result = $paymentModule->createMolliePayment($total, $orderId, $paymentMethod);

            // Mollie yanıtını kontrol et
            if ($result['success']) {
                if (ob_get_length()) ob_clean();
                echo json_encode([
                    'success' => true, 
                    'payment_type' => 'mollie', 
                    'redirect_url' => $result['redirect_url']
                ]);
                exit;
            }
            
            // Eğer Mollie'den başarısız yanıt gelirse
            throw new Exception("Mollie ödemesi başlatılamadı.");
            
        } catch (Exception $e) {
            // Mollie başlatma veya API çağrısı sırasında hata oluştu.
            // Bu hatayı fırlatıyoruz, böylece kullanıcı bankaya gitmediğini anlar.
            throw new Exception("MOLLIE_INIT_FAILED: " . $e->getMessage());
        }
    } 

    // Başarılı (Nakit)
    if (ob_get_length()) ob_clean(); // Temizlik
    echo json_encode([
        'success' => true, 
        'payment_type' => $paymentMethod, 
        'redirect_url' => $redirectUrl
    ]);
    exit;

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Verbindingsfout. (Detail: ' . $e->getMessage() . ')']); 
}