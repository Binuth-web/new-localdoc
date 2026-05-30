<?php
require 'api/db_connect.php';
$stmt = $pdo->query("DESCRIBE notifications");
$result = $stmt->fetchAll();
print_r($result);
?>
