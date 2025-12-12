<?php
// ...
if ($activeMenu === 'settings') {
    // subpage değişkenini URL'den alın
    $subpage = $_GET['subpage'] ?? 'general';

    if ($subpage === 'general') {
        require_once 'admin/settings_general.php';
    } elseif ($subpage === 'payment') {
        require_once 'admin/settings_payment.php'; // <<< YENİ SAYFA BURADA ÇAĞRILACAK
    } elseif ($subpage === 'order') {
        require_once 'admin/settings_order.php';
    } 
    // ... Diğer ayar alt sayfaları
}
// ...
?>