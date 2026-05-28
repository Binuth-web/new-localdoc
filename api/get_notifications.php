<?php
require 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

echo json_encode([
    'status' => 'success',
    'notifications' => $notifications,
    'unread_count' => count(array_filter($notifications, fn($n) => !$n['is_read'])),
]);
?>
