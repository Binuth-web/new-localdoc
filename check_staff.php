<?php
require 'api/db_connect.php';
$stmt = $pdo->query('SELECT email FROM users WHERE role="staff"');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
