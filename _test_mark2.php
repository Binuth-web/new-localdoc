<?php
session_start();
$_SESSION['user_id'] = 13;
$_SESSION['role'] = 'staff';
$_POST['token_id'] = 4;
$_POST['action'] = 'absent';
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
require 'api/mark_attendance.php';
$output = ob_get_clean();

echo "Output: " . $output . "\n";
?>
