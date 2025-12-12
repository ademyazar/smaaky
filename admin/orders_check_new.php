<?php
// admin/orders_check_new.php
// Yeni siparişleri kontrol eder ve sayısını döndürür (JSON formatında).

header('Content-Type: application/json');
require_once '../config.php';
session_start();
// Admin yetkilendirmesi burada kontrol edilmez, sadece veri çeker.

// YALNIZCA 'nieuw' durumundaki siparişleri say
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = 'nieuw'");
    $stmt->execute();
    $newOrderCount = (int)$stmt->fetchColumn();

    // En son yeni siparişin ID'sini çek (Bununla bildirimleri tetikleyeceğiz)
    $stmtLast = $pdo->prepare("SELECT id FROM orders WHERE status = 'nieuw' ORDER BY id DESC LIMIT 1");
    $stmtLast->execute();
    $lastNewOrderId = (int)$stmtLast->fetchColumn() ?? 0;

    echo json_encode([
        'success' => true,
        'count' => $newOrderCount,
        'last_id' => $lastNewOrderId
    ]);

} catch (Throwable $e) {
    error_log("Yeni Sipariş Kontrol Hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'count' => 0, 'last_id' => 0]);
}
?>