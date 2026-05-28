<?php
require 'api/db_connect.php';

// Fetch existing centers
$stmt = $pdo->query('SELECT id, name, type, area FROM medical_centers ORDER BY id ASC');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . ' - ' . $row['name'] . ' (' . $row['type'] . ', ' . $row['area'] . ")\n";
}
?>
