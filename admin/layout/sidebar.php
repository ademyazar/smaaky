<?php
// admin/layout/sidebar.php
// Bu dosya, admin_dashboard.php (root'ta) tarafından çağrılır.

// Mevcut aktif menü ve alt sayfa değerlerini al (genellikle URL'den veya üst dosyalardan gelir)
// Buradaki page/subpage değişkenleri, URL'nize göre değişebilir.
$currentPage = $_GET['page'] ?? 'dashboard';
$subpage = $_GET['subpage'] ?? 'general';

// Bu yapıyı sizin gönderdiğiniz eski menü yapınıza göre düzelttim.
// Normalde bu dosya içeriği daha geniştir, ancak sadece ayar menülerini düzeltiyoruz.
?>

<!-- Ana menü öğeleri... -->
<!-- Örneğin: Dashboard -->
<a href="admin_dashboard.php" class="menu-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
<!-- Örneğin: Siparişler -->
<a href="admin/orders.php" class="menu-item <?= $currentPage === 'orders' ? 'active' : '' ?>">Bestellingen</a>


<!-- Instellingen (Ayarlar) Menüsü -->
<a href="admin/settings.php?subpage=general" class="menu-item <?= $currentPage === 'settings' ? 'active' : '' ?>">Instellingen</a>

<?php 
// Sadece 'settings' sayfası aktifse alt menüyü göster
if ($currentPage === 'settings'): 
?>
    <div class="submenu">
        
        <!-- Genel Ayarlar -->
        <a href="admin/settings.php?subpage=general" class="submenu-item <?= $subpage === 'general' ? 'active' : '' ?>">
            Algemeen
        </a>
        
        <!-- Sipariş Ayarları -->
        <a href="admin/settings.php?subpage=order" class="submenu-item <?= $subpage === 'order' ? 'active' : '' ?>">
            Bestellingen & Levering
        </a>
        
        <!-- ÖDEME AYARLARI (MOLLIE) -->
        <a href="admin/settings.php?subpage=payment" class="submenu-item <?= $subpage === 'payment' ? 'active' : '' ?>">
            Betaling (Mollie)
        </a>
        
        <!-- E-posta Ayarları -->
        <a href="admin/settings.php?subpage=email" class="submenu-item <?= $subpage === 'email' ? 'active' : '' ?>">
            E-mail
        </a>
        
        <!-- Diğer ayarlar... -->
        <a href="admin/settings.php?subpage=password" class="submenu-item <?= $subpage === 'password' ? 'active' : '' ?>">
            Wachtwoord
        </a>
        <a href="admin/settings.php?subpage=users" class="submenu-item <?= $subpage === 'users' ? 'active' : '' ?>">
            Gebruikers
        </a>
        <a href="admin/settings.php?subpage=roles" class="submenu-item <?= $subpage === 'roles' ? 'active' : '' ?>">
            Rollen
        </a>
    </div>
<?php endif; ?>

<!-- Çıkış (Logout) -->
<a href="admin/logout.php" class="menu-item">Uitloggen</a>

<!-- ... Kalan menü öğeleri ... -->