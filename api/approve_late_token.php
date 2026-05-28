<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['staff', 'medical_staff', 'admin', 'doctor'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$tokenId = (int)($_POST['token_id'] ?? 0);
if (!$tokenId) {
    echo json_encode(['status' => 'error', 'message' => 'token_id required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM opd_tokens WHERE id = ? AND status = 'late_request'");
$stmt->execute([$tokenId]);
$token = $stmt->fetch();

if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'Token not found or not in late_request state.']);
    exit;
}

// Approve: set to waiting + mark present
$pdo->prepare("UPDATE opd_tokens SET status = 'waiting', attendance_marked = 1 WHERE id = ?")->execute([$tokenId]);

// Notify the patient their late request was approved
$msg = "✅ Your late token request for {$token['token_number']} has been APPROVED. Please proceed to the clinic. You will be called after the current queue.";
$pdo->prepare("INSERT INTO notifications (user_id, token_id, message, type) VALUES (?, ?, ?, 'info')")
    ->execute([$token['patient_id'], $tokenId, $msg]);

echo json_encode(['status' => 'success', 'message' => 'Late token approved. Patient has been notified.']);
?>
