<?php
$pageTitle = 'Categorieën';
$activeMenu = 'categories';
require_once __DIR__ . '/_header.php';
session_start();
require_once __DIR__ . '/_auth.php';

$stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white border border-slate-200 shadow-sm rounded-2xl">
    <div class="flex justify-between items-center px-6 py-4 border-b border-slate-200">
        <h2 class="text-xl font-black">Categorieën</h2>
        <a href="category_add.php"
           class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
            + Nieuwe categorie
        </a>
    </div>

    <table class="w-full text-sm">
        <thead>
        <tr class="bg-slate-50 border-b border-slate-200 text-left">
            <th class="px-4 py-3 font-semibold">#</th>
            <th class="px-4 py-3 font-semibold">Naam</th>
            <th class="px-4 py-3 font-semibold">Volgorde</th>
            <th class="px-4 py-3 font-semibold text-right">Actie</th>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="px-4 py-3"><?= $cat['id'] ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($cat['name']) ?></td>
                <td class="px-4 py-3"><?= (int)$cat['sort_order'] ?></td>
                <td class="px-4 py-3 text-right space-x-3">
                    <a href="category_edit.php?id=<?= $cat['id'] ?>"
                       class="text-blue-600 font-semibold hover:underline">Bewerken</a>

                    <a href="category_delete.php?id=<?= $cat['id'] ?>"
                       onclick="return confirm('Weet je zeker dat je deze categorie wilt verwijderen?')"
                       class="text-red-600 font-semibold hover:underline">Verwijderen</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
