<?php
// admin/dashboard.php

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../config.php';
session_start();
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_functions.php';
requireAdmin();

// --- METRİKLERİ HESAPLA ---

// Varsayılan değerler
$totalOrders      = 0;
$todayOrders      = 0;
$totalRevenue     = 0.0;
$todayRevenue     = 0.0;
$activeProducts   = 0;
$activeExtras     = 0;
$recentOrders     = [];
$topProducts      = [];
$chartLabels      = [];
$chartDataOrders  = [];
$chartDataRevenue = [];

try {
    // Toplam sipariş
    $totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    // Bugünkü sipariş
    $todayOrders = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn();

    // Toplam ciro
    $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders")->fetchColumn();

    // Bugünkü ciro
    $todayRevenue = (float)$pdo->query("
        SELECT COALESCE(SUM(total),0) 
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn();

    // Aktif ürünler
    $activeProducts = (int)$pdo->query("
        SELECT COUNT(*) FROM products WHERE is_active = 1
    ")->fetchColumn();

    // Aktif toppings
    $activeExtras = (int)$pdo->query("
        SELECT COUNT(*) FROM extras WHERE is_active = 1
    ")->fetchColumn();

    // Son 5 sipariş
    $stmt = $pdo->query("
        SELECT id, customer_name, total, created_at, status 
        FROM orders 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // En çok satılan 5 ürün (order_items üzerinden)
    $stmt = $pdo->query("
        SELECT oi.product_name, SUM(oi.qty) AS total_qty
        FROM order_items oi
        GROUP BY oi.product_name
        ORDER BY total_qty DESC
        LIMIT 5
    ");
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Son 7 gün sipariş grafiği (bugün dahil)
    $stmt = $pdo->query("
        SELECT DATE(created_at) AS d, COUNT(*) AS c, COALESCE(SUM(total),0) AS t
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY d ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Harita: tarih -> {count, total}
    $map = [];
    foreach ($rows as $r) {
        $map[$r['d']] = [
            'c' => (int)$r['c'],
            't' => (float)$r['t'],
        ];
    }

    // Son 7 günü doldur
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} day"));
        $label = date('d/m', strtotime($date));
        $chartLabels[] = $label;
        if (isset($map[$date])) {
            $chartDataOrders[]  = $map[$date]['c'];
            $chartDataRevenue[] = round($map[$date]['t'], 2);
        } else {
            $chartDataOrders[]  = 0;
            $chartDataRevenue[] = 0;
        }
    }

} catch (Throwable $e) {
    // Hata durumunda çok sert patlamasın
    // İstersen $e->getMessage()’i loglayabilirsin.
}

require_once __DIR__ . '/_header.php';
?>

<!-- Üst metrik kartları -->
<section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <!-- Toplam sipariş -->
    <div class="bg-white rounded-2xl p-4 card-shadow border border-slate-100 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-500">Totaal bestellingen</p>
            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
                Vandaag: <?= $todayOrders ?>
            </span>
        </div>
        <div class="text-2xl font-black tracking-tight">
            <?= number_format($totalOrders, 0, ',', '.') ?>
        </div>
    </div>

    <!-- Toplam ciro -->
    <div class="bg-white rounded-2xl p-4 card-shadow border border-slate-100 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-500">Totale omzet</p>
            <span class="text-xs px-2 py-0.5 rounded-full bg-orange-50 text-orange-700">
                Vandaag: € <?= number_format($todayRevenue, 2, ',', '.') ?>
            </span>
        </div>
        <div class="text-2xl font-black tracking-tight">
            € <?= number_format($totalRevenue, 2, ',', '.') ?>
        </div>
    </div>

    <!-- Aktif ürünler -->
    <div class="bg-white rounded-2xl p-4 card-shadow border border-slate-100 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-500">Actieve producten</p>
        </div>
        <div class="text-2xl font-black tracking-tight">
            <?= $activeProducts ?>
        </div>
        <p class="text-[11px] text-slate-500">Producten die zichtbaar zijn in de bestel-app.</p>
    </div>

    <!-- Aktif toppings -->
    <div class="bg-white rounded-2xl p-4 card-shadow border border-slate-100 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-500">Actieve toppings</p>
        </div>
        <div class="text-2xl font-black tracking-tight">
            <?= $activeExtras ?>
        </div>
        <p class="text-[11px] text-slate-500">Extra’s die klanten kunnen toevoegen aan hun burger.</p>
    </div>
</section>

<!-- Grafik + Popüler ürünler -->
<section class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
    <!-- Chart -->
    <div class="bg-white rounded-2xl p-4 card-shadow border border-slate-100 xl:col-span-2">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-slate-800">Bestellingen laatste 7 dagen</h2>
            <span class="text-[11px] text-slate-500">Aantal bestellingen & omzet</span>
        </div>
        <canvas id="ordersChart" class="w-full h-40"></canvas>
    </div>

    <!-- Top producten -->
    <div class="bg-white rounded-2xl p-4 card-shadow border border-slate-100">
        <h2 class="text-sm font-semibold text-slate-800 mb-3">Meest verkochte producten</h2>
        <div class="space-y-2 text-sm">
            <?php if ($topProducts): ?>
                <?php foreach ($topProducts as $p): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-6 w-6 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center text-xs">
                                🍔
                            </span>
                            <span><?= htmlspecialchars($p['product_name']) ?></span>
                        </div>
                        <span class="text-xs font-semibold text-slate-600">
                            <?= (int)$p['total_qty'] ?>x
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-xs text-slate-500">Nog geen besteldata om te tonen.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Son siparişler -->
<section class="bg-white rounded-2xl p-4 card-shadow border border-slate-100">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-slate-800">Laatste bestellingen</h2>
        <a href="orders.php" class="text-xs font-semibold text-orange-600 hover:text-orange-700">
            Alles bekijken →
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="text-xs text-slate-400 border-b border-slate-100">
                <th class="text-left py-2 pr-4">#</th>
                <th class="text-left py-2 pr-4">Klant</th>
                <th class="text-left py-2 pr-4">Totaal</th>
                <th class="text-left py-2 pr-4">Datum</th>
                <th class="text-left py-2 pr-4">Status</th>
                <th class="text-right py-2">Actie</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($recentOrders): ?>
                <?php foreach ($recentOrders as $o): ?>
                    <tr class="border-b border-slate-50 hover:bg-slate-50/80">
                        <td class="py-2 pr-4 text-xs text-slate-500">
                            #<?= (int)$o['id'] ?>
                        </td>
                        <td class="py-2 pr-4">
                            <div class="text-sm font-medium text-slate-800">
                                <?= htmlspecialchars($o['customer_name'] ?: 'Onbekend') ?>
                            </div>
                        </td>
                        <td class="py-2 pr-4 text-sm">
                            € <?= number_format((float)$o['total'], 2, ',', '.') ?>
                        </td>
                        <td class="py-2 pr-4 text-xs text-slate-500">
                            <?= htmlspecialchars($o['created_at']) ?>
                        </td>
                        <td class="py-2 pr-4">
                            <?php
                            $status = $o['status'] ?? 'nieuw';
                            $colorClass = match ($status) {
                                'nieuw'          => 'bg-orange-50 text-orange-700',
                                'bereiding'      => 'bg-blue-50 text-blue-700',
                                'bezorging'      => 'bg-purple-50 text-purple-700',
                                'afgeleverd'     => 'bg-emerald-50 text-emerald-700',
                                'geannuleerd'    => 'bg-rose-50 text-rose-700',
                                default          => 'bg-slate-50 text-slate-700',
                            };
                            ?>
                            <span class="badge-status <?= $colorClass ?>">
                                <?= ucfirst($status) ?>
                            </span>
                        </td>
                        <td class="py-2 text-right">
                            <a href="order_view.php?id=<?= (int)$o['id'] ?>"
                               class="text-xs font-semibold text-slate-600 hover:text-slate-900">
                                Bekijken →
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="py-4 text-xs text-slate-500 text-center">
                        Nog geen bestellingen gevonden.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('ordersChart').getContext('2d');

    const labels  = <?= json_encode($chartLabels) ?>;
    const orders  = <?= json_encode($chartDataOrders) ?>;
    const revenue = <?= json_encode($chartDataRevenue) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Bestellingen',
                    data: orders,
                    borderColor: '#fb923c',
                    backgroundColor: 'rgba(251, 146, 60, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y',
                },
                {
                    label: 'Omzet (€)',
                    data: revenue,
                    borderColor: '#0f172a',
                    backgroundColor: 'rgba(15, 23, 42, 0.06)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    position: 'left'
                },
                y1: {
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    position: 'right'
                }
            },
            plugins: {
                legend: {
                    labels: { font: { size: 11 } }
                }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>