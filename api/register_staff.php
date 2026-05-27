<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$password = $_POST['password'] ?? '';
$centerId = !empty($_POST['center_id']) ? (int)$_POST['center_id'] : 0;

$missing = [];
if (!$firstName) $missing[] = 'First Name';
if (!$lastName) $missing[] = 'Last Name';
if (!$email) $missing[] = 'Email';
if (!$password) $missing[] = 'Password';
if (!$centerId) $missing[] = 'Medical Center';

if (!empty($missing)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing fields: ' . implode(', ', $missing)]);
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Insert new staff (is_active = 0 pending approval)
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, hashed_password, role, is_active, is_verified, center_id) 
         VALUES (?, ?, ?, ?, ?, 0, 1, ?)'
    );
    $stmt->execute([$fullName, $email, $phone ?: null, $passwordHash, 'staff', $centerId]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Registration successful! Your account is pending admin approval.',
        'redirect' => 'login.html'
    ]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        echo json_encode(['status' => 'error', 'message' => 'Email is already registered.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
    }
}
?>
