<a href="admin/index.php?page=instellingen" class="<?php echo ($activeMenu === 'instellingen' ? 'active' : ''); ?>">Instellingen</a>

<!-- Ayarların Alt Menüsü (Genellikle settings.php'nin içinde olur, ancak doğrudan buraya da eklenebilir) -->
<?php if ($activeMenu === 'instellingen'): ?>
    <div class="submenu">
        <a href="admin/index.php?page=settings&subpage=general" class="submenu-item">Genel Ayarlar</a>
        <a href="admin/index.php?page=settings&subpage=order" class="submenu-item">Sipariş Ayarları</a>
        <a href="admin/index.php?page=settings&subpage=payment" class="submenu-item">Ödeme (Mollie) Ayarları</a> <!-- YENİ BAĞLANTI -->
    </div>
<?php endif; ?>