<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['staff', 'medical_staff', 'admin', 'doctor'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$sessionId   = (int)($_POST['session_id'] ?? 0);
$doctorName  = trim($_POST['doctor_name'] ?? '');
$sessionDate = trim($_POST['session_date'] ?? '');
$startTime   = trim($_POST['start_time'] ?? '');
$endTime     = trim($_POST['end_time'] ?? '');
$maxTokens   = (int)($_POST['max_tokens'] ?? 0);
$clinicId    = (int)($_POST['clinic_id'] ?? 0);

if (!$sessionId || !$doctorName || !$sessionDate || !$startTime || !$endTime || !$maxTokens || !$clinicId) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

// Make sure max_tokens is not less than already booked count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM opd_tokens WHERE session_id = ? AND status NOT IN ('cancelled','no-show')");
$stmt->execute([$sessionId]);
$bookedCount = (int)$stmt->fetchColumn();

if ($maxTokens < $bookedCount) {
    echo json_encode(['status' => 'error', 'message' => "Cannot set max tokens to $maxTokens — $bookedCount tokens are already booked."]);
    exit;
}

try {
    $pdo->prepare("UPDATE opd_sessions SET clinic_id=?, doctor_name=?, session_date=?, start_time=?, end_time=?, max_tokens=? WHERE id=?")
        ->execute([$clinicId, $doctorName, $sessionDate, $startTime, $endTime, $maxTokens, $sessionId]);

    echo json_encode(['status' => 'success', 'message' => 'Session updated successfully.']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update session.']);
}
?>
