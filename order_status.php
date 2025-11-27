<?php
session_start();
require_once 'config.php'; // Veritabanı ve Mollie ayarlarını yükle

// URL'den sipariş ID'sini al
$order_id = $_GET['order_id'] ?? null;

// Sipariş verilerini çek
$order = null;
if ($order_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Hata durumunda sipariş bulunamadı olarak ele al
        $order = null;
    }
}

// Sipariş durumu ve mesaj ayarlaması
$status_title = "Sipariş Durumu";
$status_message = "Siparişinizi şu anda bulamıyoruz. Lütfen sipariş numaranızı kontrol edin.";
$icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>';
$bg_color = 'bg-gray-100';

if ($order) {
    switch ($order['payment_status']) {
        case 'paid':
            $status_title = "Ödeme Başarılı! 🎉";
            $status_message = "Siparişiniz başarıyla ödendi ve hazırlanmaya başlandı. Onayınız için teşekkür ederiz!";
            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-600"><path d="M22 11.08V12a10 10 0 1 1-5.6-8.9M13 3h4l5 5M22 8l-6 6-3-3"/></svg>';
            $bg_color = 'bg-green-100 border-green-300';
            break;
        case 'unpaid':
        case 'open':
            // Mollie ödemesi başlatıldı ancak henüz ödenmedi
            $status_title = "Ödeme Bekleniyor...";
            $status_message = "Ödeme işleminiz devam ediyor. Ödeme sağlayıcısının onayını bekliyoruz.";
            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-yellow-600"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>';
            $bg_color = 'bg-yellow-100 border-yellow-300';
            break;
        case 'failed':
        case 'expired':
        case 'canceled':
            $status_title = "Ödeme Başarısız/İptal Edildi";
            $status_message = "Ödemeniz tamamlanamadı. Lütfen tekrar sipariş vermeyi deneyin.";
            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-600"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>';
            $bg_color = 'bg-red-100 border-red-300';
            break;
        case 'cash_pending':
            // Nakit ödeme ise her zaman 'yeni' statüsündedir
            $status_title = "Siparişiniz Alındı!";
            $status_message = "Siparişiniz onaylandı ve mutfağa iletildi. Siparişinizi " . ($order['delivery_type'] === 'delivery' ? 'teslim alırken' : 'kasada') . " ödeme yapabilirsiniz.";
            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-600"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/><path d="m9 12 2 2 4-4"/></svg>';
            $bg_color = 'bg-blue-100 border-blue-300';
            break;
        default:
            // Yeni ve admin panelinde onaylanmayı bekleyen siparişler
            $status_title = "Siparişiniz Alındı!";
            $status_message = "Siparişiniz başarıyla kaydedildi. Durum takibi için lütfen bu sayfayı takip edin.";
            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-orange-600"><path d="M12 2a10 10 0 0 0 0 20 10 10 0 0 0 0-20z"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>';
            $bg_color = 'bg-orange-100 border-orange-300';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sipariş Durumu #<?= htmlspecialchars($order_id) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; color: #1f2937; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="max-w-xl w-full bg-white p-8 rounded-xl shadow-2xl text-center">
        
        <div class="p-8 rounded-xl border-2 <?= $bg_color ?> transition-all mb-6">
            <?= $icon_svg ?>
            <h1 class="text-3xl font-extrabold mt-4 mb-2 text-gray-800"><?= $status_title ?></h1>
            <p class="text-gray-600"><?= $status_message ?></p>
        </div>

        <?php if ($order): ?>
            <div class="text-left space-y-4 pt-4 border-t border-gray-200">
                <h2 class="text-xl font-bold text-gray-800">Sipariş Detayları</h2>

                <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                    <span class="font-semibold text-gray-600">Sipariş No:</span>
                    <span class="font-extrabold text-orange-600 text-xl">#<?= htmlspecialchars($order['id']) ?></span>
                </div>
                <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                    <span class="font-semibold text-gray-600">Toplam Tutar:</span>
                    <span class="font-bold text-gray-900">€<?= number_format($order['total_price'], 2, ',', '.') ?></span>
                </div>
                <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                    <span class="font-semibold text-gray-600">Ödeme Şekli:</span>
                    <span class="font-bold capitalize"><?= htmlspecialchars($order['payment_method']) ?> (<?= htmlspecialchars($order['payment_status']) ?>)</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="font-semibold text-gray-600">Teslimat Şekli:</span>
                    <span class="font-bold capitalize"><?= htmlspecialchars($order['delivery_type'] == 'delivery' ? 'Teslimat' : 'Al Götür') ?></span>
                </div>
            </div>
            
            <?php 
            // Adres detaylarını göster (sadece teslimat ise)
            if ($order['delivery_type'] === 'delivery'): ?>
                <div class="mt-6 p-4 bg-gray-50 rounded-lg text-left">
                    <h3 class="font-bold mb-2 text-gray-800">Teslimat Adresi:</h3>
                    <p class="text-sm"><?= htmlspecialchars($order['customer_name']) ?></p>
                    <p class="text-sm"><?= htmlspecialchars($order['street'] . ' ' . $order['house_number']) ?></p>
                    <p class="text-sm"><?= htmlspecialchars($order['zip'] . ' ' . $order['city']) ?></p>
                    <p class="text-sm mt-1 text-gray-500">Telefon: <?= htmlspecialchars($order['phone']) ?></p>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <div class="mt-8 pt-4 border-t border-gray-200">
            <a href="/" class="bg-black text-white font-bold py-3 px-6 rounded-xl hover:bg-gray-800 transition-colors inline-block">Ana Sayfaya Dön</a>
        </div>
    </div>
</body>
</html>