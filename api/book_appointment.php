<?php
require 'db_connect.php';
require 'helpers.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    echo json_encode(['status' => 'error', 'message' => 'Please log in as a patient to book.']);
    exit;
}

if (!hasOpdTables($pdo)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'OPD booking is not set up. Run medconnect-kandy database migrations.',
    ]);
    exit;
}

$sessionId = filter_input(INPUT_POST, 'availability_id', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);

if (!$sessionId) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a session to book.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id, clinic_id, current_token, max_tokens, session_date, start_time
         FROM opd_sessions WHERE id = ? AND is_active = 1 FOR UPDATE'
    );
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Invalid session selected.']);
        exit;
    }

    if ((int) $session['current_token'] >= (int) $session['max_tokens']) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'This session is fully booked.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM opd_tokens
         WHERE patient_id = ? AND session_id = ? AND status NOT IN ('cancelled', 'no-show')"
    );
    $stmt->execute([$_SESSION['user_id'], $sessionId]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'You already have a booking for this session.']);
        exit;
    }

    $nextNum = (int) $session['current_token'] + 1;
    $tokenNumber = 'OPD-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
    $estimatedWait = (int) $session['current_token'] * 15;

    $pdo->prepare('UPDATE opd_sessions SET current_token = current_token + 1 WHERE id = ?')
        ->execute([$sessionId]);

    $pdo->prepare(
        'INSERT INTO opd_tokens (token_number, session_id, patient_id, token_type, status, estimated_time)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$tokenNumber, $sessionId, $_SESSION['user_id'], 'online', 'waiting', $estimatedWait]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Booked successfully! Your token is {$tokenNumber}.",
        'redirect' => 'dashboard_patient.php',
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Could not book appointment. Please try again.']);
}
