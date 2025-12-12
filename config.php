<?php
// --- DATABASE CONNECTION CONFIG ---

$DB_HOST = "localhost";
$DB_NAME = "u717526728_oD0olVJG3_smky"; 
$DB_USER = "u717526728_oD0olVJG3_smky";         
$DB_PASS = "2SjVEG^*~i;a";          
$charset = 'utf8mb4';

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE             => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES    => false, 
];

$pdo = null;
try {
     $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}

// -------------------------------------------------------------
// MOLLIE ENTEGRASYONU İÇİN GEREKLİ AYARLAR VE SINIF

// 1. Mollie API Bağlantı Sınıfını dahil et
$mollieClientPath = __DIR__ . '/mollie_client.php'; 
$loaded = false;

// Root'tan veya bir üst dizinden yüklemeyi dene
if (file_exists($mollieClientPath)) {
    require_once $mollieClientPath;
    $loaded = true;
} elseif (file_exists(__DIR__ . '/../mollie_client.php')) {
    require_once __DIR__ . '/../mollie_client.php';
    $loaded = true;
} 

if ($loaded && !class_exists('MollieClient')) {
    error_log("Kritik: 'mollie_client.php' yüklendi, ancak 'MollieClient' sınıfı tanımlı değil.");
    $loaded = false; 
}

// 2. Mollie API Anahtarını Veritabanından ÇEKEN FONKSİYON
function getMollieApiKey($pdo) {
    if (!$pdo) return ''; 

    try {
        // 'mollie_mode' ayarını çek (live/test)
        $stmtMode = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'mollie_mode'");
        $mode = $stmtMode->fetchColumn() ?: 'test';
        
        // Mode'a göre doğru anahtarın veritabanı sütun adını çek
        $keyName = ($mode === 'live') ? 'mollie_live_key' : 'mollie_test_key'; 
        
        // KRİTİK DÜZELTME: Sadece bir kayıt çekmeyi garanti etmek için LIMIT 1 eklenir.
        $stmtKey = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmtKey->execute([$keyName]);
        
        $apiKey = $stmtKey->fetchColumn() ?: ''; 
        
        // KRİTİK DÜZELTME: Anahtarı temizle (trim)
        $apiKey = trim($apiKey);

        if (empty($apiKey)) {
            error_log("KRİTİK UYARI: Mollie API anahtarı ('$keyName') veritabanında boş. Mollie çalışmayacaktır.");
        }
        
        return $apiKey; 
        
    } catch (Exception $e) {
        // Eğer veritabanı sorgusu (tablo yok vb.) hata verirse, boş döndür.
        error_log("KRİTİK HATA: Veritabanından anahtar çekilemedi: " . $e->getMessage());
        return ''; 
    }
}

// Global Mollie İstemcisi oluştur
$mollieClient = null;

if ($loaded) {
    $mollieApiKey = getMollieApiKey($pdo);

    if (!empty($mollieApiKey)) {
        try {
            // Sınıfın varlığını kontrol ettik
            $mollieClient = new MollieClient($mollieApiKey);
            error_log("BILGI: MollieClient başarıyla başlatıldı.");

        } catch (Exception $e) {
            error_log("HATA: MollieClient başlatılamadı: " . $e->getMessage());
        }
    } else {
        error_log("UYARI: MollieClient başlatılamadı, çünkü API anahtarı veritabanından boş geldi.");
    }
}

// -------------------------------------------------------------
?>