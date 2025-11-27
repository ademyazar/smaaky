<?php
$pageTitle = 'Categorie bewerken';
$activeMenu = 'categories';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_functions.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$cat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cat) {
    echo "<p class='text-red-600 text-center font-semibold'>Categorie niet gevonden.</p>";
    require_once __DIR__ . '/_footer.php';
    exit;
}
?>

<div class="max-w-xl mx-auto bg-white border border-slate-200 shadow-sm rounded-2xl p-6">

    <form action="category_edit_action.php" method="post" class="space-y-6">

        <input type="hidden" name="id" value="<?= $cat['id'] ?>">

        <div>
            <label class="text-sm font-semibold mb-1 block">Naam</label>
            <input type="text" name="name" required
                   value="<?= htmlspecialchars($cat['name']) ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
        </div>

        <div>
            <label class="text-sm font-semibold mb-1 block">Volgorde</label>
            <input type="number" name="sort_order" min="0"
                   value="<?= (int)$cat['sort_order'] ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-slate-900">
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="categories.php"
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
