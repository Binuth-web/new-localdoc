<?php
require 'db_connect.php';
require 'helpers.php';
header('Content-Type: application/json');

$centerId = isset($_GET['center_id']) ? (int) $_GET['center_id'] : 0;
if (!$centerId) {
    echo json_encode(['status' => 'error', 'message' => 'Center ID required']);
    exit;
}

$rows = [];

if (hasOpdTables($pdo)) {
    $sql = "SELECT os.id AS availability_id,
                   os.id AS doctor_id,
                   os.opd_name AS specialization,
                   COALESCE(os.doctor_name, os.opd_name, 'OPD') AS first_name,
                   '' AS last_name,
                   os.session_date AS date,
                   os.start_time,
                   os.end_time
            FROM opd_sessions os
            WHERE os.clinic_id = ?
              AND os.status = 'active'
              AND os.session_date >= CURDATE()
              AND os.current_token < os.max_tokens
            ORDER BY os.session_date, os.start_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$centerId]);
    $rows = $stmt->fetchAll();
}

echo json_encode(['status' => 'success', 'data' => $rows]);
