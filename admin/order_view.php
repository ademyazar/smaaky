<?php
$pageTitle = "Bestelling bekijken";
$activeMenu = "orders";
require_once __DIR__ . "/_header.php";
require_once __DIR__ . '/_functions.php';
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ORDER
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<p class='text-red-600 p-6'>Bestelling niet gevonden.</p>";
    require_once __DIR__ . "/_footer.php";
    exit;
}

// ORDER ITEMS
$stmt = $pdo->prepare("
    SELECT id, product_name, qty, unit_price, total_price
    FROM order_items
    WHERE order_id = ?
");
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// EXTRAS
$stmt = $pdo->prepare("
    SELECT oie.order_item_id, oie.extra_name, oie.extra_price
    FROM order_item_extras oie
    WHERE oie.order_item_id IN (SELECT id FROM order_items WHERE order_id = ?)
");
$stmt->execute([$id]);
$extrasRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$extras = [];
foreach ($extrasRaw as $ex) {
    $extras[$ex["order_item_id"]][] = $ex;
}

// STATUS COLORS
$statusColors = [
    "nieuw"       => "bg-blue-100 text-blue-700",
    "bereiden"    => "bg-yellow-100 text-yellow-700",
    "klaar"       => "bg-purple-100 text-purple-700",
    "bezorgen"    => "bg-orange-100 text-orange-700",
    "afgeleverd"  => "bg-green-100 text-green-700",
    "geannuleerd" => "bg-red-100 text-red-700",
];
$color = $statusColors[$order["status"]] ?? "bg-gray-100 text-gray-700";
?>

<!-- PAGE HEADER -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold">Bestelling #<?= $order["id"] ?></h1>
        <p class="text-gray-500 mt-1">
            Geplaatst op <?= $order["created_at"] ?>
        </p>
    </div>

    <span class="px-4 py-2 text-sm rounded-full <?= $color ?>">
        <?= ucfirst($order["status"]) ?>
    </span>
</div>

<!-- GRID LAYOUT -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- CUSTOMER INFO -->
    <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm border p-5">
        <h2 class="text-lg font-semibold mb-4">Klant</h2>

        <div class="space-y-2 text-sm">
            <p><strong>Naam:</strong> <?= htmlspecialchars($order["customer_name"]) ?></p>
            <p><strong>Telefoon:</strong> <?= htmlspecialchars($order["phone"]) ?></p>
            <p><strong>E-mail:</strong> <?= htmlspecialchars($order["email"]) ?></p>

            <p>
                <strong>Adres:</strong><br>
                <?= $order["street"] ?> <?= $order["house_number"] ?><br>
                <?= $order["zip"] ?> <?= $order["city"] ?>
            </p>
        </div>
    </div>

    <!-- ORDER ITEMS -->
    <div class="lg:col-span-6 bg-white rounded-2xl shadow-sm border p-5">
        <h2 class="text-lg font-semibold mb-4">Bestelde producten</h2>

        <?php if (empty($items)): ?>
            <p class="text-gray-500">Geen orderregels gevonden.</p>
        <?php endif; ?>

        <div class="space-y-4">
            <?php foreach ($items as $item): ?>
                <div class="border-b pb-3">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="font-semibold"><?= htmlspecialchars($item["product_name"]) ?></h3>
                            <p class="text-sm text-gray-500"><?= $item["qty"] ?> × €<?= number_format($item["unit_price"], 2) ?></p>
                        </div>

                        <span class="font-semibold">
                            €<?= number_format($item["total_price"], 2) ?>
                        </span>
                    </div>

                    <!-- Extras -->
                    <?php if (!empty($extras[$item["id"]] ?? [])): ?>
                        <div class="mt-2 space-y-1 pl-4 border-l border-gray-300">
                            <?php foreach ($extras[$item["id"]] as $ex): ?>
                                <p class="text-sm text-gray-600">
                                    + <?= htmlspecialchars($ex["extra_name"]) ?>
                                    (<?= number_format($ex["extra_price"], 2) ?>)
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- STATUS PANEL -->
    <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm border p-5">
        <h2 class="text-lg font-semibold mb-4">Status wijzigen</h2>

        <?php
        $statuses = [
            "nieuw"       => "Nieuw",
            "bereiden"    => "Bezig met bereiden",
            "klaar"       => "Klaar voor bezorging",
            "bezorgen"    => "Bezorging gestart",
            "afgeleverd"  => "Afgeleverd",
            "geannuleerd" => "Geannuleerd"
        ];
        ?>

        <form method="post" action="update_status.php" class="space-y-2">
            <input type="hidden" name="id" value="<?= $order["id"] ?>">

            <?php foreach ($statuses as $key => $label): ?>
                <button
                    name="status"
                    value="<?= $key ?>"
                    class="w-full text-left px-4 py-2 rounded-xl border
                           <?php if ($order['status'] === $key): ?>
                                bg-blue-600 text-white border-blue-600
                           <?php else: ?>
                                hover:bg-gray-100
                           <?php endif; ?>
                           transition"
                >
                    <?= $label ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>

</div>

<?php require_once __DIR__ . "/_footer.php"; ?>