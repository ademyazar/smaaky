<?php
// Hata raporlamayı aç
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Root dizinine göre config.php'yi dahil et
require_once '../config.php';
// Oturum kontrolü varsayılır: Admin'in oturum açmış olması gerekir.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // POST olmayan istekleri ayarlar sayfasına yönlendir
    header('Location: ../admin/index.php?page=settings&subpage=payment');
    exit;
}

try {
    // 1. Verileri al ve temizle
    $mode = trim($_POST['mollie_mode'] ?? 'test');
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

    // INSERT OR UPDATE (ON DUPLICATE KEY UPDATE) sorgusu ile ayarları tek seferde ekle/güncelle
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($settings_to_update as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    $pdo->commit();

    // 3. Başarı durumunda geri yönlendir
    header('Location: ../admin/index.php?page=settings&subpage=payment&status=success');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Mollie Ayar Kaydetme Hatası: " . $e->getMessage());
    // 4. Hata durumunda geri yönlendir
    header('Location: ../admin/index.php?page=settings&subpage=payment&status=error');
    exit;
}
?>