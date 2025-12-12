<?php
$pageTitle = 'Extra bewerken';
$activeMenu = 'extras';
require_once __DIR__ . '/_header.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM extras WHERE id = ?");
$stmt->execute([$id]);
$ex = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ex) {
    echo "<p class='text-red-600 text-center font-semibold'>Extra niet gevonden.</p>";
    require_once __DIR__ . '/_footer.php';
    exit;
}
?>

<div class="max-w-xl mx-auto bg-white border border-slate-200 shadow-sm rounded-2xl p-6">

    <form action="extras_edit_action.php" method="post" class="space-y-6">

        <input type="hidden" name="id" value="<?= $ex['id'] ?>">

        <div>
            <label class="text-sm font-semibold mb-1 block">Naam</label>
            <input type="text" name="name" required
                   value="<?= htmlspecialchars($ex['name']) ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300">
        </div>

        <div>
            <label class="text-sm font-semibold mb-1 block">Prijs</label>
            <input type="number" name="price" step="0.01" required
                   value="<?= $ex['price'] ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1"
                <?= $ex['is_active'] ? 'checked' : '' ?>
                class="h-4 w-4">
            <label class="text-sm">Actief</label>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-200 gap-3">
            <a href="extras.php"
               class="px-4 py-2 border rounded-xl text-sm font-semibold hover:bg-slate-50">
                Annuleren
            </a>

            <button class="px-5 py-2 bg-slate-900 text-white rounded-xl text-sm font-semibold hover:bg-slate-800">
                Opslaan
            </button>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
