<?php
$pageTitle = 'Extras (Toppings)';
$activeMenu = 'extras';
require_once __DIR__ . '/_header.php';
session_start();
require_once __DIR__ . '/_auth.php';

$stmt = $pdo->query("SELECT * FROM extras ORDER BY name ASC");
$extras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white border border-slate-200 shadow-sm rounded-2xl">

    <div class="flex justify-between items-center px-6 py-4 border-b border-slate-200">
        <h2 class="text-xl font-black">Extras (Toppings)</h2>

        <a href="extras_add.php"
           class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
            + Nieuwe extra
        </a>
    </div>

    <table class="w-full text-sm">
        <thead>
        <tr class="bg-slate-50 border-b border-slate-200">
            <th class="px-4 py-3 text-left font-semibold">Naam</th>
            <th class="px-4 py-3 text-left font-semibold">Prijs</th>
            <th class="px-4 py-3 font-semibold text-center">Actief</th>
            <th class="px-4 py-3 text-right font-semibold">Actie</th>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($extras as $ex): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="px-4 py-3"><?= htmlspecialchars($ex['name']) ?></td>

                <td class="px-4 py-3">€<?= number_format($ex['price'], 2) ?></td>

                <td class="px-4 py-3 text-center">
                    <?php if ($ex['is_active']): ?>
                        <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-lg">Actief</span>
                    <?php else: ?>
                        <span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-lg">Inactief</span>
                    <?php endif; ?>
                </td>

                <td class="px-4 py-3 text-right space-x-3">
                    <a href="extras_edit.php?id=<?= $ex['id'] ?>" class="text-blue-600 font-semibold hover:underline">
                        Bewerken
                    </a>

                    <a href="extras_delete.php?id=<?= $ex['id'] ?>"
                       onclick="return confirm('Weet je zeker dat je deze extra wilt verwijderen?')"
                       class="text-red-600 font-semibold hover:underline">
                        Verwijderen
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>

    </table>

</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
