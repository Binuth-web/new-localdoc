<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$idNumber = trim((string) ($_POST['id_number'] ?? ''));
$dob = trim((string) ($_POST['dob'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$centerId = filter_input(INPUT_POST, 'center_id', FILTER_VALIDATE_INT) ?: null;

$portalCookie = 'patient';
setcookie('medconnect_portal', $portalCookie, time() + (86400 * 30), "/");

session_write_close();
session_name('medconnect_' . $portalCookie);
session_start();

// Clear any existing session to ensure a fresh login for the new user
session_unset();
session_regenerate_id(true);

$fullName = trim($firstName . ' ' . $lastName);

if (!$firstName || !$idNumber || !$dob || !$phone || !$email) {
    echo json_encode(['status' => 'error', 'message' => 'All fields including Email are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}

// Generate dummy password since patient accounts no longer use them
$passwordHash = password_hash('NOPASSWORD', PASSWORD_DEFAULT);

try {
    // Check if patient already exists by NIC Number
    $existingStmt = $pdo->prepare('SELECT id, full_name, center_id FROM users WHERE id_number = ? AND role = "patient"');
    $existingStmt->execute([$idNumber]);
    $existingUser = $existingStmt->fetch();

    if ($existingUser) {
        echo json_encode([
            'status' => 'error',
            'message' => 'This NIC is already registered. If you already have an account, please use the Login page.',
        ]);
        exit;
    }

    // Insert new patient
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, id_number, date_of_birth, hashed_password, role, is_active, is_verified, center_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?)'
    );
    $stmt->execute([$fullName, $email, $phone, $idNumber, $dob, $passwordHash, 'patient', $centerId]);

    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    $_SESSION['role'] = 'patient';
    $_SESSION['name'] = $fullName;
    if ($centerId) {
        $_SESSION['center_id'] = (int) $centerId;
    }
    
    $redirect = 'index.html#centers-section';

    echo json_encode([
        'status' => 'success',
        'message' => 'Registration successful! Taking you to book your appointment…',
        'redirect' => $redirect,
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again. Error: ' . $e->getMessage()]);
}
?>
