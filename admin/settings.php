<?php
// admin/settings.php

$pageTitle  = 'Instellingen';
$activeMenu = 'settings';

// 1. ÖNCE config.php (PDO bağlantısı ve temel ayarlar)
require_once __DIR__ . '/../config.php';

// 2. SONRA session_start()
session_start();

// 3. EN SON auth ve functions
require_once __DIR__ . '/_auth.php';

// KRİTİK GÜVENLİK KONTROLÜ: _functions.php dosyasını yüklemeyi GARANTİ EDİYORUZ.
if (!function_exists('requireAdmin')) {
    require_once __DIR__ . '/_functions.php';
}

// 4. Yetkilendirme Kontrolü
requireAdmin();

// --- Subpage Navigasyonunu Tanımla ---
$subpage = $_GET['subpage'] ?? 'general'; // Varsayılan: general

// Ayar sekmeleri
$tabs = [
    'general'  => 'Algemeen (Openingstijden)',
    'order'    => 'Bestellingen & Levering',
    'payment'  => 'Betaling (Mollie)', 
    'email'    => 'E-mail',
    'password' => 'Wachtwoord',
    'users'    => 'Gebruikers',
    'roles'    => 'Rollen',
];

// Sayfa içeriğini çek
$contentFile = match($subpage) {
    'general'  => 'settings_general.php',
    'order'    => 'settings_order.php',
    'payment'  => 'settings_payment.php', 
    'email'    => 'settings_email.php',
    'password' => 'settings_password.php',
    'users'    => 'settings_users.php',
    'roles'    => 'settings_roles.php',
    default    => 'settings_general.php',
};

require_once __DIR__ . '/_header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Instellingen</h1>
        <p class="page-subtitle">
            Beheer alle algemene, bestel- en betalingsinstellingen van Smaaky.
        </p>
    </div>
</div>

<!-- Ayarlar Sekme Navigasyonu -->
<div class="card-tabs mb-6">
    <div class="tabs-nav">
        <?php foreach ($tabs as $key => $label): ?>
            <?php 
                $active = ($key === $subpage) ? 'active' : ''; 
                $url = 'settings.php?subpage=' . urlencode($key);
            ?>
            <a href="<?= $url ?>" class="tab-item <?= $active ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Ayar İçeriği -->
<div class="settings-content">
    <?php 
        $fullPath = __DIR__ . '/' . $contentFile;
        
        // Seçilen ayar dosyasını dahil et
        if (file_exists($fullPath)) {
            // Hata ayıklama çıktısı: Eğer burası çalışırsa sayfa boş kalmamalı
            // echo "DEBUG: Dosya bulundu ve yükleniyor: " . $contentFile; 
            require_once $fullPath;
        } else {
            // DEBUG: Dosya bulunamazsa genel ayarları göster ve logla
            error_log("Ayarlar dosyası bulunamadı: " . $contentFile);
            require_once __DIR__ . '/settings_general.php';
        }
    ?>
</div>

<?php
require_once __DIR__ . '/_footer.php';