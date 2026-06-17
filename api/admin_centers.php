<?php
session_name('medconnect_staff');
require 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin access required.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch all centers
    $stmt = $pdo->query("SELECT * FROM medical_centers ORDER BY name ASC");
    $centers = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $centers]);
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Center ID is required.']);
        exit;
    }

    if ($action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $area = trim($_POST['area'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        $pdo->prepare("UPDATE medical_centers SET name=?, type=?, area=?, phone=?, address=? WHERE id=?")
            ->execute([$name, $type, $area, $phone, $address, $id]);
            
        echo json_encode(['status' => 'success', 'message' => 'Center updated successfully.']);
        exit;
    }

    if ($action === 'toggle_booking') {
        $enabled = (int)($_POST['enabled'] ?? 1);
        $pdo->prepare("UPDATE medical_centers SET online_booking_enabled=? WHERE id=?")
            ->execute([$enabled, $id]);
        
        $statusStr = $enabled ? 'enabled' : 'disabled';
        echo json_encode(['status' => 'success', 'message' => "Online booking $statusStr for this center."]);
        exit;
    }

    if ($action === 'toggle_active') {
        $active = (int)($_POST['available'] ?? 1); // using 'available' column in db
        $pdo->prepare("UPDATE medical_centers SET available=? WHERE id=?")
            ->execute([$active, $id]);
            
        $statusStr = $active ? 'activated' : 'deactivated';
        echo json_encode(['status' => 'success', 'message' => "Center $statusStr successfully."]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}
?>
