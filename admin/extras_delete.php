<?php
require_once __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $pdo->prepare("DELETE FROM extras WHERE id = ?")->execute([$id]);
}

header("Location: extras.php");
exit;
