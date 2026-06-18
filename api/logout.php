<?php
$portal = $_GET['portal'] ?? $_COOKIE['medconnect_portal'] ?? 'default';
if ($portal === 'admin') $portal = 'staff';
session_name('medconnect_' . $portal);
if (session_status() === PHP_SESSION_NONE) session_start();
session_unset();
session_destroy();
setcookie('medconnect_portal', '', time() - 3600, "/");

$redirect = '../index.html';
if ($portal === 'staff' || $portal === 'admin') {
    $redirect = '../staff_login.html';
}

header("Location: " . $redirect);
exit;
?>
