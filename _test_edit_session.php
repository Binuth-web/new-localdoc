<?php
session_start();
$_SESSION['user_id'] = 13;
$_SESSION['role'] = 'staff';

$_POST['session_id']   = 1;
$_POST['clinic_id']    = 1;
$_POST['doctor_name']  = 'Dr. Test Doctor';
$_POST['session_date'] = '2026-06-01';
$_POST['start_time']   = '09:00';
$_POST['end_time']     = '12:00';
$_POST['max_tokens']   = 25;
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
require 'api/edit_session.php';
$out = ob_get_clean();
echo "Edit result: $out\n";
?>
