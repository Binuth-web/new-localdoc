<?php
require 'db_connect.php';
header('Content-Type: application/json');

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
$city = isset($_GET['city']) ? filter_var($_GET['city'], FILTER_SANITIZE_STRING) : null;

if ($lat && $lng) {
    // Haversine formula to sort by distance (in km)
    $sql = "SELECT center_id, name, address, city, latitude, longitude, contact_number,
            ( 6371 * acos( cos( radians(:lat) ) * cos( radians( latitude ) ) 
            * cos( radians( longitude ) - radians(:lng) ) + sin( radians(:lat) ) 
            * sin( radians( latitude ) ) ) ) AS distance 
            FROM medical_centers 
            ORDER BY distance ASC 
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['lat' => $lat, 'lng' => $lng]);
} else if ($city) {
    // Fallback search by city
    $sql = "SELECT *, NULL as distance FROM medical_centers WHERE city LIKE :city LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['city' => '%' . $city . '%']);
} else {
    // Default fetch
    $sql = "SELECT *, NULL as distance FROM medical_centers LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}

$centers = $stmt->fetchAll();
echo json_encode(["status" => "success", "data" => $centers]);
?>
