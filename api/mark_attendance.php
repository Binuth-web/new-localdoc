<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

// Accept both 'staff' (DB value) and 'medical_staff' (legacy session value)
$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['staff', 'medical_staff', 'admin', 'doctor'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$tokenId = (int)($_POST['token_id'] ?? 0);
$action  = trim($_POST['action'] ?? ''); // 'present' or 'absent'

if (!$tokenId || !in_array($action, ['present', 'absent', 'complete'])) {
    echo json_encode(['status' => 'error', 'message' => 'token_id and valid action required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT ot.*, u.full_name AS patient_name FROM opd_tokens ot JOIN users u ON ot.patient_id = u.id WHERE ot.id = ?");
$stmt->execute([$tokenId]);
$token = $stmt->fetch();

if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'Token not found.']);
    exit;
}

if ($action === 'present') {
    $pdo->prepare("UPDATE opd_tokens SET attendance_marked = 1 WHERE id = ?")->execute([$tokenId]);
    echo json_encode(['status' => 'success', 'message' => 'Marked as present.']);
} elseif ($action === 'complete') {
    $pdo->prepare("UPDATE opd_tokens SET status = 'served', attendance_marked = 1 WHERE id = ?")->execute([$tokenId]);
    echo json_encode(['status' => 'success', 'message' => 'Marked as completed.']);
} else {
    // Mark as no-show and notify the patient
    $pdo->prepare("UPDATE opd_tokens SET status = 'no-show', attendance_marked = 0 WHERE id = ?")->execute([$tokenId]);

    $message = "⚠️ You were marked ABSENT for token {$token['token_number']}. If you are on your way, please click 'Request Late Token' below before the session ends.";
    $pdo->prepare("INSERT INTO notifications (user_id, token_id, message, type, action_label, action_data) VALUES (?, ?, ?, 'action', 'Request Late Token', ?)")
        ->execute([
            $token['patient_id'],
            $tokenId,
            $message,
            json_encode(['token_id' => $tokenId])
        ]);

    echo json_encode(['status' => 'success', 'message' => 'Marked as absent. Patient notified.']);
}
?>
