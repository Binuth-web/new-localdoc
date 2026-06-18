<?php
session_name('medconnect_staff');
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
    
    if ($action === 'complete' || $action === 'block') {
        // Find unserved tokens
        $tStmt = $pdo->prepare("SELECT id, patient_id, token_number FROM opd_tokens WHERE session_id = ? AND status NOT IN ('served', 'called', 'cancelled') AND patient_id IS NOT NULL");
        $tStmt->execute([$sessionId]);
        $unservedTokens = $tStmt->fetchAll();
        
        if (count($unservedTokens) > 0) {
            // Cancel them
            $pdo->prepare("UPDATE opd_tokens SET status = 'cancelled' WHERE session_id = ? AND status NOT IN ('served', 'called', 'cancelled')")->execute([$sessionId]);
            
            // Notify patients
            $notifTitle = $action === 'complete' ? "Session Completed" : "Session Cancelled";
            $actionWord = $action === 'complete' ? "completed" : "cancelled";
            
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, token_id, type) VALUES (?, ?, ?, 'warning')");
            foreach ($unservedTokens as $t) {
                $msg = "The session has been {$actionWord} by the medical staff. Your token (#{$t['token_number']}) is no longer valid.";
                $notifStmt->execute([$t['patient_id'], $msg, $t['id']]);
            }
        }
    }
    
    $label = ucfirst($newStatus);
    echo json_encode(['status' => 'success', 'message' => "Session marked as {$label}."]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update session status.']);
}
?>
