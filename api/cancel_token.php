<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$tokenId = filter_input(INPUT_POST, 'token_id', FILTER_VALIDATE_INT);
if (!$tokenId) {
    echo json_encode(['status' => 'error', 'message' => 'token_id required.']);
    exit;
}

// Verify this token belongs to this patient and is cancellable
$stmt = $pdo->prepare("SELECT ot.*, os.is_active FROM opd_tokens ot JOIN opd_sessions os ON ot.session_id = os.id WHERE ot.id = ? AND ot.patient_id = ?");
$stmt->execute([$tokenId, $_SESSION['user_id']]);
$token = $stmt->fetch();

if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'Token not found.']);
    exit;
}

if (!in_array($token['status'], ['waiting', 'pending'])) {
    echo json_encode(['status' => 'error', 'message' => 'This token cannot be cancelled.']);
    exit;
}

$pdo->beginTransaction();
try {
    // Cancel the token
    $pdo->prepare("UPDATE opd_tokens SET status = 'cancelled' WHERE id = ?")->execute([$tokenId]);
    // Expand max_tokens by 1 so a new slot opens up
    $pdo->prepare("UPDATE opd_sessions SET max_tokens = max_tokens + 1 WHERE id = ?")->execute([$token['session_id']]);
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Your booking has been cancelled. A new slot has been opened for others.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Cancellation failed. Please try again.']);
}
?>
