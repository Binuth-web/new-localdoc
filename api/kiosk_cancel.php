<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

$sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
$tokenNumber = trim($_POST['token_number'] ?? '');

if (!$sessionId || !$tokenNumber) {
    echo json_encode(['status' => 'error', 'message' => 'Token number required.']);
    exit;
}

// Make sure token number has OPD- prefix
if (!str_starts_with(strtoupper($tokenNumber), 'OPD-')) {
    $tokenNumber = 'OPD-' . str_pad($tokenNumber, 3, '0', STR_PAD_LEFT);
}

$pdo->beginTransaction();
try {
    // Find the token
    $stmt = $pdo->prepare("SELECT * FROM opd_tokens WHERE session_id = ? AND token_number = ? AND status = 'waiting'");
    $stmt->execute([$sessionId, $tokenNumber]);
    $token = $stmt->fetch();

    if (!$token) {
        echo json_encode(['status' => 'error', 'message' => 'Active token not found. It may be already cancelled or completed.']);
        $pdo->rollBack();
        exit;
    }

    // Cancel token
    $stmt = $pdo->prepare("UPDATE opd_tokens SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$token['id']]);

    // Expand max_tokens by 1 automatically
    $stmt = $pdo->prepare("UPDATE opd_sessions SET max_tokens = max_tokens + 1 WHERE id = ?");
    $stmt->execute([$sessionId]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Token {$tokenNumber} has been cancelled successfully. A new slot has been opened."
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel token.']);
}
?>
