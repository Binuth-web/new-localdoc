<?php
require 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin access required.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch all sessions
    $stmt = $pdo->query("
        SELECT os.*, mc.name AS center_name 
        FROM opd_sessions os 
        JOIN medical_centers mc ON os.clinic_id = mc.id
        ORDER BY os.session_date DESC, os.start_time DESC
    ");
    $sessions = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $sessions]);
    exit;
}
?>
