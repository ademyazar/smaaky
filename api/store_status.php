<?php
require_once "../admin/_functions.php";

$open = isStoreOpen();

$response = [
    "open"            => $open,
    "force_closed"    => isStoreForceClosed(),
    "delivery_paused" => isDeliveryPaused(),
    "pickup_paused"   => isPickupPaused(),
    "opens_in"        => null
];

if (!$open) {
    // Açılışa geri sayım
    $hours = getOpeningHours();
    $day = strtolower(date("D"));
    $map = ["mon"=>"mon","tue"=>"tue","wed"=>"wed","thu"=>"thu","fri"=>"fri","sat"=>"sat","sun"=>"sun"];
    $d = $map[$day] ?? null;

    if ($d && isset($hours[$d]) && empty($hours[$d]["closed"])) {
        $openTime = strtotime($hours[$d]["open"]);
        $now = time();

        if ($openTime > $now) {
            $diff = $openTime - $now;
            $hoursLeft = floor($diff / 3600);
            $minsLeft = floor(($diff % 3600) / 60);
            $response["opens_in"] = "{$hoursLeft} uur {$minsLeft} min";
        }
    }
}

header("Content-Type: application/json");
echo json_encode($response);