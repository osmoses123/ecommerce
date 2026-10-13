<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

$countryId = (int) ($_GET['country_id'] ?? 0);
$stateId = (int) ($_GET['state_id'] ?? 0);

if ($stateId > 0) {
    $stmt = db()->prepare('SELECT id, name FROM cities WHERE state_id = ? ORDER BY name ASC');
    $stmt->execute([$stateId]);
    echo json_encode(['cities' => $stmt->fetchAll()]);
    exit;
}

if ($countryId > 0) {
    $stmt = db()->prepare('SELECT id, name, shipping_fee FROM states WHERE country_id = ? ORDER BY name ASC');
    $stmt->execute([$countryId]);
    echo json_encode(['states' => $stmt->fetchAll()]);
    exit;
}

echo json_encode(['states' => [], 'cities' => []]);
