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
         FROM opd_sessions WHERE id = ? AND status = 'active' FOR UPDATE'
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

    // ⚠️ Attendance warning notification for the patient
    $lastTokenId = (int)$pdo->lastInsertId();
    $sessionDate = $session['session_date'];
    $warningMsg  = "⚠️ Booking Confirmed — Token {$tokenNumber} on {$sessionDate}.\n\nIMPORTANT: You must be physically present at the medical center before your session time. Medical staff will mark your attendance. If you are not marked present, your token will be set to ABSENT and your slot given up. If you are running late, you can request a Late Token from your dashboard.";
    $pdo->prepare("INSERT INTO notifications (user_id, token_id, message, type) VALUES (?, ?, ?, 'warning')")
        ->execute([$_SESSION['user_id'], $lastTokenId, $warningMsg]);

    echo json_encode([
        'status' => 'success',
        'message' => "Booked successfully! Your token is {$tokenNumber}. Check your dashboard for important attendance info.",
        'redirect' => 'dashboard_patient.php',
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Could not book appointment. Please try again.']);
}
