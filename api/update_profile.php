<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';

    if (!$firstName || !$lastName || !$email) {
        echo json_encode(["status" => "error", "message" => "Name and email are required."]);
        exit;
    }

    try {
        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, password_hash=? WHERE user_id=?");
            $stmt->execute([$firstName, $lastName, $email, $phone, $passwordHash, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE user_id=?");
            $stmt->execute([$firstName, $lastName, $email, $phone, $_SESSION['user_id']]);
        }
        
        $_SESSION['name'] = $firstName; // Update session name
        
        echo json_encode(["status" => "success", "message" => "Profile updated successfully!"]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(["status" => "error", "message" => "That email is already in use by another account."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update profile."]);
        }
    }
}
?>
