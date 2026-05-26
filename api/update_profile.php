<?php
require 'db_connect.php';
require 'helpers.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone = trim((string) ($_POST['phone'] ?? ''));
$password = $_POST['password'] ?? '';

if (!$firstName || !$email) {
    echo json_encode(['status' => 'error', 'message' => 'Name and email are required.']);
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);

try {
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'UPDATE users SET full_name = ?, email = ?, phone = ?, hashed_password = ? WHERE id = ?'
        );
        $stmt->execute([$fullName, $email, $phone ?: null, $hash, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
        $stmt->execute([$fullName, $email, $phone ?: null, $_SESSION['user_id']]);
    }

    $_SESSION['name'] = $firstName;
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully!']);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        echo json_encode(['status' => 'error', 'message' => 'That email is already in use by another account.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update profile.']);
    }
}
