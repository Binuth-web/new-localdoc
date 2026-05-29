<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$role = $_POST['role'] ?? 'patient';
$portal = $_POST['portal'] ?? 'patient';

if ($role !== $portal && !($role === 'medical_staff' && $portal === 'staff')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid login portal for this role.']);
    exit;
}

if ($role === 'patient') {
    // Patient login uses First Name and ID Number
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $idNumber = trim((string) ($_POST['id_number'] ?? ''));

    if (!$firstName || !$idNumber) {
        echo json_encode(['status' => 'error', 'message' => 'First Name and NIC Number are required.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, full_name, role, center_id FROM users WHERE id_number = ? AND full_name LIKE ? AND role = "patient"');
    $stmt->execute([$idNumber, $firstName . '%']);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];
        if ($user['center_id']) {
            $_SESSION['center_id'] = $user['center_id'];
        }

        $redirect = 'dashboard_patient.php';
        $redirectParam = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
        if ($redirectParam === 'book_appointment.html' && isset($_POST['center'])) {
            $redirect = 'book_appointment.html?center=' . urlencode($_POST['center']);
        } elseif (!empty($redirectParam)) {
            $redirect = $redirectParam;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'redirect' => $redirect
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Patient not found. Please register or check your details.']);
    }

} else {
    // Staff and Admin login uses Email and Password
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password required.']);
        exit;
    }

    $dbRole = ($role === 'medical_staff') ? 'staff' : $role;

    $stmt = $pdo->prepare('SELECT id, full_name, hashed_password, role, center_id FROM users WHERE email = ? AND role = ?');
    $stmt->execute([$email, $dbRole]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['hashed_password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = explode(' ', $user['full_name'])[0];
        
        if ($user['center_id']) {
            $_SESSION['center_id'] = $user['center_id'];
        }

        $redirect = 'dashboard_patient.php';
        if ($role === 'medical_staff' || $role === 'staff') $redirect = 'dashboard_staff.php';
        if ($role === 'admin') $redirect = 'dashboard_admin.html';
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'redirect' => $redirect
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
    }
}
?>
