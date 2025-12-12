<?php
// Mollie Client Sınıfı - Basit bir API çağrısı sarmalayıcısı

class MollieClient {
    private $apiKey;
    private $apiUrl = 'https://api.mollie.com/v2/payments';

    public function __construct($apiKey) {
        if (empty($apiKey)) {
            throw new Exception("Mollie API anahtarı boş olamaz.");
        }
        $this->apiKey = $apiKey;
    }

    public function createPayment($amount, $description, $redirectUrl, $webhookUrl, $orderId, $method) {
        
        $methodToUse = in_array($method, ['ideal', 'creditcard', 'klarnapaylater']) ? $method : null;

        $data = [
            "amount" => [
                "currency" => "EUR",
                "value" => number_format($amount, 2, '.', '')
            ],
            "description" => $description,
            "redirectUrl" => $redirectUrl,
            "webhookUrl" => $webhookUrl,
            "metadata" => [
                "order_id" => (int)$orderId
            ],
            "method" => $methodToUse // Ödeme yöntemini burada belirtiyoruz
        ];

        return $this->callApi($data);
    }

    private function callApi($data) {
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->apiKey}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Güvenlik için true olmalı, test için false olabilir

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL hatası: " . $error);
        }

        $result = json_decode($response);

        if ($httpCode >= 400) {
            // Mollie API hata kodu döndürdü
            $detail = isset($result->detail) ? $result->detail : 'Bilinmeyen API hatası';
            throw new Exception("Mollie API HTTP $httpCode Hatası: " . $detail);
        }

        return $result;
    }
}
?>