<?php
session_name('medconnect_staff');
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

$pdo->beginTransaction();
try {
    // Reject/Cancel the late token
    $pdo->prepare("UPDATE opd_tokens SET status = 'cancelled' WHERE id = ?")->execute([$tokenId]);
    // Expand max_tokens by 1 so a new slot opens up
    $pdo->prepare("UPDATE opd_sessions SET max_tokens = max_tokens + 1 WHERE id = ?")->execute([$token['session_id']]);
    
    // Notify the patient their late request was rejected and token cancelled
    $msg = "❌ Your late token request for {$token['token_number']} has been REJECTED. Your token is now cancelled. Please book a new token if needed.";
    $pdo->prepare("INSERT INTO notifications (user_id, token_id, message, type) VALUES (?, ?, ?, 'warning')")
        ->execute([$token['patient_id'], $tokenId, $msg]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Late token cancelled. Patient has been notified.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Failed to reject late token.']);
}
?>
