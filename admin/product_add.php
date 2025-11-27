<?php
$pageTitle = 'Nieuw product';
$pageSubtitle = 'Voeg een nieuw product toe aan het Smaaky menu.';
$activeMenu = 'products';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_functions.php';
requireAdmin();

// Kategoriler
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY sort_order, name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="max-w-3xl mx-auto bg-white border border-slate-200 shadow-sm rounded-2xl p-6">

    <form action="product_add_action.php" method="post" class="space-y-6">

        <!-- Naam -->
        <div>
            <label class="block text-sm font-semibold mb-1">Naam</label>
            <input type="text" name="name" required
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
        </div>

        <!-- Beschrijving -->
        <div>
            <label class="block text-sm font-semibold mb-1">Beschrijving (optioneel)</label>
            <textarea name="description" rows="3"
                      class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Categorie -->
            <div>
                <label class="block text-sm font-semibold mb-1">Categorie</label>
                <select name="category_id" required
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
                    <option value="">Kies een categorie...</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Prijs -->
            <div>
                <label class="block text-sm font-semibold mb-1">Prijs (€)</label>
                <input type="number" name="price" step="0.01" min="0" required
                       class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
            </div>
        </div>

        <!-- Afbeelding -->
        <div>
            <label class="block text-sm font-semibold mb-1">Afbeelding URL</label>
            <input type="text" name="image"
                   placeholder="/images/burger.jpg"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
        </div>

        <!-- Image URL II (isteğe bağlı) -->
        <div>
            <label class="block text-sm font-semibold mb-1">Image URL (alternatief)</label>
            <input type="text" name="image_url"
                   placeholder="https://..."
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
        </div>

        <!-- Toppings -->
        <div class="flex items-center gap-2">
            <input type="checkbox" name="has_toppings" value="1"
                   class="h-4 w-4 border-slate-300 rounded">
            <label class="text-sm">Dit product kan toppings krijgen</label>
        </div>

        <!-- Actief -->
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" checked
                   class="h-4 w-4 border-slate-300 rounded">
            <label class="text-sm">Product is zichtbaar in de bestel-app</label>
        </div>

        <!-- Opslaan -->
        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
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
