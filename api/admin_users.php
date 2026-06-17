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
    $role = $_GET['role'] ?? 'all';
    
    $query = "SELECT u.id, u.full_name, u.email, u.id_number, u.date_of_birth, u.phone, u.role, u.is_active, c.name as center_name 
              FROM users u 
              LEFT JOIN medical_centers c ON u.center_id = c.id";
    
    $params = [];
    if ($role !== 'all') {
        $query .= " WHERE u.role = ?";
        $params[] = $role;
    }
    
    $query .= " ORDER BY u.full_name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    echo json_encode(['status' => 'success', 'data' => $users]);
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'User ID is required.']);
        exit;
    }

    if ($action === 'update') {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        $pdo->prepare("UPDATE users SET full_name=?, phone=? WHERE id=?")
            ->execute([$name, $phone, $id]);
            
        echo json_encode(['status' => 'success', 'message' => 'User updated successfully.']);
        exit;
    }

    if ($action === 'toggle_active') {
        $active = (int)($_POST['is_active'] ?? 1);
        $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")
            ->execute([$active, $id]);
            
        $statusStr = $active ? 'activated' : 'deactivated';
        echo json_encode(['status' => 'success', 'message' => "User account $statusStr successfully."]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}
?>
