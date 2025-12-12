<?php
$pageTitle = 'Product bewerken';
$activeMenu = 'products';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);

// ÜRÜNÜ GETİR
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "<p class='text-red-600 text-center font-semibold'>Product niet gevonden.</p>";
    require_once __DIR__ . '/_footer.php';
    exit;
}

// KATEGORİLER
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY sort_order, name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TÜM EXTRAS
$stmtEx = $pdo->query("SELECT id, name, price, is_active FROM extras ORDER BY name ASC");
$allExtras = $stmtEx->fetchAll(PDO::FETCH_ASSOC);

// BU ÜRÜNE BAĞLI MEVCUT EXTRAS
$stmtPE = $pdo->prepare("SELECT extra_id FROM product_extras WHERE product_id = ?");
$stmtPE->execute([$product['id']]);
$currentExtraIds = $stmtPE->fetchAll(PDO::FETCH_COLUMN);
$currentExtraIds = array_map('intval', $currentExtraIds);
?>

<div class="max-w-3xl mx-auto bg-white border border-slate-200 shadow-sm rounded-2xl p-6">

    <form action="product_edit_action.php" method="post" class="space-y-6">
        <input type="hidden" name="id" value="<?= $product['id'] ?>">

        <!-- Naam -->
        <div>
            <label class="block text-sm font-semibold mb-1">Naam</label>
            <input type="text" name="name" required
                   value="<?= htmlspecialchars($product['name']) ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
        </div>

        <!-- Beschrijving -->
        <div>
            <label class="block text-sm font-semibold mb-1">Beschrijving</label>
            <textarea name="description" rows="3"
                      class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900"><?= htmlspecialchars($product['description']) ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Categorie -->
            <div>
                <label class="block text-sm font-semibold mb-1">Categorie</label>
                <select name="category_id" required
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $product['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Prijs -->
            <div>
                <label class="block text-sm font-semibold mb-1">Prijs (€)</label>
                <input type="number" name="price" step="0.01" required
                       value="<?= $product['price'] ?>"
                       class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
            </div>
        </div>

        <!-- Afbeelding -->
        <div>
            <label class="block text-sm font-semibold mb-1">Afbeelding pad (bijv. /images/burger.jpg)</label>
            <input type="text" name="image"
                   value="<?= htmlspecialchars($product['image']) ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
        </div>

        <!-- Alternatief URL -->
        <div>
            <label class="block text-sm font-semibold mb-1">Image URL (extern, optioneel)</label>
            <input type="text" name="image_url"
                   value="<?= htmlspecialchars($product['image_url']) ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
        </div>

        <!-- Toppings flag -->
        <div class="flex items-center gap-2">
            <input type="checkbox" name="has_toppings" value="1"
                   <?= $product['has_toppings'] ? 'checked' : '' ?>
                   class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
            <label class="text-sm">
                Dit product kan <span class="font-semibold">toppings</span krijgen.
            </label>
        </div>

        <!-- Actief -->
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1"
                   <?= $product['is_active'] ? 'checked' : '' ?>
                   class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
            <label class="text-sm">Product is zichtbaar in de bestel-app</label>
        </div>

        <!-- EXTRAS (TOPPINGS) LİSTESİ -->
        <?php if (!empty($allExtras)): ?>
            <div class="pt-4 border-t border-slate-200">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold text-slate-800">Toppings voor dit product</h3>
                    <p class="text-xs text-slate-500">
                        Alleen relevant als <strong>toppings</strong> zijn ingeschakeld.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <?php foreach ($allExtras as $ex): ?>
                        <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl border border-slate-200 hover:bg-slate-50">
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="extra_ids[]"
                                    value="<?= $ex['id'] ?>"
                                    class="h-4 w-4 rounded border-slate-300"
                                    <?= in_array((int)$ex['id'], $currentExtraIds, true) ? 'checked' : '' ?>
                                >
                                <span class="text-sm <?= $ex['is_active'] ? '' : 'line-through text-slate-400' ?>">
                                    <?= htmlspecialchars($ex['name']) ?>
                                </span>
                            </div>
                            <span class="text-xs font-semibold text-slate-600">
                                €<?= number_format($ex['price'], 2) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p class="mt-2 text-xs text-slate-500">
                    Tip: Voor burgers en loaded fries meerdere toppings selecteren; voor dranken/snacks meestal geen toppings.
                </p>
            </div>
        <?php endif; ?>

        <!-- Opslaan -->
        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-4">
            <a href="products.php"
               class="px-4 py-2 rounded-xl border border-slate-300 text-sm font-semibold hover:bg-slate-100">
                Annuleren
            </a>

            <button type="submit"
                    class="px-5 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
                Opslaan
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
