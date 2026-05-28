<?php
require 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');
$centerId = (int)($_GET['center_id'] ?? 0);

// Filter by center if requested
$centerFilterOS = $centerId > 0 ? " AND os.clinic_id = " . $centerId : "";
$centerFilterOT = $centerId > 0 ? " AND os.clinic_id = " . $centerId : "";

$analytics = [];

// 1. Total Sessions (Active / Blocked / Completed)
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM opd_sessions os WHERE session_date = ? $centerFilterOS GROUP BY status");
$stmt->execute([$date]);
$analytics['sessions'] = [
    'active' => 0, 'blocked' => 0, 'completed' => 0, 'total' => 0
];
while ($row = $stmt->fetch()) {
    $analytics['sessions'][$row['status']] = (int)$row['count'];
    $analytics['sessions']['total'] += (int)$row['count'];
}

// 2. Token Breakdown
$stmt = $pdo->prepare("
    SELECT ot.status, ot.attendance_marked, ot.token_type, ot.was_late_request, COUNT(*) as count 
    FROM opd_tokens ot 
    JOIN opd_sessions os ON ot.session_id = os.id 
    WHERE os.session_date = ? $centerFilterOT 
    GROUP BY ot.status, ot.attendance_marked, ot.token_type, ot.was_late_request
");
$stmt->execute([$date]);

$metrics = [
    'present' => 0, 'absent' => 0, 'canceled' => 0, 'done' => 0,
    'late_approved' => 0, 'late_denied' => 0, 'walkin_present' => 0, 'walkin_cancel' => 0,
    'total_booked' => 0
];

while ($row = $stmt->fetch()) {
    $count = (int)$row['count'];
    $metrics['total_booked'] += $count;

    // Walk-ins
    if ($row['token_type'] === 'walk-in') {
        if ($row['status'] === 'cancelled') {
            $metrics['walkin_cancel'] += $count;
        } else {
            $metrics['walkin_present'] += $count;
        }
    }

    // Late Requests
    if ($row['was_late_request'] == 1) {
        if ($row['status'] === 'served' || $row['status'] === 'waiting') {
            $metrics['late_approved'] += $count;
        } else {
            $metrics['late_denied'] += $count;
        }
    }

    // General Status
    if ($row['status'] === 'waiting' && $row['attendance_marked'] == 1) {
        $metrics['present'] += $count;
    } elseif ($row['status'] === 'no-show') {
        $metrics['absent'] += $count;
    } elseif ($row['status'] === 'cancelled') {
        $metrics['canceled'] += $count;
    } elseif ($row['status'] === 'served' || $row['status'] === 'called') {
        $metrics['done'] += $count;
    }
}
$analytics['tokens'] = $metrics;

// Calculate permanently empty
$stmt = $pdo->prepare("SELECT SUM(max_tokens) as total_capacity FROM opd_sessions os WHERE session_date = ? $centerFilterOS");
$stmt->execute([$date]);
$totalCapacity = (int)$stmt->fetchColumn();
$analytics['tokens']['permanently_empty'] = max(0, $totalCapacity - $metrics['total_booked']);
$analytics['total_capacity'] = $totalCapacity;

// 3. Peak Hours (Tokens booked per hour)
$stmt = $pdo->prepare("
    SELECT HOUR(ot.issue_time) as hour, COUNT(*) as count 
    FROM opd_tokens ot 
    JOIN opd_sessions os ON ot.session_id = os.id 
    WHERE os.session_date = ? $centerFilterOT 
    GROUP BY HOUR(ot.issue_time)
    ORDER BY hour ASC
");
$stmt->execute([$date]);
$analytics['peak_hours'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['status' => 'success', 'data' => $analytics]);
?>
