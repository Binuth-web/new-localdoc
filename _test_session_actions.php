<?php
session_start();
$_SESSION['user_id'] = 13;
$_SESSION['role'] = 'staff';

require 'api/db_connect.php'; // already calls session_start, ignore notice

$_POST['session_id'] = 1;

// Test block
$_POST['action'] = 'block';
$_SERVER['REQUEST_METHOD'] = 'POST';
ob_start();
include 'api/update_session_status.php';
$out = ob_get_clean();
echo "Block:  $out\n";

// Test resume
$_POST['action'] = 'resume';
ob_start();
include 'api/update_session_status.php';
$out = ob_get_clean();
echo "Resume: $out\n";

// Test complete
$_POST['action'] = 'complete';
ob_start();
include 'api/update_session_status.php';
$out = ob_get_clean();
echo "Complete: $out\n";
?>
