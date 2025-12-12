<?php
// admin/settings_payment.php
// Bu dosya admin/settings.php tarafından çağrılır, $pdo ve requireAdmin() zaten çalışmıştır.

// 1. Gerekli Ayarları Çek
// getSetting fonksiyonu admin/_functions.php'de olmalıdır
$mode = getSetting('mollie_mode') ?? 'test';
$testKey = getSetting('mollie_test_key') ?? '';
$liveKey = getSetting('mollie_live_key') ?? '';

// 2. Mesajları Hazırla
$message = '';
$message_type = 'success';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = 'Mollie Instellingen zijn succesvol opgeslagen.';
    } elseif ($_GET['status'] === 'error') {
        $message = 'Fout bij het opslaan van de instellingen. Controleer de API sleutel en machtigingen.';
        $message_type = 'error';
    }
}
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <!-- Mollie Ayarları Formu -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Mollie API Sleutels en Modus</h2>
            <p class="card-subtitle">
                Voer uw Mollie API-sleutels in en schakel tussen de Test- en Live-modus.
            </p>
        </div>
        <div class="card-body">
            
            <?php if ($message): ?>
                <!-- Tailwind tabanlı alert stilini kullandığınızı varsayarak -->
                <div class="p-3 mb-4 rounded-xl font-medium <?php echo $message_type === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="settings_payment_action.php" method="post" class="space-y-6"> 

                <!-- Mod Seçimi -->
                <div class="form-group">
                    <label class="form-label font-bold text-sm">Werkomgeving</label>
                    <div class="flex gap-4 mt-2">
                        <label class="radio-label flex items-center">
                            <input type="radio" name="mollie_mode" value="test" class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300" <?php echo $mode === 'test' ? 'checked' : ''; ?>>
                            <span class="ml-2 text-gray-700">Test (Ontwikkeling)</span>
                        </label>
                        <label class="radio-label flex items-center">
                            <input type="radio" name="mollie_mode" value="live" class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300" <?php echo $mode === 'live' ? 'checked' : ''; ?>>
                            <span class="ml-2 text-gray-700">Live (Productie)</span>
                        </label>
                    </div>
                </div>

                <!-- Test API Key -->
                <div class="form-group">
                    <label for="mollie_test_key" class="form-label block font-bold text-sm mb-1">Test API Sleutel (<span class="text-orange-600">test_...</span>)</label>
                    <input type="text" name="mollie_test_key" id="mollie_test_key" value="<?php echo htmlspecialchars($testKey); ?>" class="form-input w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-500" placeholder="test_...">
                    <p class="text-xs text-gray-500 mt-1">Huidige modus: <span class="font-semibold capitalize"><?= htmlspecialchars($mode) ?></span></p>
                </div>

                <!-- Live API Key -->
                <div class="form-group">
                    <label for="mollie_live_key" class="form-label block font-bold text-sm mb-1">Live API Sleutel (<span class="text-red-600">live_...</span>)</label>
                    <input type="text" name="mollie_live_key" id="mollie_live_key" value="<?php echo htmlspecialchars($liveKey); ?>" class="form-input w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-500" placeholder="live_...">
                </div>

                <!-- Kaydet Butonu -->
                <div class="form-actions pt-4">
                    <button type="submit" class="btn btn-primary bg-black text-white px-6 py-2.5 rounded-xl font-bold hover:bg-gray-800 transition-colors">
                        Instellingen Opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Info Card -->
    <div class="card bg-gray-50 p-6 rounded-2xl border border-gray-100">
        <h3 class="font-bold text-lg mb-3 text-gray-800">Mollie Integratie Informatie</h3>
        <ul class="space-y-3 text-sm text-gray-600">
            <li>
                <strong>Veritabanı Kayıtları:</strong><br>
                Sleutels worden opgeslagen in de <code>settings</code>-tabel (<code>mollie_test_key</code>, <code>mollie_live_key</code>, <code>mollie_mode</code>).
            </li>
            <li>
                <strong>Kritieke Webhook:</strong><br>
                Voor automatische status updates, stel de webhook URL in Mollie Dashboard in op:<br>
                <code class="block mt-1 p-2 bg-white rounded-lg border text-xs font-mono select-all">[UW_SITE_URL]/api/mollie_webhook.php</code>
            </li>
            <li>
                <strong>Foutopsporing:</strong><br>
                Als de pagina leeg blijft, controleer dan de Console (F12) op PHP 500 Fouten, wat meestal duidt op een ontbrekende bestand of functie.
            </li>
        </ul>
    </div>
</div>