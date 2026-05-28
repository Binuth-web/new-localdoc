<?php
require 'api/db_connect.php';
$stmt = $pdo->query('DESCRIBE opd_sessions');
foreach ($stmt->fetchAll() as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
