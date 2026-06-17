<?php
/**
 * check_session.php
 * Verifies the caller has a valid, active session.
 * Accepts optional ?portal=patient|staff|admin  (defaults to cookie value).
 */

$portalParam = $_GET['portal'] ?? $_COOKIE['medconnect_portal'] ?? 'default';
// admin logins share the 'staff' session space
if ($portalParam === 'admin') $portalParam = 'staff';

session_name('medconnect_' . $portalParam);
require 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

// Re-check is_active from the database on every request.
$stmt = $pdo->prepare('SELECT id, is_active, role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_unset();
    session_destroy();
    echo json_encode(['status' => 'error', 'message' => 'Account not found.']);
    exit;
}

if ((int)$user['is_active'] === 0) {
    // Account deactivated by admin — kill the session immediately
    session_unset();
    session_destroy();
    echo json_encode(['status' => 'error', 'message' => 'Your account has been deactivated.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'role'   => $_SESSION['role'],
    'name'   => $_SESSION['name'] ?? '',
]);
?>
