<?php
require 'api/db_connect.php';
try {
    $pdo->exec("ALTER TABLE opd_sessions ADD COLUMN status ENUM('active', 'blocked', 'completed') DEFAULT 'active' AFTER is_active");
    // Migrate existing data based on is_active
    $pdo->exec("UPDATE opd_sessions SET status = 'blocked' WHERE is_active = 0");
    echo "Added status column.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
