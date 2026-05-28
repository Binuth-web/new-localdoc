<?php
require 'api/db_connect.php';
session_start();
$_SESSION['user_id'] = 13; // assuming 13 is a medical_staff
$_SESSION['role'] = 'medical_staff';
$_POST['token_id'] = 3; // from the screenshot, OPD-003 is probably id 3? Wait, let's just query it
$tok = $pdo->query("SELECT id FROM opd_tokens WHERE token_number='OPD-003' ORDER BY id DESC LIMIT 1")->fetch();
if ($tok) {
    $_POST['token_id'] = $tok['id'];
    $_POST['action'] = 'absent';
    $_SERVER['REQUEST_METHOD'] = 'POST';

    ob_start();
    require 'api/mark_attendance.php';
    $out = ob_get_clean();
    echo "API output: " . $out . "\n";
    
    // Check if token updated
    $updated = $pdo->query("SELECT status, attendance_marked FROM opd_tokens WHERE id=".$tok['id'])->fetch();
    print_r($updated);
} else {
    echo "Token OPD-003 not found\n";
}
?>
