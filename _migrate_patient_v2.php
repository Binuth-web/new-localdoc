<?php
require 'api/db_connect.php';

$steps = [];

// 1. Add id_number column (unique NIC/passport identifier for patients)
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN id_number VARCHAR(20) NULL DEFAULT NULL");
    $steps[] = "✅ Added id_number column.";
} catch (Exception $e) {
    $steps[] = "ℹ️ id_number: " . $e->getMessage();
}

// 2. Add date_of_birth column
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN date_of_birth DATE NULL DEFAULT NULL");
    $steps[] = "✅ Added date_of_birth column.";
} catch (Exception $e) {
    $steps[] = "ℹ️ date_of_birth: " . $e->getMessage();
}

// 3. Add UNIQUE index on id_number (only for non-null values — partial via application logic)
try {
    $pdo->exec("CREATE UNIQUE INDEX idx_users_id_number ON users (id_number)");
    $steps[] = "✅ Added unique index on id_number.";
} catch (Exception $e) {
    $steps[] = "ℹ️ index: " . $e->getMessage();
}

foreach ($steps as $s) {
    echo $s . "<br>\n";
}
echo "<br><strong>Migration complete. You can delete this file.</strong>";
?>
