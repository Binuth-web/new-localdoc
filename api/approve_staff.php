<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ? AND role = ?');
    $stmt->execute([$userId, 'staff']);

    if ($stmt->rowCount() > 0) {
        // Insert notification
        $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
        $notifStmt->execute([
            $userId, 
            'Your account has been approved. You can now login.', 
            'info'
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Staff member approved successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Staff member not found or already approved.']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>
