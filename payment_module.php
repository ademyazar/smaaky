<?php
// payment_module.php - Mollie ödeme entegrasyonunu bağımsız olarak yönetir

class MolliePaymentModule {
    private $mollieClient;
    
    public function __construct() {
        // Fonksiyon çakışmasını önlemek için Mollie Client'ı sadece burada başlatıyoruz.
        // API anahtarı config.php'den gelmeli.
        $mollieApiKey = '';
        if (function_exists('getMollieApiKey')) {
            $mollieApiKey = getMollieApiKey();
        } 

        // MollieClient sınıfının zaten yüklenmiş olması gerekir.
        if (!empty($mollieApiKey) && class_exists('MollieClient')) {
            try {
                // $this->mollieClient, MollieClient sınıfının bir örneğidir.
                $this->mollieClient = new MollieClient($mollieApiKey);
            } catch (Exception $e) {
                error_log("MollieClient başlatma hatası: " . $e->getMessage());
                $this->mollieClient = null; // Başlatılamazsa null kalsın
            }
        } else {
            $this->mollieClient = null;
        }
    }

    /**
     * Mollie'de yeni bir ödeme başlatır ve yönlendirme URL'sini döndürür.
     */
    public function createMolliePayment(float $total, string $orderId, string $paymentMethod): array {
        if (!$this->mollieClient) {
            throw new Exception("MOLLIE_INIT_FAILED: Mollie API bağlantısı kurulamadı veya anahtar eksik. Lütfen Admin panelindeki anahtarı kontrol edin.");
        }
        
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $domain = $_SERVER['HTTP_HOST'];
        $baseUrl = "$protocol://$domain"; 

        if ($total <= 0.01) {
             throw new Exception("Ödeme tutarı 0.01 EUR'dan küçük olamaz.");
        }
        
        // Ödeme oluşturma
        $payment = $this->mollieClient->createPayment(
            $total, 
            "Bestelling #" . $orderId, 
            "$baseUrl/order_success.php?order_id=" . $orderId, // Başarı durumunda yönlendirme
            "$baseUrl/api/mollie_webhook.php",                 // Webhook
            $orderId, 
            $paymentMethod 
        );
        
        if (isset($payment->_links->checkout->href)) {
            // Başarılı, Mollie ödeme URL'sini döndür
            return ['success' => true, 'redirect_url' => $payment->_links->checkout->href];
        } 
        
        $mollieError = isset($payment->detail) ? $payment->detail : "Geçersiz Mollie yanıtı.";
        throw new Exception("MOLLIE_API_REDDI: " . $mollieError);
    }
}
?>