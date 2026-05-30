<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$password = $_POST['password'] ?? '';
$centerId = !empty($_POST['center_id']) ? (int)$_POST['center_id'] : 0;

$missing = [];
if (!$fullName) $missing[] = 'Full Name';
if (!$username) $missing[] = 'Username';
if (!$password) $missing[] = 'Password';
if (!$centerId) $missing[] = 'Medical Center';

if (!empty($missing)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing fields: ' . implode(', ', $missing)]);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long.']);
    exit;
}
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Insert new staff (is_active = 0 pending approval)
    // Storing username in the email column for compatibility
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, hashed_password, role, is_active, is_verified, center_id) 
         VALUES (?, ?, ?, ?, ?, 0, 1, ?)'
    );
    $stmt->execute([$fullName, $username, $phone ?: null, $passwordHash, 'staff', $centerId]);

    $newUserId = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'Registration successful! Your account is pending admin approval.',
        'redirect' => 'staff_pending.html?user_id=' . $newUserId
    ]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        echo json_encode(['status' => 'error', 'message' => 'Username is already registered.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
    }
}
?>
