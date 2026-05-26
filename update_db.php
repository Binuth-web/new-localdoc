<?php
require 'api/db_connect.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN dob DATE NULL");
    echo "Column 'dob' added successfully.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') { // Duplicate column
        echo "Column 'dob' already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
