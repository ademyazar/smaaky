<?php
// order_status.php - Sipariş durumunu gösterir
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    die("Config bulunamadı.");
}

$order_id = $_GET['order_id'] ?? 0;
$order = null;

if ($order_id) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$order) {
    die("Bestelling niet gevonden.");
}

// Durum Etiketleri
$statusLabels = [
    'pending' => 'In afwachting',
    'new' => 'Bestelling Ontvangen',
    'cooking' => 'Wordt Bereid',
    'delivering' => 'Onderweg',
    'completed' => 'Bezorgd',
    'cancelled' => 'Geannuleerd'
];

$statusIcons = [
    'pending' => 'clock',
    'new' => 'check-circle',
    'cooking' => 'flame',
    'delivering' => 'bike',
    'completed' => 'thumbs-up',
    'cancelled' => 'x-circle'
];

$currentStatus = $order['status'] ?? 'pending';
$statusText = $statusLabels[$currentStatus] ?? 'Onbekend';
$icon = $statusIcons[$currentStatus] ?? 'help-circle';

// Renk ayarı
$bgColor = 'bg-blue-50';
$textColor = 'text-blue-600';
if ($currentStatus == 'cooking') { $bgColor = 'bg-orange-50'; $textColor = 'text-orange-600'; }
if ($currentStatus == 'completed') { $bgColor = 'bg-green-50'; $textColor = 'text-green-600'; }

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bestelstatus #<?= htmlspecialchars($order_id) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <meta http-equiv="refresh" content="30"> <!-- Sayfayı her 30 saniyede bir yenile -->
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white max-w-md w-full rounded-2xl shadow-xl p-8 text-center">
        <div class="mb-6 flex justify-center">
            <div class="<?= $bgColor ?> p-6 rounded-full animate-pulse">
                <i data-lucide="<?= $icon ?>" class="w-12 h-12 <?= $textColor ?>"></i>
            </div>
        </div>

        <h1 class="text-2xl font-black mb-2">Bestelling #<?= htmlspecialchars($order_id) ?></h1>
        <p class="text-gray-500 mb-6">Bedankt voor je bestelling, <?= htmlspecialchars($order['customer_name']) ?>!</p>

        <div class="border-t border-b border-gray-100 py-6 mb-6">
            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-2">Huidige Status</h2>
            <div class="text-3xl font-black <?= $textColor ?>">
                <?= $statusText ?>
            </div>
        </div>

        <div class="space-y-3 text-sm text-gray-600 mb-8">
            <div class="flex justify-between">
                <span>Totaalbedrag:</span>
                <span class="font-bold text-gray-900">€<?= number_format($order['total'], 2) ?></span>
            </div>
            <div class="flex justify-between">
                <span>Betaalmethode:</span>
                <span class="font-bold uppercase"><?= htmlspecialchars($order['payment_method']) ?></span>
            </div>
        </div>

        <a href="index.php" class="block w-full bg-black text-white py-4 rounded-xl font-bold hover:bg-gray-800 transition-colors">
            Terug naar Home
        </a>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>