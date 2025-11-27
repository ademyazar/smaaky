<?php
// admin/settings_general.php
require_once __DIR__ . '/_functions.php';
requireAdmin();

// Açılış saatlerini ve force close durumunu yükle
$hours = getOpeningHours();
$forceClosed = isStoreForceClosed();

// Varsayılan saatler (eğer hiç ayarlanmamışsa)
$defaultDay = [
    'open'   => '11:00',
    'close'  => '23:00',
    'closed' => false
];

$days = [
    'mon' => 'Maandag',
    'tue' => 'Dinsdag',
    'wed' => 'Woensdag',
    'thu' => 'Donderdag',
    'fri' => 'Vrijdag',
    'sat' => 'Zaterdag',
    'sun' => 'Zondag',
];

require_once __DIR__ . '/_header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Instellingen – Openingstijden</h1>
        <p class="page-subtitle">
            Beheer openingstijden en online bestellingen van Smaaky.
        </p>
    </div>
</div>

<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">
        Instellingen zijn succesvol opgeslagen.
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <!-- Openingstijden -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Openingstijden per dag</h2>
            <p class="card-subtitle">
                Stel per dag in wanneer de keuken open is voor bestellingen.
            </p>
        </div>
        <div class="card-body">
            <form action="settings_general_action.php" method="post">

                <div class="opening-hours-table">
                    <?php foreach ($days as $key => $label): 
                        $d = $hours[$key] ?? $defaultDay;
                        $open  = $d['open']  ?? $defaultDay['open'];
                        $close = $d['close'] ?? $defaultDay['close'];
                        $closed = !empty($d['closed']);
                    ?>
                        <div class="opening-row">
                            <div class="opening-day">
                                <?= e($label) ?>
                            </div>
                            <div class="opening-inputs">
                                <label class="opening-label">
                                    Open
                                    <input type="time"
                                           name="<?= $key ?>_open"
                                           value="<?= e($open) ?>">
                                </label>
                                <label class="opening-label">
                                    Dicht
                                    <input type="time"
                                           name="<?= $key ?>_close"
                                           value="<?= e($close) ?>">
                                </label>
                            </div>
                            <div class="opening-closed">
                                <label class="checkbox-label">
                                    <input type="checkbox"
                                           name="<?= $key ?>_closed"
                                           value="1"
                                           <?= $closed ? 'checked' : '' ?>>
                                    <span>Gesloten</span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Force close -->
                <div class="card-divider"></div>

                <div class="force-close-block">
                    <label class="checkbox-label">
                        <input type="checkbox"
                               name="store_force_closed"
                               value="1"
                               <?= $forceClosed ? 'checked' : '' ?>>
                        <span><strong>Online bestellingen tijdelijk pauzeren</strong></span>
                    </label>
                    <p class="help-text">
                        Wanneer dit is aangevinkt, wordt de zaak voor online bestellen
                        als <strong>gesloten</strong> beschouwd, ongeacht de openingstijden.
                        Handig bij extreme drukte of vakantie.
                    </p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Instellingen opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Info card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Hoe werkt dit?</h2>
        </div>
        <div class="card-body">
            <ul class="info-list">
                <li>
                    <strong>Openingstijden</strong><br>
                    De frontend (bestelpagina) gebruikt <code>isStoreOpen()</code>
                    om te bepalen of Smaaky “open” of “gesloten” is.
                </li>
                <li>
                    <strong>Gesloten dag</strong><br>
                    Vink <em>Gesloten</em> aan voor dagen waarop je nooit open bent
                    (bijv. maandag).
                </li>
                <li>
                    <strong>Tijdelijk pauzeren</strong><br>
                    Met <em>Online bestellingen tijdelijk pauzeren</em> kun je
                    direct alle bestellingen blokkeren, ongeacht de tijden.
                </li>
                <li>
                    <strong>JSON in database</strong><br>
                    Alle openingstijden worden als JSON opgeslagen in de
                    <code>settings</code>-tabel onder de key
                    <code>opening_hours</code>.
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/_footer.php';