<?php
require 'db_connect.php';
require 'helpers.php';
header('Content-Type: application/json');

$lat = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float) $_GET['lng'] : null;
$city = isset($_GET['city']) ? trim((string) $_GET['city']) : null;
if ($city === '') {
    $city = null;
}

$select = 'id AS center_id, name, address, area AS city, lat AS latitude, lng AS longitude,
           phone AS contact_number, hours, available';

if ($lat && $lng) {
    $sql = "SELECT $select,
            (6371 * acos(cos(radians(:lat)) * cos(radians(lat))
            * cos(radians(lng) - radians(:lng)) + sin(radians(:lat)) * sin(radians(lat)))) AS distance
            FROM medical_centers
            ORDER BY distance ASC
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['lat' => $lat, 'lng' => $lng]);
} elseif ($city) {
    $sql = "SELECT $select, NULL AS distance FROM medical_centers
            WHERE CONCAT(area, ' ', address, ' ', name) LIKE :city
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['city' => '%' . $city . '%']);
} else {
    $sql = "SELECT $select, NULL AS distance FROM medical_centers LIMIT 20";
    $stmt = $pdo->query($sql);
}

$centers = $stmt->fetchAll();
foreach ($centers as &$center) {
    [$open, $close] = parseHoursRange($center['hours'] ?? null);
    $center['open_time'] = $open;
    $center['close_time'] = $close;
    $center['available'] = (bool) ($center['available'] ?? true);
}
unset($center);

echo json_encode(['status' => 'success', 'data' => $centers]);
