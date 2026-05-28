<?php
session_start();
$_SESSION['user_id'] = 13;
$_SESSION['role'] = 'admin';
$_POST['token_id'] = 4;
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
require 'api/approve_late_token.php';
$output = ob_get_clean();

echo "Output: " . $output . "\n";
?>
