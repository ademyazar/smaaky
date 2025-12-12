<?php
// admin/settings_general_action.php
require_once __DIR__ . '/_functions.php';
requireAdmin();

$days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

$hours = [];

foreach ($days as $day) {
    $open  = $_POST[$day . '_open']  ?? '';
    $close = $_POST[$day . '_close'] ?? '';
    $closed = isset($_POST[$day . '_closed']) ? true : false;

    // Boş bırakılırsa default vermek istersen:
    if ($open === '')  $open  = '11:00';
    if ($close === '') $close = '23:00';

    $hours[$day] = [
        'open'   => $open,
        'close'  => $close,
        'closed' => $closed,
    ];
}

// JSON olarak kaydet
setSetting('opening_hours', json_encode($hours));

// Force close
$force = isset($_POST['store_force_closed']) ? '1' : '0';
setSetting('store_force_closed', $force);

// Geri dön
header("Location: settings_general.php?saved=1");
exit;