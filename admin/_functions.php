<?php
// ===========================================
// SMAAKY – GLOBAL ADMIN FUNCTIONS
// ===========================================

// Database bağlantısı
require_once __DIR__ . '/../config.php';


// ===========================================
// SESSION HELPER
// ===========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin() {
    if (empty($_SESSION['admin_logged_in'])) {
        header("Location: login.php");
        exit;
    }
}


// ===========================================
// SETTINGS GET / SET
// ===========================================

/**
 * Ayar anahtarına göre veritabanından ayar değerini çeker (GÜNCELLENDİ).
 * @param string $key Ayar anahtarı.
 * @param mixed $default Varsayılan değer.
 * @return string|mixed Ayar değeri veya varsayılan.
 */
function getSetting($key, $default = null) {
    global $pdo;
    
    if (!$pdo) {
        return $default; 
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $default;
        }
        
        // KRİTİK GÜNCELLEME: Çekilen değeri temizle (trim)
        return trim($value);

    } catch (Exception $e) {
        error_log("Veritabanından ayar çekme hatası ({$key}): " . $e->getMessage());
        return $default;
    }
}

function setSetting($key, $value) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE setting_value = :value
    ");

    $stmt->execute([
        ":key" => $key,
        // Değer kaydederken de boşlukları temizle
        ":value" => trim($value)
    ]);
}

// ===========================================
// MOLLIE FUNCTIONS (YENİ)
// ===========================================

/**
 * Mevcut moda (test/live) göre Mollie API anahtarını döndürür.
 * @return string Mollie API Anahtarı.
 */
function getMollieApiKey() {
    $mode = getSetting('mollie_mode') ?? 'test';
    
    if ($mode === 'live') {
        return getSetting('mollie_live_key');
    }
    
    // Varsayılan olarak veya 'test' modunda Test anahtarını döndür
    return getSetting('mollie_test_key');
}


// ===========================================
// STORE OPEN/CLOSED LOGIC
// ===========================================

function isStoreForceClosed() {
    return getSetting("store_force_closed", "0") === "1";
}

function isDeliveryPaused() {
    return getSetting("delivery_paused", "0") === "1";
}

function isPickupPaused() {
    return getSetting("pickup_paused", "0") === "1";
}

function getOpeningHours() {
    $json = getSetting("opening_hours", "{}");
    return json_decode($json, true);
}

function isStoreOpen() {
    // Manuel kapatma kontrolü
    if (isStoreForceClosed()) {
        return false;
    }

    // Açılış – kapanış saatlerini al
    $hours = getOpeningHours();

    // Bugünün gün kodu
    $dayKey = strtolower(date("D")); // mon, tue, wed…

    $map = [
        "mon" => "mon",
        "tue" => "tue",
        "wed" => "wed",
        "thu" => "thu",
        "fri" => "fri",
        "sat" => "sat",
        "sun" => "sun"
    ];

    if (!isset($map[$dayKey])) return false;

    $today = $map[$dayKey];

    if (!isset($hours[$today])) return true; // ayarlı değilse açık kabul

    // Gün kapalı mı?
    if (!empty($hours[$today]["closed"])) {
        return false;
    }

    $open  = $hours[$today]["open"]  ?? "00:00";
    $close = $hours[$today]["close"] ?? "23:59";

    $now = date("H:i");

    return ($now >= $open && $now < $close);
}


// ===========================================
// HELPER FUNCTIONS
// ===========================================

// Güvenli tarih formatlama
function formatDateTime($dt) {
    return date("d-m-Y H:i", strtotime($dt));
}

// Para formatlama
function euro($n) {
    return "€ " . number_format($n, 2, ",", ".");
}

// XSS koruma
function e($str) {
    return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8");
}
// ===========================================
// ADMIN/ORDERS_CHECK_NEW'den gelen fonksiyon, _functions.php içinde bulunmalıydı.
// Ancak bu fonksiyon genellikle sadece orders_check_new.php dosyasında kullanılacaksa oraya taşınmalıdır.
// Eğer tüm sistemde kullanılması gerekiyorsa:
/*
function getNewOrderCount() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = 'nieuw'");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Yeni sipariş sayma hatası: " . $e->getMessage());
        return 0;
    }
}
*/
?>