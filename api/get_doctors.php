<?php
require 'db_connect.php';
header('Content-Type: application/json');

$center_id = isset($_GET['center_id']) ? (int)$_GET['center_id'] : 0;

if (!$center_id) {
    echo json_encode(["status" => "error", "message" => "Center ID required"]);
    exit;
}

$sql = "SELECT d.doctor_id, d.specialization, u.first_name, u.last_name,
               a.availability_id, a.date, a.start_time, a.end_time
        FROM doctors d
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN availability a ON d.doctor_id = a.doctor_id AND a.is_booked = FALSE
        WHERE d.center_id = ?
        ORDER BY d.doctor_id, a.date, a.start_time";
$stmt = $pdo->prepare($sql);
$stmt->execute([$center_id]);
$doctors = $stmt->fetchAll();

echo json_encode(["status" => "success", "data" => $doctors]);
?>
