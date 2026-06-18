<?php
session_name('medconnect_staff');
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? null;

if (!$sessionId) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Verify session exists
    $stmt = $pdo->prepare("SELECT id FROM opd_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    if (!$stmt->fetch()) {
        throw new Exception("Session not found");
    }

    // Delete notifications tied to tokens from this session.
    // We must do this before the session/tokens are deleted because the tokens might cascade delete.
    $stmt = $pdo->prepare("
        DELETE FROM notifications 
        WHERE token_id IN (
            SELECT id FROM opd_tokens WHERE session_id = ?
        )
    ");
    $stmt->execute([$sessionId]);

    // Delete the session. This will cascade delete the related opd_tokens due to foreign key constraint.
    $stmt = $pdo->prepare("DELETE FROM opd_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Session successfully deleted']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
