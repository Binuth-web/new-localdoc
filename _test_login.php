<?php
require 'api/db_connect.php';

// Add doctor_started and calling_token columns
try {
    $pdo->exec("ALTER TABLE opd_sessions ADD COLUMN doctor_started TINYINT(1) NOT NULL DEFAULT 0");
    echo "Added doctor_started\n";
} catch (PDOException $e) { echo "doctor_started: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE opd_sessions ADD COLUMN calling_token INT(11) NOT NULL DEFAULT 0");
    echo "Added calling_token\n";
} catch (PDOException $e) { echo "calling_token: " . $e->getMessage() . "\n"; }

echo "Done\n";
