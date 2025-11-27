<?php
// Bu dosya, admin/index.php tarafından çağrılır ve gerekli yetkilendirme (authentication) zaten yapılmış kabul edilir.

// Veritabanı bağlantısının ($pdo) mevcut olduğundan emin olunur.

// Mevcut Mollie ayarlarını veritabanından çek
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('mollie_mode', 'mollie_test_key', 'mollie_live_key')");
$mollie_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$mode = $mollie_settings['mollie_mode'] ?? 'test';
$testKey = $mollie_settings['mollie_test_key'] ?? '';
$liveKey = $mollie_settings['mollie_live_key'] ?? '';

// Başarı mesajı kontrolü
$message = '';
$message_type = 'success';
if (isset($_GET['status']) && isset($_GET['subpage']) && $_GET['subpage'] === 'payment') {
    if ($_GET['status'] === 'success') {
        $message = 'Mollie Ayarları başarıyla güncellendi.';
    } elseif ($_GET['status'] === 'error') {
        $message = 'Ayarları güncellerken bir hata oluştu.';
        $message_type = 'error';
    }
}
?>

<div class="p-6 bg-white rounded-xl shadow-lg">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2">Mollie Ödeme Ayarları</h2>

    <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-lg text-sm <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Form action'ı, admin klasöründeki action dosyasına yönlendirilir -->
    <form action="admin/settings_payment_action.php" method="POST" class="space-y-6">

        <!-- Mod Seçimi -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Çalışma Modu</label>
            <div class="flex items-center space-x-6">
                <label class="inline-flex items-center">
                    <input type="radio" name="mollie_mode" value="test" class="form-radio h-4 w-4 text-orange-600 border-gray-300 focus:ring-orange-500" <?php echo $mode === 'test' ? 'checked' : ''; ?>>
                    <span class="ml-2 text-gray-700 font-medium">Test (Geliştirme)</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" name="mollie_mode" value="live" class="form-radio h-4 w-4 text-green-600 border-gray-300 focus:ring-green-500" <?php echo $mode === 'live' ? 'checked' : ''; ?>>
                    <span class="ml-2 text-gray-700 font-medium">Live (Canlı)</span>
                </label>
            </div>
            <p class="mt-2 text-xs text-gray-500">Mollie'nin hangi ortamda çalışacağını seçin. Canlı modda gerçek ödemeler gerçekleşir.</p>
        </div>

        <!-- Test API Key -->
        <div>
            <label for="mollie_test_key" class="block text-sm font-medium text-gray-700">Test API Anahtarı (<span class="font-mono text-xs">test_...</span>)</label>
            <input type="text" name="mollie_test_key" id="mollie_test_key" value="<?php echo htmlspecialchars($testKey); ?>" 
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-3 focus:ring-orange-500 focus:border-orange-500 transition-colors font-mono tracking-wide">
            <p class="mt-2 text-xs text-gray-500">Test ortamında ödeme oluşturmak için kullanılan Mollie anahtarı.</p>
        </div>

        <!-- Live API Key -->
        <div>
            <label for="mollie_live_key" class="block text-sm font-medium text-gray-700">Live API Anahtarı (<span class="font-mono text-xs">live_...</span>)</label>
            <input type="text" name="mollie_live_key" id="mollie_live_key" value="<?php echo htmlspecialchars($liveKey); ?>" 
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-3 focus:ring-green-500 focus:border-green-500 transition-colors font-mono tracking-wide">
            <p class="mt-2 text-xs text-gray-500">Gerçek, canlı ödemeler için kullanılan Mollie anahtarı.</p>
        </div>

        <!-- Kaydet Butonu -->
        <div class="pt-4 border-t mt-6">
            <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-black hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-black">
                Ayarları Kaydet
            </button>
        </div>
    </form>
</div>