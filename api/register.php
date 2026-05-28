<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$centerId = filter_input(INPUT_POST, 'center_id', FILTER_VALIDATE_INT) ?: null;

if (!$fullName || !$phone || $age === false) {
    echo json_encode(['status' => 'error', 'message' => 'Full Name, Contact Number, and Age are required.']);
    exit;
}

// Generate dummy email and password since patient accounts no longer use them
$email = preg_replace('/[^0-9]/', '', $phone) . '_' . time() . '@medconnect.local';
$passwordHash = password_hash('NOPASSWORD', PASSWORD_DEFAULT);

try {
    // Check if patient already exists by phone and name
    $existingStmt = $pdo->prepare('SELECT id, full_name, center_id FROM users WHERE phone = ? AND full_name = ? AND role = "patient"');
    $existingStmt->execute([$phone, $fullName]);
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
        'INSERT INTO users (full_name, email, phone, age, hashed_password, role, is_active, is_verified, center_id) 
         VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?)'
    );
    $stmt->execute([$fullName, $email, $phone, $age, $passwordHash, 'patient', $centerId]);

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
