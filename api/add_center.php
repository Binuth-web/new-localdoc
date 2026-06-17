<?php
session_name('medconnect_staff');
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

// In a real system, we'd check if the user is an admin here.
// session_start();
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
//     echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
//     exit;
// }

$name = trim($_POST['name'] ?? '');
$type = trim($_POST['type'] ?? '');
$area = trim($_POST['area'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if (!$name || !$address) {
    echo json_encode(['status' => 'error', 'message' => 'Name and address are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO medical_centers (name, type, area, address, phone, available) 
         VALUES (?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute([$name, $type, $area, $address, $phone]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Medical Center added successfully!'
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add Medical Center. Please try again.']);
}
?>
