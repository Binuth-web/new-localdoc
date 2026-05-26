<?php
/**
 * medconnect_db is managed by the MedConnect Kandy schema.
 * Run migrations from: medconnect-kandy/php-backend/database/
 *
 * This page only verifies the connection.
 */
require 'api/db_connect.php';
require 'api/helpers.php';

echo '<h2>medconnect_db connection</h2>';
echo '<p style="color:green;">Connected successfully.</p>';

$count = $pdo->query('SELECT COUNT(*) FROM medical_centers')->fetchColumn();
echo '<p>Medical centers in database: <strong>' . (int) $count . '</strong></p>';

if (hasOpdTables($pdo)) {
    $sessions = $pdo->query('SELECT COUNT(*) FROM opd_sessions')->fetchColumn();
    echo '<p>OPD sessions: <strong>' . (int) $sessions . '</strong></p>';
} else {
    echo '<p style="color:#b45309;">OPD tables not found. Import <code>migration_opd_tokens.sql</code> from medconnect-kandy.</p>';
}

echo '<p><a href="index.html">Back to MedConnect</a></p>';
