<?php
require 'api/db_connect.php';
$stmt = $pdo->query('SHOW TABLES');
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . "\n";
    $stmt2 = $pdo->query('DESCRIBE ' . $row[0]);
    while ($col = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        echo '  - ' . $col['Field'] . ' (' . $col['Type'] . ")\n";
    }
}
?>
