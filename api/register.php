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
// Generate a random password if none provided (for patients without password)
if (empty($password)) {
    $password = bin2hex(random_bytes(4)); // 8-character random password
}
$centerId = filter_input(INPUT_POST, 'center_id', FILTER_VALIDATE_INT) ?: null;

if (!$firstName || !$lastName || !$email) {
    echo json_encode(['status' => 'error', 'message' => 'Name and email are required.']);
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Insert new patient
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, hashed_password, role, is_active, is_verified, center_id) 
         VALUES (?, ?, ?, ?, ?, 1, 1, ?)'
    );
    $stmt->execute([$fullName, $email, $phone ?: null, $passwordHash, 'patient', $centerId]);

    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    $_SESSION['role'] = 'patient';
    $_SESSION['name'] = $firstName;
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
    if ((int) $e->getCode() === 23000) {
        // Duplicate email: fetch existing user and log them in
        $existingStmt = $pdo->prepare('SELECT id, full_name, center_id FROM users WHERE email = ?');
        $existingStmt->execute([$email]);
        $existingUser = $existingStmt->fetch();
        if ($existingUser) {
            $_SESSION['user_id'] = (int) $existingUser['id'];
            $_SESSION['role'] = 'patient';
            $_SESSION['name'] = explode(' ', $existingUser['full_name'])[0];
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
    }
    echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
}
?>
