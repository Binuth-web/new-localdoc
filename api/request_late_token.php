<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$tokenId = (int)($_POST['token_id'] ?? 0);
if (!$tokenId) {
    echo json_encode(['status' => 'error', 'message' => 'token_id required.']);
    exit;
}

// Verify token belongs to this patient and is in no-show status
$stmt = $pdo->prepare("
    SELECT ot.*, os.status AS session_status, os.end_time, os.session_date, os.clinic_id,
           mc.name AS center_name
    FROM opd_tokens ot 
    JOIN opd_sessions os ON ot.session_id = os.id
    JOIN medical_centers mc ON os.clinic_id = mc.id
    WHERE ot.id = ? AND ot.patient_id = ?
");
$stmt->execute([$tokenId, $_SESSION['user_id']]);
$token = $stmt->fetch();

if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'Token not found.']);
    exit;
}

if (!in_array($token['status'], ['waiting', 'no-show'])) {
    echo json_encode(['status' => 'error', 'message' => 'Late token can only be requested when pending or marked absent.']);
    exit;
}

if ($token['session_status'] !== 'active') {
    echo json_encode(['status' => 'error', 'message' => 'This session is no longer active.']);
    exit;
}

// Change status to late_request and track it
$pdo->prepare("UPDATE opd_tokens SET status = 'late_request', was_late_request = 1 WHERE id = ?")->execute([$tokenId]);

// Notify all staff of this center — DB role enum value is 'staff'
$staffStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'staff' AND center_id = ? AND is_active = 1");
$staffStmt->execute([$token['clinic_id']]);
$staffMembers = $staffStmt->fetchAll();

$patientName = $_SESSION['name'] ?? 'Patient';
$statusDesc = ($token['status'] === 'no-show') ? 'was marked absent but is' : 'is currently pending and';
$message = "🕐 Late Token Request: {$patientName} (token {$token['token_number']}) {$statusDesc} requesting a late token for {$token['center_name']}. Please check with the doctor and approve in the session view.";

foreach ($staffMembers as $staff) {
    $pdo->prepare("INSERT INTO notifications (user_id, token_id, message, type) VALUES (?, ?, ?, 'action')")
        ->execute([$staff['id'], $tokenId, $message]);
}

echo json_encode(['status' => 'success', 'message' => 'Your late token request has been sent to the medical staff. Please wait for their response.']);
?>
