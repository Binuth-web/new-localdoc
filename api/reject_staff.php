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
    // Delete the pending user
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = ? AND is_active = 0');
    $stmt->execute([$userId, 'staff']);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Staff member request rejected.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Staff member not found or already approved.']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>
