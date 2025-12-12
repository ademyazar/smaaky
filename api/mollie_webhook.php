<?php
// api/mollie_webhook.php
// Mollie'nin ödeme durumu güncellemelerini göndereceği dosya.
// Yanıt olarak HTTP 200 (OK) döndürülmelidir.

// Hata raporlamayı kapat, aksi takdirde Mollie webhook'u hata alır.
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Veritabanı bağlantısı ve Mollie Client için config dosyasını dahil et
require_once '../config.php';
require_once '../admin/_functions.php'; // getSetting fonksiyonu için

// 1. Gelen Veriyi Kontrol Et (Mollie sadece payment ID gönderir)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Mollie'den bir 'id' (Ödeme ID'si) gelmesi gerekir.
if (!isset($data['id']) || empty($data['id'])) {
    // Geçersiz istek, ama 200 döndürerek Mollie'yi rahat bırak
    header("HTTP/1.1 200 OK");
    exit;
}

$paymentId = $data['id'];

// 2. Mollie API Anahtarını al ve İstemciyi başlat
$mollieApiKey = getMollieApiKey($pdo);

// Hata durumunda (API anahtarı yoksa) logla ve çık
if (empty($mollieApiKey) || !class_exists('MollieClient')) {
    error_log("WEBHOOK HATA: Mollie API Anahtarı eksik veya MollieClient yüklenemedi.");
    header("HTTP/1.1 200 OK"); // Hata durumunda bile 200 döndür, Mollie spam yapmasın
    exit;
}

$mollieClient = null;
try {
    $mollieClient = new MollieClient($mollieApiKey);
} catch (Exception $e) {
    error_log("WEBHOOK HATA: Mollie Client başlatılamadı: " . $e->getMessage());
    header("HTTP/1.1 200 OK");
    exit;
}

// 3. Mollie'den Ödeme Durumunu Kontrol Et
try {
    // MollieClient sınıfınızda, Payment ID ile ödeme bilgisini çekme metodu olmalı.
    // MollieClient'a getPayment metodunu eklememiz gerekiyor.
    $payment = $mollieClient->getPayment($paymentId); 

    // Ödeme ID'sini meta datadan al
    $orderId = $payment->metadata->order_id ?? null;

    if (!$orderId) {
        error_log("WEBHOOK HATA: Ödeme ID'si $paymentId için sipariş ID'si bulunamadı.");
        header("HTTP/1.1 200 OK");
        exit;
    }

    // 4. Duruma göre siparişi güncelle
    $newStatus = null;

    switch ($payment->status) {
        case 'paid':
        case 'authorized':
            // Başarılı ödeme
            $newStatus = 'bevestigd'; // Sizin sisteminize uygun bir "Ödendi/Onaylandı" durumu
            break;
        case 'failed':
        case 'expired':
        case 'canceled':
            // Başarısız/İptal edilen ödeme
            $newStatus = 'geannuleerd'; // İptal durumu
            break;
        case 'pending':
        default:
            // Beklemede veya diğer durumlar, işlem yapmaya gerek yok
            $newStatus = null;
            break;
    }

    if ($newStatus) {
        // Sipariş durumunu veritabanında güncelle
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, payment_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $payment->status, $orderId]);
        error_log("WEBHOOK BILGI: Sipariş #$orderId Mollie durumu '$payment->status' olarak güncellendi.");
    }

} catch (Exception $e) {
    // Mollie API'den çekme hatası (API down olabilir, vb.)
    error_log("WEBHOOK KRİTİK HATA: Mollie API'den ödeme çekilemedi ($paymentId). Hata: " . $e->getMessage());
}

// Mollie'ye her zaman başarılı yanıt döndür, böylece yeniden denemeyi durdurur.
header("HTTP/1.1 200 OK");
?>