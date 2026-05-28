<?php
session_start();
require 'api/db_connect.php';

// Create a dummy token in late_request state
$pdo->exec("INSERT INTO opd_tokens (session_id, token_number, patient_id, status, token_type) VALUES (1, 'OPD-999', 1, 'late_request', 'online')");
$tokenId = $pdo->lastInsertId();

$_SESSION['user_id'] = 13;
$_SESSION['role'] = 'admin';
$_POST['token_id'] = $tokenId;
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
require 'api/approve_late_token.php';
$output = ob_get_clean();

echo "Output for admin: " . $output . "\n";

// Change role to staff
$_SESSION['role'] = 'staff';
// Make another token
$pdo->exec("INSERT INTO opd_tokens (session_id, token_number, patient_id, status, token_type) VALUES (1, 'OPD-998', 1, 'late_request', 'online')");
$tokenId2 = $pdo->lastInsertId();
$_POST['token_id'] = $tokenId2;
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
require 'api/approve_late_token.php';
$output = ob_get_clean();

echo "Output for staff: " . $output . "\n";
?>
