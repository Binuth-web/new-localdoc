<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['staff', 'medical_staff', 'admin', 'doctor'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$sessionId = (int)($_POST['session_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if (!$sessionId || !in_array($action, ['complete', 'block', 'resume'])) {
    echo json_encode(['status' => 'error', 'message' => 'Valid session_id and action required.']);
    exit;
}

$newStatus = 'active'; // resume maps to active
if ($action === 'complete') $newStatus = 'completed';
if ($action === 'block')    $newStatus = 'blocked';

try {
    $stmt = $pdo->prepare("UPDATE opd_sessions SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $sessionId]);
    $label = ucfirst($newStatus);
    echo json_encode(['status' => 'success', 'message' => "Session marked as {$label}."]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update session status.']);
}
?>
