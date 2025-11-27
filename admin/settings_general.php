<?php
$pageTitle  = "Instellingen – Openingstijden";
$activeMenu = "settings";

require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_functions.php';

// --------------------------------------------------
// POST: instellingen opslaan
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Opening hours
    $hours = [
        'mon' => [
            'open'   => $_POST['mon_open']  ?? '00:00',
            'close'  => $_POST['mon_close'] ?? '23:59',
            'closed' => isset($_POST['mon_closed']) ? 1 : 0,
        ],
        'tue' => [
            'open'   => $_POST['tue_open']  ?? '00:00',
            'close'  => $_POST['tue_close'] ?? '23:59',
            'closed' => isset($_POST['tue_closed']) ? 1 : 0,
        ],
        'wed' => [
            'open'   => $_POST['wed_open']  ?? '00:00',
            'close'  => $_POST['wed_close'] ?? '23:59',
            'closed' => isset($_POST['wed_closed']) ? 1 : 0,
        ],
        'thu' => [
            'open'   => $_POST['thu_open']  ?? '00:00',
            'close'  => $_POST['thu_close'] ?? '23:59',
            'closed' => isset($_POST['thu_closed']) ? 1 : 0,
        ],
        'fri' => [
            'open'   => $_POST['fri_open']  ?? '00:00',
            'close'  => $_POST['fri_close'] ?? '23:59',
            'closed' => isset($_POST['fri_closed']) ? 1 : 0,
        ],
        'sat' => [
            'open'   => $_POST['sat_open']  ?? '00:00',
            'close'  => $_POST['sat_close'] ?? '23:59',
            'closed' => isset($_POST['sat_closed']) ? 1 : 0,
        ],
        'sun' => [
            'open'   => $_POST['sun_open']  ?? '00:00',
            'close'  => $_POST['sun_close'] ?? '23:59',
            'closed' => isset($_POST['sun_closed']) ? 1 : 0,
        ],
    ];

    setSetting('opening_hours', json_encode($hours));

    // Global force closed
    $forceClosed = isset($_POST['store_force_closed']) ? "1" : "0";
    setSetting('store_force_closed', $forceClosed);

    // NEW: delivery/pickup paused
    $deliveryPaused = isset($_POST['delivery_paused']) ? "1" : "0";
    $pickupPaused   = isset($_POST['pickup_paused']) ? "1" : "0";

    setSetting('delivery_paused', $deliveryPaused);
    setSetting('pickup_paused', $pickupPaused);

    $saved = true;
}

// --------------------------------------------------
// Uitlezen huidige waarden
// --------------------------------------------------
$hours = getOpeningHours();
$storeForceClosed = isStoreForceClosed();
$deliveryPaused   = isDeliveryPaused();
$pickupPaused     = isPickupPaused();

function h($k, $hours, $day, $field, $default) {
    return htmlspecialchars($hours[$day][$field] ?? $default, ENT_QUOTES, 'UTF-8');
}

?>
<div class="page-header">
    <div>
        <div class="page-kicker">Instellingen – Openingstijden</div>
        <h1 class="page-title">Smaaky Rotterdam – beheer de online bestellingen.</h1>
    </div>
</div>

<div class="page-content">
    <?php if (!empty($saved)): ?>
        <div class="alert alert-success">
            Instellingen zijn opgeslagen.
        </div>
    <?php endif; ?>

    <form action="" method="post" class="settings-form">

        <section class="settings-card">
            <h2 class="settings-title">Openingstijden per dag</h2>
            <p class="settings-subtitle">
                Stel per dag in wanneer de keuken open is voor bestellingen.
            </p>

            <div class="opening-grid">

                <?php
                $days = [
                    'mon' => 'Maandag',
                    'tue' => 'Dinsdag',
                    'wed' => 'Woensdag',
                    'thu' => 'Donderdag',
                    'fri' => 'Vrijdag',
                    'sat' => 'Zaterdag',
                    'sun' => 'Zondag',
                ];

                foreach ($days as $key => $label): ?>
                    <div class="opening-row">
                        <div class="opening-day"><?= $label ?></div>

                        <div class="opening-times">
                            <label class="opening-time-label">
                                <span>Open</span>
                                <input type="time"
                                       name="<?= $key ?>_open"
                                       value="<?= h('h', $hours, $key, 'open', '11:00') ?>">
                            </label>

                            <label class="opening-time-label">
                                <span>Dicht</span>
                                <input type="time"
                                       name="<?= $key ?>_close"
                                       value="<?= h('h', $hours, $key, 'close', '23:00') ?>">
                            </label>

                            <label class="switch-label">
                                <input type="checkbox"
                                       name="<?= $key ?>_closed"
                                        <?= !empty($hours[$key]['closed']) ? 'checked' : '' ?>>
                                <span class="switch"></span>
                                <span class="switch-text">Gesloten</span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </section>

        <section class="settings-card">
            <h2 class="settings-title">Online bestelstatus</h2>

            <div class="settings-row">
                <label class="switch-label">
                    <input type="checkbox" name="store_force_closed"
                        <?= $storeForceClosed ? 'checked' : '' ?>>
                    <span class="switch"></span>
                    <span class="switch-text">
                        Alle online bestellingen tijdelijk pauzeren
                    </span>
                </label>
                <p class="settings-help">
                    Wanneer dit is aangevinkt, wordt de zaak voor online bestellen als
                    <strong>gesloten</strong> beschouwd, ongeacht de openingstijden.
                    Handig bij extreme drukte of vakantie.
                </p>
            </div>

            <div class="settings-row">
                <label class="switch-label">
                    <input type="checkbox" name="delivery_paused"
                        <?= $deliveryPaused ? 'checked' : '' ?>>
                    <span class="switch"></span>
                    <span class="switch-text">
                        Alleen bezorging pauzeren
                    </span>
                </label>
                <p class="settings-help">
                    Bezorging tijdelijk sluiten, maar <strong>afhalen open</strong> laten.
                    De klant ziet in de bestelpagina dat bezorging niet beschikbaar is.
                </p>
            </div>

            <div class="settings-row">
                <label class="switch-label">
                    <input type="checkbox" name="pickup_paused"
                        <?= $pickupPaused ? 'checked' : '' ?>>
                    <span class="switch"></span>
                    <span class="switch-text">
                        Alleen afhalen pauzeren
                    </span>
                </label>
                <p class="settings-help">
                    Afhaling tijdelijk sluiten, maar <strong>bezorging open</strong> laten.
                </p>
            </div>
        </section>

        <button type="submit" class="btn-primary">
            Instellingen opslaan
        </button>

        <section class="settings-card settings-help-block">
            <h3 class="settings-small-title">Hoe werkt dit?</h3>
            <p class="settings-help">
                De frontend (bestelpagina) gebruikt <code>isStoreOpen()</code>,
                <code>isDeliveryPaused()</code> en <code>isPickupPaused()</code> om te
                bepalen of Smaaky "open", "alleen afhalen", "alleen bezorgen" of "gesloten" is.
            </p>
        </section>
    </form>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>