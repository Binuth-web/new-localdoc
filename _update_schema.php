<?php
require 'api/db_connect.php';

try {
    $pdo->exec("ALTER TABLE medical_centers ADD COLUMN online_booking_enabled TINYINT(1) DEFAULT 1");
    echo "Added online_booking_enabled to medical_centers.\n";
} catch (Exception $e) {
    echo "Error (medical_centers): " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE opd_tokens ADD COLUMN was_late_request TINYINT(1) DEFAULT 0");
    echo "Added was_late_request to opd_tokens.\n";
} catch (Exception $e) {
    echo "Error (opd_tokens): " . $e->getMessage() . "\n";
}
?>
