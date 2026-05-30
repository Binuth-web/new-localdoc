<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    exit;
}

// Fetch pending staff (role='staff', is_active=0)
try {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.full_name, u.email, u.phone, m.name as center_name 
         FROM users u 
         LEFT JOIN medical_centers m ON u.center_id = m.id 
         WHERE u.role = ? AND u.is_active = 0'
    );
    $stmt->execute(['staff']);
    $pendingStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $pendingStaff
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch pending staff.']);
}
?>
