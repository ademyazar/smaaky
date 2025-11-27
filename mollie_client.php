<?php
/**
 * Mollie API ile bağlantı kurmak için minimal PHP istemcisi (Composer olmadan).
 * Mollie Payments API'ye ödeme oluşturma isteği göndermek için kullanılır.
 */
class MollieClient {
    private $apiKey;
    private $apiUrl = 'https://api.mollie.com/v2/payments';

    public function __construct(string $apiKey) {
        if (empty($apiKey)) {
            throw new Exception("Mollie API Anahtarı boş olamaz.");
        }
        // KRİTİK KONTROL: Anahtarın formatını kontrol et
        if (substr($apiKey, 0, 5) !== 'test_' && substr($apiKey, 0, 5) !== 'live_') {
            throw new Exception("API Anahtarı formatı yanlış. 'test_' veya 'live_' ile başlamalıdır.");
        }
        $this->apiKey = $apiKey;
    }

    /**
     * Yeni bir Mollie ödemesi oluşturur.
     * @param float $amount Ödenecek miktar.
     * @param string $description Sipariş açıklaması.
     * @param string $redirectUrl Ödeme tamamlandıktan sonra yönlendirilecek URL.
     * @param string $webhookUrl Ödeme durumu güncellemeleri için Mollie'nin çağıracağı URL.
     * @param string $orderId Kendi sistemimizdeki sipariş ID'si.
     * @return object Mollie Payment objesi.
     * @throws Exception HTTP 201 kodu alınamazsa hata fırlatır.
     */
    public function createPayment(float $amount, string $description, string $redirectUrl, string $webhookUrl, string $orderId) {
        $data = [
            'amount' => [
                'currency' => 'EUR', // Para birimini (Euro) sabitliyoruz
                'value' => number_format($amount, 2, '.', ''), // Mollie 2 ondalık hassasiyet bekler
            ],
            'description' => $description,
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => $webhookUrl,
            'metadata' => [
                'order_id' => $orderId, // Kendi sistemimizdeki sipariş ID'sini meta veriye ekliyoruz
            ],
            // Ödeme yöntemini belirtmek, Mollie'nin daha hızlı çalışmasını sağlar.
            'method' => 'ideal', 
        ];

        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        // API anahtarını Authorization başlığı ile gönderme
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!empty($curlError)) {
             // CURL bağlantı hatalarını yakala
             throw new Exception("CURL Bağlantı Hatası: " . $curlError);
        }

        $result = json_decode($response);

        if ($httpCode !== 201) {
            // Hata işleme: Mollie API'sinden gelen hatayı yakala
            $errorMessage = "Mollie API Reddi (HTTP $httpCode).";
            if (isset($result->detail)) {
                $errorMessage .= " Detay: " . $result->detail;
            } elseif (isset($result->_links->documentation->href)) {
                 $errorMessage .= " Daha fazla bilgi için Mollie dokümantasyonuna bakın.";
            } else {
                 $errorMessage .= " Yanıt: " . substr($response, 0, 100) . "...";
            }
            throw new Exception($errorMessage);
        }

        return $result;
    }
}
?>