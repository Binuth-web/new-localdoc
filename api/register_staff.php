<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone = trim((string) ($_POST['phone'] ?? ''));
$password = $_POST['password'] ?? '';
$centerId = filter_input(INPUT_POST, 'center_id', FILTER_VALIDATE_INT);

if (!$firstName || !$lastName || !$email || !$password || !$centerId) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
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
