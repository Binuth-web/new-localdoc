<?php
require 'db_connect.php';
session_destroy();
setcookie('medconnect_portal', '', time() - 3600, "/");
header("Location: ../index.html");
exit;
?>
