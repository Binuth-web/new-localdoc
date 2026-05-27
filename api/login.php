<?php
require 'db_connect.php';
require 'helpers.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$requestedRole = $_POST['role'] ?? 'patient';
$dbRole = mapLoginRoleToDb($requestedRole);

if (!$email || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'Email and password required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, hashed_password, role, full_name, is_active, center_id FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['hashed_password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
    exit;
}

if (!$user['is_active']) {
    echo json_encode(['status' => 'error', 'message' => 'Your account has been deactivated.']);
    exit;
}

if ($user['role'] !== $dbRole) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid role selected.']);
    exit;
}

$nameParts = splitFullName($user['full_name']);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['role'] = $user['role'] === 'staff' ? 'medical_staff' : $user['role'];
$_SESSION['name'] = $nameParts['first_name'];
if (isset($user['center_id'])) {
    $_SESSION['center_id'] = (int) $user['center_id'];
}

$redirect = $_POST['redirect'] ?? '';
$patientRedirects = ['index.html', 'dashboard_patient.php', 'profile.php'];

if ($user['role'] === 'patient') {
    if (!in_array($redirect, $patientRedirects, true)) {
        $bookCenter = filter_input(INPUT_POST, 'book_center', FILTER_VALIDATE_INT);
        $redirect = $bookCenter ? 'index.html?book_center=' . $bookCenter : 'index.html';
    }
} elseif ($user['role'] === 'staff') {
    $redirect = 'dashboard_staff.php';
} elseif ($user['role'] === 'admin') {
    $redirect = 'dashboard_admin.html';
}

echo json_encode(['status' => 'success', 'redirect' => $redirect]);
