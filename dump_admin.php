<?php
require 'api/db_connect.php';
$stmt = $pdo->query("SELECT * FROM users WHERE email LIKE '%admin%' OR role='admin'");
print_r($stmt->fetchAll());
