<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';

    if (!$firstName || !$lastName || !$email || !$password) {
        echo json_encode(["status" => "error", "message" => "All fields except phone are required."]);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role, first_name, last_name, phone, dob) VALUES (?, ?, 'patient', ?, ?, ?, ?)");
        $stmt->execute([$email, $passwordHash, $firstName, $lastName, $phone, $dob]);
        echo json_encode(["status" => "success", "message" => "Registration successful! You can now log in."]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            echo json_encode(["status" => "error", "message" => "An account with this email already exists."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Registration failed. Please try again."]);
        }
    }
}
?>
