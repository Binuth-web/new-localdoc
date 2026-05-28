<?php
require 'api/db_connect.php';

// Check for related sessions/tokens before deletion
echo "=== Checking opd_sessions per center ===\n";
$stmt = $pdo->query("SELECT clinic_id, COUNT(*) as cnt FROM opd_sessions GROUP BY clinic_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "clinic_id " . $row['clinic_id'] . " has " . $row['cnt'] . " sessions\n";
}

echo "\n=== Checking walkin_counters ===\n";
$stmt = $pdo->query("SELECT clinic_id, COUNT(*) as cnt FROM walkin_counters GROUP BY clinic_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "clinic_id " . $row['clinic_id'] . " has " . $row['cnt'] . " walkin_counters\n";
}

echo "\n=== Checking users per center ===\n";
$stmt = $pdo->query("SELECT center_id, COUNT(*) as cnt FROM users WHERE center_id IS NOT NULL AND center_id > 0 GROUP BY center_id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "center_id " . $row['center_id'] . " has " . $row['cnt'] . " users\n";
}
?>
