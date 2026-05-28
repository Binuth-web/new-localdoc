<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

$notifId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
if (!$notifId) {
    // Dismiss all
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    echo json_encode(['status' => 'success', 'message' => 'All notifications dismissed.']);
    exit;
}

// Dismiss one (only if it belongs to this user)
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$notifId, $_SESSION['user_id']]);
echo json_encode(['status' => 'success']);
?>
