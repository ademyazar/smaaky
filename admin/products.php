<?php
$pageTitle = 'Producten';
$pageSubtitle = 'Smaaky menu beheren';
$activeMenu = 'products';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_functions.php';
requireAdmin();

$stmt = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    ORDER BY c.sort_order, p.sort_order, p.name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="flex justify-between items-center mb-4">
    <h2 class="text-sm font-semibold text-slate-700">Alle producten</h2>
    <a href="product_add.php"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 text-slate-50 text-sm font-semibold hover:bg-slate-800">
        <span>＋</span> <span>Nieuw product</span>
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
        <tr>
            <th class="px-4 py-2 text-left">ID</th>
            <th class="px-4 py-2 text-left">Naam</th>
            <th class="px-4 py-2 text-left">Categorie</th>
            <th class="px-4 py-2 text-right">Prijs</th>
            <th class="px-4 py-2 text-center">Toppings</th>
            <th class="px-4 py-2 text-center">Actief</th>
            <th class="px-4 py-2 text-right">Acties</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($products as $p): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-2 text-xs text-slate-500">#<?= $p['id'] ?></td>
                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($p['name']) ?></td>
                <td class="px-4 py-2 text-slate-600"><?= htmlspecialchars($p['category_name']) ?></td>
                <td class="px-4 py-2 text-right">€<?= number_format($p['price'], 2) ?></td>
                <td class="px-4 py-2 text-center text-xs">
                    <?php if ($p['has_toppings']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
                            Ja
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-50 text-slate-500">
                            Nee
                        </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-center text-xs">
                    <?php if ($p['is_active']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
                            Actief
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-50 text-slate-500">
                            Verborgen
                        </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-right space-x-2">
                    <a href="product_edit.php?id=<?= $p['id'] ?>" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Bewerken</a>
                    <!-- Silme için backend yazarsan: -->
                    <!-- <a href="product_delete.php?id=..." class="text-xs text-red-600">Verwijderen</a> -->
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($products)): ?>
            <tr><td colspan="7" class="px-4 py-4 text-sm text-slate-500">Nog geen producten.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
