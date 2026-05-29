<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$idNumber = trim((string) ($_POST['id_number'] ?? ''));
$dob = trim((string) ($_POST['dob'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$centerId = filter_input(INPUT_POST, 'center_id', FILTER_VALIDATE_INT) ?: null;

$fullName = trim($firstName . ' ' . $lastName);

if (!$firstName || !$idNumber || !$dob || !$phone) {
    echo json_encode(['status' => 'error', 'message' => 'First Name, NIC Number, Date of Birth, and Contact Number are required.']);
    exit;
}

// Generate dummy email and password since patient accounts no longer use them
$email = preg_replace('/[^0-9]/', '', $phone) . '_' . time() . '@medconnect.local';
$passwordHash = password_hash('NOPASSWORD', PASSWORD_DEFAULT);

try {
    // Check if patient already exists by NIC Number
    $existingStmt = $pdo->prepare('SELECT id, full_name, center_id FROM users WHERE id_number = ? AND role = "patient"');
    $existingStmt->execute([$idNumber]);
    $existingUser = $existingStmt->fetch();

    if ($existingUser) {
        // Just log them in if they re-register with exact same info
        $_SESSION['user_id'] = (int) $existingUser['id'];
        $_SESSION['role'] = 'patient';
        $_SESSION['name'] = $existingUser['full_name'];
        if (!empty($existingUser['center_id'])) {
            $_SESSION['center_id'] = (int) $existingUser['center_id'];
        }
        $redirect = 'index.html';
        if ($centerId) {
            $redirect .= '?book_center=' . $centerId;
        }
        echo json_encode([
            'status' => 'success',
            'message' => 'Logged in with existing account.',
            'redirect' => $redirect,
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
    echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again. Error: ' . $e->getMessage()]);
}
?>
