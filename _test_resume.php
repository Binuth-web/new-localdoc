<?php
session_start();
$_SESSION['user_id'] = 13;
$_SESSION['role'] = 'admin'; 
$_POST['session_id'] = 1;
$_POST['action'] = 'resume';
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
require 'api/update_session_status.php';
$output = ob_get_clean();
echo "Resume test: " . $output . "\n";
?>
