<?php
// If the caller sets $_skipAutoSession = true before requiring this file,
// skip automatic session handling (login.php / register.php manage sessions manually).
if (empty($_skipAutoSession)) {
    if (session_name() === 'PHPSESSID') {
        // No explicit session_name was set by the caller.
        // Determine the portal from (in priority order):
        //   1. ?portal= query param (reliable, sent by frontend)
        //   2. medconnect_portal cookie (can be stale if another tab logged in)
        $portal = $_GET['portal'] ?? $_COOKIE['medconnect_portal'] ?? 'default';
        // Normalize: admin shares the 'staff' session space
        if ($portal === 'admin') $portal = 'staff';
        session_name('medconnect_' . $portal);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        
        // Self-heal: If the browser's cookies got tangled by the previous bug 
        // (both staff and patient pointing to the exact same session file),
        // we force a regeneration for the current portal to break the link.
        if (!empty($_COOKIE['medconnect_staff']) && 
            !empty($_COOKIE['medconnect_patient']) && 
            $_COOKIE['medconnect_staff'] === $_COOKIE['medconnect_patient']) {
            session_regenerate_id(true);
        }
    }

    // Safety net: if the session is empty but we have session cookies for
    // other portals, try those. This handles the case where the
    // medconnect_portal cookie was overwritten by another tab/login.
    if (empty($_SESSION['user_id']) && session_name() !== 'medconnect_staff' && isset($_COOKIE['medconnect_staff'])) {
        session_write_close();
        session_name('medconnect_staff');
        session_id($_COOKIE['medconnect_staff']); // Force it to use the cookie's ID
        session_start();
    }
    if (empty($_SESSION['user_id']) && session_name() !== 'medconnect_patient' && isset($_COOKIE['medconnect_patient'])) {
        session_write_close();
        session_name('medconnect_patient');
        session_id($_COOKIE['medconnect_patient']); // Force it to use the cookie's ID
        session_start();
    }
}

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'medconnect_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}
