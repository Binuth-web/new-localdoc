<?php
require 'db_connect.php';
header('Content-Type: application/json');

$loggedIn = isset($_SESSION['user_id']);
$isPatient = $loggedIn && ($_SESSION['role'] ?? '') === 'patient';

echo json_encode([
    'status' => 'success',
    'logged_in' => $loggedIn,
    'is_patient' => $isPatient,
    'role' => $_SESSION['role'] ?? null,
    'name' => $_SESSION['name'] ?? null,
]);
