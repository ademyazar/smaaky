<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    // 1. Kategorileri Çek
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. SADECE AKTİF Ürünleri Çek
    $stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY id DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Ürünlere Özgü Ekstraları Çek (GÜNCELLENDİ)
    // Artık tüm ekstraları değil, sadece admin panelinden eşleştirilenleri çekiyoruz.
    $extrasByProduct = [];
    foreach ($products as $p) {
        $stmt = $pdo->prepare("
            SELECT e.* FROM extras e 
            JOIN product_extras pe ON e.id = pe.extra_id 
            WHERE pe.product_id = ? 
            ORDER BY e.name ASC
        ");
        $stmt->execute([$p['id']]);
        $extrasByProduct[$p['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'status' => 'success',
        'categories' => $categories,
        'products' => $products,
        'extras_by_product' => $extrasByProduct
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?>