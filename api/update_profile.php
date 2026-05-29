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
$idNumber = trim((string) ($_POST['id_number'] ?? ''));
$dob = trim((string) ($_POST['dob'] ?? ''));

if (!$firstName || !$email || !$idNumber || !$dob) {
    echo json_encode(['status' => 'error', 'message' => 'Name, Email, NIC Number, and DOB are required.']);
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);

try {
    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, id_number = ?, date_of_birth = ? WHERE id = ?');
    $stmt->execute([$fullName, $email, $phone ?: null, $idNumber, $dob, $_SESSION['user_id']]);

    $_SESSION['name'] = $firstName;
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully!']);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        echo json_encode(['status' => 'error', 'message' => 'That email is already in use by another account.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update profile.']);
    }
}
