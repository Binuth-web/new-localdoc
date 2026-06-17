<?php
// Prevent db_connect from opening any session prematurely
// We handle session switching manually below
$_skipAutoSession = true;
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
    // Patient login uses First Name, Last Name, and ID Number
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $idNumber = trim((string) ($_POST['id_number'] ?? ''));

    if (!$firstName || !$lastName || !$idNumber) {
        echo json_encode(['status' => 'error', 'message' => 'First Name, Last Name, and NIC Number are required.']);
        exit;
    }
    
    $expectedFullName = trim($firstName . ' ' . $lastName);

    $stmt = $pdo->prepare('SELECT id, full_name, role, center_id, is_active FROM users WHERE id_number = ? AND role = "patient"');
    $stmt->execute([$idNumber]);
    $user = $stmt->fetch();

    // Verify full name matches exactly (case-insensitive)
    $nameMatch = false;
    if ($user) {
        if (strcasecmp($user['full_name'], $expectedFullName) === 0) {
            $nameMatch = true;
        }
    }

    if ($nameMatch && $user) {
        if ((int)$user['is_active'] === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Your account has been deactivated.']);
            exit;
        }
        $portalCookie = 'patient';
        setcookie('medconnect_portal', $portalCookie, time() + (86400 * 30), "/");

        session_write_close();
        session_name('medconnect_' . $portalCookie);
        session_start();

        // Regenerate session ID for security but keep the old file intact
        // so other portal sessions on the same browser are not destroyed
        session_unset();
        session_regenerate_id(false);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];
        if ($user['center_id']) {
            $_SESSION['center_id'] = $user['center_id'];
        }

        $redirect = 'index.html#centers-section';
        $redirectParam = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
        if ($redirectParam === 'index.html' && isset($_POST['center'])) {
            $redirect = 'index.html?center=' . urlencode($_POST['center']);
        } elseif (!empty($redirectParam)) {
            $redirect = $redirectParam;
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'redirect' => $redirect
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Patient not found. Please register or check your details.']);
    }

} else {
    // Staff and Admin login uses Username (or Email) and Password
    $loginId = trim((string)($_POST['username'] ?? $_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$loginId || !$password) {
        echo json_encode(['status' => 'error', 'message' => 'Username/Email and password required.']);
        exit;
    }

    $dbRole = ($role === 'medical_staff') ? 'staff' : $role;

    $stmt = $pdo->prepare('SELECT id, full_name, hashed_password, role, center_id, is_active FROM users WHERE email = ? AND role = ?');
    $stmt->execute([$loginId, $dbRole]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['hashed_password'])) {
        // Block deactivated accounts before even creating a session
        if ((int)$user['is_active'] === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Your account has been deactivated.']);
            exit;
        }

        // Set a cookie to track the portal so sessions don't conflict
        $portalCookie = 'staff';
        setcookie('medconnect_portal', $portalCookie, time() + (86400 * 30), "/");

        session_write_close();
        session_name('medconnect_' . $portalCookie);
        session_start();

        // Regenerate session ID for security but keep the old file intact
        // so other portal sessions on the same browser are not destroyed
        session_unset();
        session_regenerate_id(false);

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
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password.']);
    }
}
?>
