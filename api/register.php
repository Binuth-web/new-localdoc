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
$centerId = filter_input(INPUT_POST, 'center_id', FILTER_VALIDATE_INT) ?: null;

if (!$firstName || !$lastName || !$email || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'Name, email, and password are required.']);
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, hashed_password, role, is_active, is_verified)
         VALUES (?, ?, ?, ?, ?, 1, 1)'
    );
    $stmt->execute([$fullName, $email, $phone ?: null, $passwordHash, 'patient']);

    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    $_SESSION['role'] = 'patient';
    $_SESSION['name'] = $firstName;

    $redirect = 'index.html';
    if ($centerId) {
        $redirect .= '?book_center=' . $centerId;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Registration successful! Taking you to book your appointment…',
        'redirect' => $redirect,
    ]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        echo json_encode(['status' => 'error', 'message' => 'An account with this email already exists.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
    }
}
