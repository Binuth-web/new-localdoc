<?php
session_name('medconnect_staff');
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

file_put_contents(__DIR__ . '/../debug_all.log', date('Y-m-d H:i:s') . " - call_next_token\n" . print_r($_SESSION, true) . "\nCookie: " . print_r($_COOKIE, true) . "\n", FILE_APPEND);

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'medical_staff', 'admin', 'doctor'])) {
    file_put_contents(__DIR__ . '/../debug_session.log', date('Y-m-d H:i:s') . ' - call_next auth failed: ' . json_encode($_SESSION) . ' Cookie: ' . json_encode($_COOKIE) . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
if (!$sessionId) {
    echo json_encode(['status' => 'error', 'message' => 'session_id required.']);
    exit;
}

// Get session and check it's started
$stmt = $pdo->prepare("SELECT * FROM opd_sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['status' => 'error', 'message' => 'Session not found.']);
    exit;
}

if (!$session['doctor_started']) {
    echo json_encode(['status' => 'error', 'message' => 'Session not started yet. Click "Start Session" first.']);
    exit;
}

$nextToken = (int)$session['calling_token'] + 1;

// Find the token entry for this slot
$tokenStmt = $pdo->prepare("
    SELECT ot.*, u.full_name AS patient_name 
    FROM opd_tokens ot 
    JOIN users u ON ot.patient_id = u.id
    WHERE ot.session_id = ? AND ot.token_number = ?
");
$paddedToken = 'OPD-' . str_pad($nextToken, 3, '0', STR_PAD_LEFT);
$tokenStmt->execute([$sessionId, $paddedToken]);
$token = $tokenStmt->fetch();

// Update calling_token
$pdo->prepare("UPDATE opd_sessions SET calling_token = ? WHERE id = ?")->execute([$nextToken, $sessionId]);

// Notify the patient whose token is being called
if ($token) {
    $docName = $session['doctor_name'] ?: $session['opd_name'];
    $msg = "📢 Your token {$paddedToken} is being called now! Please proceed to the doctor's room for Dr. {$docName}.";
    $pdo->prepare("INSERT INTO notifications (user_id, type, message, token_id, is_read, created_at) VALUES (?, 'action', ?, ?, 0, NOW())")
        ->execute([$token['patient_id'], $msg, $token['id']]);
}

echo json_encode([
    'status'        => 'success',
    'calling_token' => $nextToken,
    'token_number'  => $paddedToken,
    'patient_name'  => $token ? $token['patient_name'] : null,
    'message'       => "Now calling Token {$paddedToken}" . ($token ? " — " . $token['patient_name'] : ''),
]);
