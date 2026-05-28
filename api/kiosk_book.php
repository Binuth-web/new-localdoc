<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

$sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
$name  = trim($_POST['name']  ?? '');
$phone = trim($_POST['phone'] ?? '');

if (!$sessionId || !$name) {
    echo json_encode(['status' => 'error', 'message' => 'Session ID and Name required.']);
    exit;
}

$pdo->beginTransaction();
try {
    // Lock session row (FOR UPDATE must be inside a transaction)
    $stmt = $pdo->prepare("SELECT * FROM opd_sessions WHERE id = ? AND is_active = 1 FOR UPDATE");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Session unavailable or not active.']);
        exit;
    }

    if ((int)$session['current_token'] >= (int)$session['max_tokens']) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'No tokens available. Session is full.']);
        exit;
    }

    // Determine next token number
    $newToken    = (int)$session['current_token'] + 1;
    $paddedToken = 'OPD-' . str_pad($newToken, 3, '0', STR_PAD_LEFT);

    // Create a walk-in user record.
    // DB column is 'hashed_password', role enum is ('patient','doctor','staff','admin')
    $dummyEmail = 'walkin_' . time() . '_' . rand(1000, 9999) . '@medconnect.local';
    $pdo->prepare("INSERT INTO users (full_name, email, hashed_password, role, phone, is_active, is_verified) VALUES (?, ?, ?, 'patient', ?, 1, 1)")
        ->execute([$name, $dummyEmail, 'WALKIN_NO_LOGIN', $phone]);
    $patientId = $pdo->lastInsertId();

    // Insert walk-in token — mark attendance_marked = 1 (present immediately)
    $pdo->prepare("INSERT INTO opd_tokens (patient_id, session_id, token_number, token_type, status, attendance_marked) VALUES (?, ?, ?, 'walk-in', 'waiting', 1)")
        ->execute([$patientId, $sessionId, $paddedToken]);

    // Advance session current_token counter
    $pdo->prepare("UPDATE opd_sessions SET current_token = ? WHERE id = ?")
        ->execute([$newToken, $sessionId]);

    $pdo->commit();
    echo json_encode([
        'status'       => 'success',
        'token_number' => $paddedToken,
        'name'         => $name,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Failed to issue token: ' . $e->getMessage()]);
}
?>
