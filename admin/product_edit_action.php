<?php
session_start();
require_once __DIR__ . '/../config.php';

$id = (int)$_POST['id'];
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$price = (float)($_POST['price'] ?? 0);

$image = trim($_POST['image'] ?? '');
$image_url = trim($_POST['image_url'] ?? '');

$has_toppings = isset($_POST['has_toppings']) ? 1 : 0;
$is_active    = isset($_POST['is_active']) ? 1 : 0;

// Seçilen extras (toppings)
$extra_ids = $_POST['extra_ids'] ?? [];
if (!is_array($extra_ids)) {
    $extra_ids = [];
}

// Güvenlik: sadece pozitif integer yap
$extra_ids_clean = [];
foreach ($extra_ids as $eid) {
    $eid = (int)$eid;
    if ($eid > 0) {
        $extra_ids_clean[] = $eid;
    }
}

if (!$id || !$name || !$category_id || $price < 0) {
    die("Missing or invalid fields");
}

try {
    $pdo->beginTransaction();

    // ÜRÜN GÜNCELLE
    $stmt = $pdo->prepare("
        UPDATE products
        SET category_id  = :category_id,
            name         = :name,
            description  = :description,
            price        = :price,
            image        = :image,
            image_url    = :image_url,
            has_toppings = :has_toppings,
            is_active    = :is_active
        WHERE id         = :id
    ");

    $stmt->execute([
        ':id'          => $id,
        ':category_id' => $category_id,
        ':name'        => $name,
        ':description' => $description,
        ':price'       => $price,
        ':image'       => $image,
        ':image_url'   => $image_url,
        ':has_toppings'=> $has_toppings,
        ':is_active'   => $is_active
    ]);

    // TOPPINGS İLİŞKİLERİ: ÖNCE TEMİZLE
    $del = $pdo->prepare("DELETE FROM product_extras WHERE product_id = ?");
    $del->execute([$id]);

    // Eğer has_toppings = 1 ise ve seçili extra varsa yeniden ekle
    if ($has_toppings && !empty($extra_ids_clean)) {
        $ins = $pdo->prepare("
            INSERT INTO product_extras (product_id, extra_id)
            VALUES (:product_id, :extra_id)
        ");

        foreach ($extra_ids_clean as $eid) {
            $ins->execute([
                ':product_id' => $id,
                ':extra_id'   => $eid
            ]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die("Error updating product: " . $e->getMessage());
}

header("Location: products.php");
exit;
