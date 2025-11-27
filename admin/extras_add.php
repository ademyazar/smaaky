<?php
$pageTitle = 'Nieuwe extra';
$activeMenu = 'extras';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_functions.php';
requireAdmin();
?>

<div class="max-w-xl mx-auto bg-white border border-slate-200 shadow-sm rounded-2xl p-6">

    <form action="extras_add_action.php" method="post" class="space-y-6">

        <div>
            <label class="text-sm font-semibold mb-1 block">Naam</label>
            <input type="text" name="name" required
                   class="w-full px-3 py-2 rounded-xl border border-slate-300">
        </div>

        <div>
            <label class="text-sm font-semibold mb-1 block">Prijs (€)</label>
            <input type="number" step="0.01" name="price" required
                   class="w-full px-3 py-2 rounded-xl border border-slate-300">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4">
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
