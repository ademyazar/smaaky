<?php
$pageTitle = "Bestellingen";
$activeMenu = "orders";
require_once __DIR__ . "/_header.php";
session_start();
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_functions.php';
requireAdmin();

$stmt = $pdo->query("
    SELECT id, customer_name, phone, street, house_number, zip, city, total, status, created_at
    FROM orders
    ORDER BY created_at DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status colors
$statusColors = [
    "nieuw" => "bg-blue-100 text-blue-700",
    "bereiden" => "bg-yellow-100 text-yellow-700",
    "klaar" => "bg-purple-100 text-purple-700",
    "bezorgen" => "bg-green-100 text-green-700",
    "afgeleverd" => "bg-gray-100 text-gray-700",
    "geannuleerd" => "bg-red-100 text-red-700",
];
?>

<div class="page-header mb-6">
    <h1 class="text-2xl font-semibold">Bestellingen</h1>
    <p class="text-gray-500">Bekijk en beheer alle binnenkomende bestellingen.</p>
</div>

<!-- Filters -->
<div class="flex flex-col md:flex-row md:items-center gap-3 mb-6">
    <input
        type="text"
        id="search"
        placeholder="Zoek op naam, telefoon, postcode…"
        class="w-full md:w-96 px-4 py-2 rounded-xl border border-gray-300 focus:ring focus:ring-blue-200"
    >
    
    <select id="filter-status" class="px-4 py-2 rounded-xl border border-gray-300 focus:ring focus:ring-blue-200">
        <option value="">Alle statussen</option>
        <option value="nieuw">Nieuw</option>
        <option value="bereiden">Bezig met bereiden</option>
        <option value="klaar">Klaar voor bezorging</option>
        <option value="bezorgen">Bezorging gestart</option>
        <option value="afgeleverd">Afgeleverd</option>
        <option value="geannuleerd">Geannuleerd</option>
    </select>
</div>

<!-- Order Cards -->
<div id="order-list" class="grid gap-4">

    <?php foreach ($orders as $o): ?>
        <?php $sColor = $statusColors[$o["status"]] ?? "bg-gray-100 text-gray-700"; ?>

        <div class="order-item border border-gray-200 rounded-2xl p-4 bg-white shadow-sm hover:shadow-md transition cursor-pointer"
             data-name="<?= strtolower($o['customer_name']) ?>"
             data-phone="<?= strtolower($o['phone']) ?>"
             data-zip="<?= strtolower($o['zip']) ?>"
             data-status="<?= strtolower($o['status']) ?>"
        >
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-semibold text-lg"><?= htmlspecialchars($o["customer_name"]) ?></h2>

                <span class="text-xs px-3 py-1 rounded-full <?= $sColor ?>">
                    <?= ucfirst($o["status"]) ?>
                </span>
            </div>

            <div class="text-sm text-gray-600 space-y-1">
                <p><strong>Adres:</strong> <?= $o["street"] ?> <?= $o["house_number"] ?>, <?= $o["zip"] ?> <?= $o["city"] ?></p>
                <p><strong>Tel:</strong> <?= $o["phone"] ?></p>
                <p><strong>Datum:</strong> <?= $o["created_at"] ?></p>
            </div>

            <div class="flex items-center justify-between mt-4">
                <span class="text-lg font-semibold">€ <?= number_format($o["total"], 2) ?></span>

                <a href="order_view.php?id=<?= $o["id"] ?>"
                   class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition text-sm">
                    Bekijken →
                </a>
            </div>
        </div>

    <?php endforeach; ?>

    <?php if (empty($orders)): ?>
        <p class="text-gray-500 text-center py-12">Geen bestellingen gevonden.</p>
    <?php endif; ?>

</div>

<script>
const search = document.getElementById("search");
const filterStatus = document.getElementById("filter-status");
const list = document.querySelectorAll(".order-item");

function applyFilters() {
    const q = search.value.toLowerCase();
    const st = filterStatus.value;

    list.forEach(card => {
        const matchesText =
            card.dataset.name.includes(q) ||
            card.dataset.phone.includes(q) ||
            card.dataset.zip.includes(q);

        const matchesStatus = st === "" || card.dataset.status === st;

        card.style.display = (matchesText && matchesStatus) ? "" : "none";
    });
}

search.addEventListener("input", applyFilters);
filterStatus.addEventListener("change", applyFilters);
</script>

<?php require_once __DIR__ . "/_footer.php"; ?>