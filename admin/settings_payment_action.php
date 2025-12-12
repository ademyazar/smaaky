<?php
// admin/settings_payment_action.php
// Hata raporlamayı aç
ini_set('display_errors', 1);
error_reporting(E_ALL);

// config.php'yi dahil et (veritabanı bağlantısı için)
require_once '../config.php';
require_once __DIR__ . '/_functions.php';
requireAdmin(); // Admin yetkilendirmesini kontrol et

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // POST olmayan istekleri ayarlar sayfasına yönlendir (settings.php'ye)
    header('Location: settings.php?subpage=payment');
    exit;
}

try {
    // 1. Verileri al ve temizle
    $mode = trim($_POST['mollie_mode'] ?? 'test');
    // API anahtarlarını alırken trim ile baştaki ve sondaki boşlukları temizle (401 hatasını önler)
    $testKey = trim($_POST['mollie_test_key'] ?? '');
    $liveKey = trim($_POST['mollie_live_key'] ?? '');

    // Güncellenecek ayarların listesi
    $settings_to_update = [
        'mollie_mode' => $mode,
        'mollie_test_key' => $testKey,
        'mollie_live_key' => $liveKey,
    ];

    // 2. Veritabanı işlemini başlat
    $pdo->beginTransaction();

    // INSERT OR UPDATE sorgusu ile ayarları tek seferde ekle/güncelle
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($settings_to_update as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    $pdo->commit();

    // 3. Başarı durumunda geri yönlendir
    header('Location: settings.php?subpage=payment&status=success');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Mollie Ayar Kaydetme Hatası: " . $e->getMessage());
    // 4. Hata durumunda geri yönlendir
    header('Location: settings.php?subpage=payment&status=error');
    exit;
}
?>