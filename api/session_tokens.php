<?php
require 'db_connect.php';
header('Content-Type: application/json');

$sessionId = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);
if (!$sessionId) {
    echo json_encode(['status' => 'error', 'message' => 'session_id required.']);
    exit;
}

// Get session info
$stmt = $pdo->prepare("SELECT os.*, mc.name AS center_name FROM opd_sessions os JOIN medical_centers mc ON os.clinic_id = mc.id WHERE os.id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['status' => 'error', 'message' => 'Session not found.']);
    exit;
}

// Get all booked tokens for this session
$stmt = $pdo->prepare("
    SELECT ot.*, u.full_name AS patient_name, u.phone AS patient_phone
    FROM opd_tokens ot
    JOIN users u ON ot.patient_id = u.id
    WHERE ot.session_id = ?
    ORDER BY ot.token_number ASC
");
$stmt->execute([$sessionId]);
$tokens = $stmt->fetchAll();

// Build slot list: slots 1..max_tokens + any late_request slots beyond
$maxSlots = (int)$session['max_tokens'];

// Count late_request extras (they sit beyond max_tokens)
$lateRequests = array_filter($tokens, fn($t) => $t['status'] === 'late_request');

$slots = [];
for ($i = 1; $i <= $maxSlots; $i++) {
    $padded = 'OPD-' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $match = array_values(array_filter($tokens, fn($t) => $t['token_number'] === $padded));
    if ($match) {
        $t = $match[0];
        // Determine display status
        if ($t['status'] === 'waiting' && $t['attendance_marked'] == 1) {
            $displayStatus = 'present';
        } elseif ($t['status'] === 'waiting') {
            $displayStatus = 'pending';
        } elseif ($t['status'] === 'served' || $t['status'] === 'called') {
            continue; // Remove completed tokens from the dashboard
        } else {
            $displayStatus = $t['status'];
        }
        $patientName = ($t['token_type'] === 'walk-in') ? 'On Site Patients' : $t['patient_name'];
        $slots[] = [
            'slot' => $i,
            'token_number' => $padded,
            'token_id' => (int)$t['id'],
            'patient_name' => $patientName,
            'patient_phone' => $t['patient_phone'],
            'status' => $displayStatus,
            'db_status' => $t['status'],
            'attendance_marked' => (bool)$t['attendance_marked'],
        ];
    } else {
        $slots[] = [
            'slot' => $i,
            'token_number' => $padded,
            'token_id' => null,
            'patient_name' => null,
            'status' => 'empty',
            'db_status' => 'empty',
            'attendance_marked' => false,
        ];
    }
}

// Append late_request tokens at the end (they are beyond regular slots)
foreach ($lateRequests as $lr) {
    // Check if already in slots (it would be if token_number is within max range)
    $slotNum = (int)ltrim(str_replace('OPD-', '', $lr['token_number']), '0');
    if ($slotNum <= $maxSlots) continue; // already included above
    $patientName = ($lr['token_type'] === 'walk-in') ? 'On Site Patients' : $lr['patient_name'];
    $slots[] = [
        'slot' => 'LATE',
        'token_number' => $lr['token_number'],
        'token_id' => (int)$lr['id'],
        'patient_name' => $patientName,
        'patient_phone' => $lr['patient_phone'],
        'status' => 'late_request',
        'db_status' => 'late_request',
        'attendance_marked' => false,
    ];
}

echo json_encode([
    'status' => 'success',
    'session' => $session,
    'slots' => $slots,
]);
?>
