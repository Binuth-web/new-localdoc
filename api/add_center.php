<?php
session_name('medconnect_staff');
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$name    = trim($_POST['name']    ?? '');
$type    = trim($_POST['type']    ?? '');
$area    = trim($_POST['area']    ?? '');
$address = trim($_POST['address'] ?? '');
$phone   = trim($_POST['phone']   ?? '');
$lat     = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng     = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;

if (!$name || !$address) {
    echo json_encode(['status' => 'error', 'message' => 'Name and address are required.']);
    exit;
}

if ($lat === null || $lng === null) {
    echo json_encode(['status' => 'error', 'message' => 'Please set a map location for this center.']);
    exit;
}

// Auto-migrate: add lat/lng columns if they don't exist yet
try {
    $existingCols = $pdo->query("SHOW COLUMNS FROM medical_centers")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('lat', $existingCols)) {
        $pdo->exec("ALTER TABLE medical_centers ADD COLUMN lat DECIMAL(10,7) DEFAULT NULL");
    }
    if (!in_array('lng', $existingCols)) {
        $pdo->exec("ALTER TABLE medical_centers ADD COLUMN lng DECIMAL(10,7) DEFAULT NULL");
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB migration failed: ' . $e->getMessage()]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO medical_centers (name, type, area, address, phone, lat, lng, available, services)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, '[]')"
    );
    $stmt->execute([$name, $type, $area, $address, $phone, $lat, $lng]);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Medical Center added successfully!'
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
}
