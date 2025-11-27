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
function getSetting($key, $default = null) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value !== false ? $value : $default;
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
        ":value" => $value
    ]);
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

?>