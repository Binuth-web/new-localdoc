<?php
session_name('medconnect_staff');
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

file_put_contents(__DIR__ . '/../debug_all.log', date('Y-m-d H:i:s') . " - start_session\n" . print_r($_SESSION, true) . "\nCookie: " . print_r($_COOKIE, true) . "\n", FILE_APPEND);

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'medical_staff', 'admin', 'doctor'])) {
    file_put_contents(__DIR__ . '/../debug_session.log', date('Y-m-d H:i:s') . ' - start_session auth failed: ' . json_encode($_SESSION) . ' Cookie: ' . json_encode($_COOKIE) . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
if (!$sessionId) {
    echo json_encode(['status' => 'error', 'message' => 'session_id required.']);
    exit;
}

// Mark session as doctor started
$stmt = $pdo->prepare("UPDATE opd_sessions SET doctor_started = 1 WHERE id = ?");
$stmt->execute([$sessionId]);

if ($stmt->rowCount() === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Session not found.']);
    exit;
}

// Get all patients with tokens in this session (non-cancelled)
$tokenStmt = $pdo->prepare("
    SELECT ot.patient_id, os.opd_name, os.doctor_name, os.session_date
    FROM opd_tokens ot
    JOIN opd_sessions os ON ot.session_id = os.id
    WHERE ot.session_id = ? AND ot.status NOT IN ('cancelled', 'served')
");
$tokenStmt->execute([$sessionId]);
$patients = $tokenStmt->fetchAll();

$sessionRow = $pdo->prepare("SELECT opd_name, doctor_name FROM opd_sessions WHERE id = ?");
$sessionRow->execute([$sessionId]);
$sess = $sessionRow->fetch();
$docName = $sess['doctor_name'] ?: $sess['opd_name'];

// Send notification to all token holders
$notifStmt = $pdo->prepare("
    INSERT INTO notifications (user_id, type, message, token_id, is_read, created_at)
    VALUES (?, 'info', ?, NULL, 0, NOW())
");
$sentTo = [];
foreach ($patients as $p) {
    if (in_array($p['patient_id'], $sentTo)) continue;
    $sentTo[] = $p['patient_id'];
    $msg = "🩺 Doctor session has started for Dr. {$docName}. Please be ready — your token will be called soon!";
    $notifStmt->execute([$p['patient_id'], $msg]);
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Session started! Notifications sent to ' . count($sentTo) . ' patient(s).',
]);
