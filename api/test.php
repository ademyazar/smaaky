<?php
// PHP hatalarını tarayıcıya göndermeyi kapat (JSON'u bozmaması için)
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- UZMAN TEST: KRİTİK DOSYA YÜKLEMELERİ VE VERİTABANI ERİŞİMİ YOK ---

try {
    // 3. JSON verisini al (Sadece verinin geldiğini kontrol etmek için)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) throw new Exception('Geen data ontvangen. (JSON veri hatası)');

    // TEST BAŞARISI: HİÇBİR İŞLEM YAPILMADI, SADECE BAŞARI DÖNÜLÜYOR.
    // Başarı durumunda, tarayıcıyı order_success sayfasına yönlendirmesi gerekir.
    $testOrderId = 12345;
    
    // 1. Durum: Kapıda Ödeme (cash) seçilmişse
    if (($data['payment_method'] ?? 'cash') === 'cash') {
        echo json_encode([
            'success' => true,
            'payment_type' => 'cash',
            'redirect_url' => '../order_success.php?order_id=' . $testOrderId
        ]);
        exit;
    } 
    
    // 2. Durum: Online Ödeme seçilmişse
    echo json_encode([
        'success' => true,
        'payment_type' => $data['payment_method'],
        'redirect_url' => '../order_success.php?order_id=' . $testOrderId
    ]);
    exit;


} catch (Exception $e) {
    http_response_code(500);
    // En üst seviye hata yakalama bloğu: Hatanın detayını tarayıcıya net iletiyoruz.
    echo json_encode(['success' => false, 'message' => 'Verbindingsfout. (Detail: TEST_FAILED_AT_INPUT: Veri okuma sırasında bile hata oluştu.']); 
}
?>