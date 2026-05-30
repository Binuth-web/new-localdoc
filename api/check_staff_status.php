<?php
require 'db_connect.php';
header('Content-Type: application/json');

$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT is_active FROM users WHERE id = ? AND role = "staff"');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        // User was deleted (rejected)
        echo json_encode(['status' => 'success', 'account_status' => 'rejected']);
    } elseif ((int)$user['is_active'] === 1) {
        echo json_encode(['status' => 'success', 'account_status' => 'approved']);
    } else {
        echo json_encode(['status' => 'success', 'account_status' => 'pending']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>
