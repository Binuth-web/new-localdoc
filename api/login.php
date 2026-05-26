<?php
require 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $requested_role = $_POST['role'] ?? 'patient';

    if (!$email || !$password) {
        echo json_encode(["status" => "error", "message" => "Email and password required."]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT user_id, password_hash, role, first_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['role'] !== $requested_role) {
            echo json_encode(["status" => "error", "message" => "Invalid role selected."]);
            exit;
        }

        // Setup session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['first_name'];

        $redirect = '';
        if ($user['role'] === 'patient') $redirect = 'index.html';
        elseif ($user['role'] === 'medical_staff') $redirect = 'dashboard_staff.html';
        elseif ($user['role'] === 'admin') $redirect = 'dashboard_admin.html';

        echo json_encode(["status" => "success", "redirect" => $redirect]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
    }
}
?>
