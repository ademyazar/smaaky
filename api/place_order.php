<?php
// api/place_order.php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../admin/_functions.php'; 
require_once __DIR__ . '/../config.php';


// -----------------------------
// 1) Mağaza açık mı?
// -----------------------------
if (!isStoreOpen()) {
    echo json_encode([
        "status"  => "closed",
        "message" => "Helaas! We nemen op dit moment geen bestellingen aan."
    ]);
    exit;
}


// -----------------------------
// 2) JSON al
// -----------------------------
$raw = file_get_contents("php://input");
if (!$raw) {
    echo json_encode(["status" => "error", "message" => "Leeg verzoek ontvangen."]);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(["status" => "error", "message" => "Ongeldige JSON."]);
    exit;
}


// -----------------------------
// 3) Required fields
// NOTE: JS tarafındaki app.js ile birebir eşleşiyor!
// -----------------------------
$required = [
    "name",
    "phone",
    "email",
    "street",
    "zip",
    "city",
    "mode",            // delivery | pickup
    "subtotal",
    "delivery_fee",
    "total",
    "items"
];

foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === "") {
        echo json_encode([
            "status" => "error",
            "message" => "Missing field: {$field}"
        ]);
        exit;
    }
}


// -----------------------------
// 4) Değişkenleri al
// -----------------------------
$name   = trim($data["name"]);
$phone  = trim($data["phone"]);
$email  = trim($data["email"]);
$street = trim($data["street"]);
$zip    = trim($data["zip"]);
$city   = trim($data["city"]);
$mode   = $data["mode"]; 
$items  = $data["items"];

$subtotal     = (float)$data["subtotal"];
$delivery_fee = (float)$data["delivery_fee"];
$total        = (float)$data["total"];


// -----------------------------
// 5) Items validasyonu
// -----------------------------
if (!is_array($items) || count($items) === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Winkelmandje is leeg."
    ]);
    exit;
}


// -----------------------------
// 6) Güvenlik → fiyat backend’de yeniden hesaplanır
// -----------------------------
$calc_subtotal = 0.0;

foreach ($items as $item) {

    if (!isset($item["product_id"], $item["quantity"], $item["unit_price"])) {
        echo json_encode(["status" => "error", "message" => "Ongeldig product gegevens."]);
        exit;
    }

    $pid   = (int)$item["product_id"];
    $qty   = (int)$item["quantity"];
    $uprice = (float)$item["unit_price"];

    // Ürün doğru mu?
    $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
    $stmt->execute([$pid]);
    $db_price = $stmt->fetchColumn();

    if (!$db_price) {
        echo json_encode(["status" => "error", "message" => "Product bestaat niet: {$pid}"]);
        exit;
    }

    // Extras kontrol
    $extras_total = 0.0;
    $extras = $item["extras"] ?? [];

    foreach ($extras as $ex) {
        if (!isset($ex["id"], $ex["price"])) {
            echo json_encode(["status" => "error", "message" => "Ongeldige extra gegevens."]);
            exit;
        }
        $extras_total += (float)$ex["price"];
    }

    $line_total = ($uprice + $extras_total) * $qty;
    $calc_subtotal += $line_total;
}

if (abs($subtotal - $calc_subtotal) > 0.01) {
    echo json_encode([
        "status" => "error",
        "message" => "Prijscontrole mislukt. Herlaad de pagina."
    ]);
    exit;
}


// -----------------------------
// 7) Veritabanına yaz (transaction)
// -----------------------------
try {
    $pdo->beginTransaction();

    // ORDER INSERT
    $stmt = $pdo->prepare("
        INSERT INTO orders 
        (customer_name, email, phone, street, zip, city, delivery_mode, 
         delivery_fee, subtotal, total, created_at)
        VALUES 
        (:customer_name, :email, :phone, :street, :zip, :city, :delivery_mode,
         :delivery_fee, :subtotal, :total, NOW())
    ");

    $stmt->execute([
        ":customer_name" => $name,
        ":email"         => $email,
        ":phone"         => $phone,
        ":street"        => $street,
        ":zip"           => $zip,
        ":city"          => $city,
        ":delivery_mode" => $mode,
        ":delivery_fee"  => $delivery_fee,
        ":subtotal"      => $subtotal,
        ":total"         => $total,
    ]);

    $order_id = (int)$pdo->lastInsertId();


    // -------------- ORDER ITEMS --------------
    $stmtItem = $pdo->prepare("
        INSERT INTO order_items 
        (order_id, product_id, product_name, qty, unit_price, total_price)
        VALUES
        (:order_id, :product_id, :product_name, :qty, :unit_price, :total_price)
    ");

    // -------------- EXTRAS -------------------
    $stmtExtra = $pdo->prepare("
        INSERT INTO order_item_extras
        (order_item_id, extra_id, extra_name, price)
        VALUES
        (:order_item_id, :extra_id, :extra_name, :price)
    ");


    foreach ($items as $item) {

        $pid   = (int)$item["product_id"];
        $nameP = $item["product_name"] ?? $item["name"];
        $qty   = (int)$item["quantity"];
        $uprice = (float)$item["unit_price"];
        $extras = $item["extras"] ?? [];

        $extras_total = array_sum(array_column($extras, "price"));
        $line_total   = ($uprice + $extras_total) * $qty;

        // Insert order item
        $stmtItem->execute([
            ":order_id"     => $order_id,
            ":product_id"   => $pid,
            ":product_name" => $nameP,
            ":qty"          => $qty,
            ":unit_price"   => $uprice,
            ":total_price"  => $line_total
        ]);

        $item_id = (int)$pdo->lastInsertId();

        // Insert extras
        foreach ($extras as $ex) {
            $stmtExtra->execute([
                ":order_item_id" => $item_id,
                ":extra_id"      => (int)$ex["id"],
                ":extra_name"    => $ex["name"],
                ":price"         => (float)$ex["price"]
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "order_id" => $order_id,
        "total" => $total
    ]);
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    file_put_contents(
        __DIR__ . '/../debug_place_order.log',
        '['.date('Y-m-d H:i:s').'] '.$e->getMessage()."\n",
        FILE_APPEND
    );

    echo json_encode([
        "status" => "error",
        "message" => "Server fout: ".$e->getMessage()
    ]);
    exit;
}

?>