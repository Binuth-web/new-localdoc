<?php
session_name('medconnect_staff');
require 'db_connect.php';
header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || $role !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Admin access required.']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['status' => 'error', 'message' => 'Query must be at least 2 characters.']);
    exit;
}

$like = "%{$q}%";
$results = [];

// 1. Registered Patients
$stmt = $pdo->prepare("
    SELECT id, full_name, email, phone, id_number, date_of_birth, is_active
    FROM users WHERE role='patient' AND email NOT LIKE '%@medconnect.local'
    AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR id_number LIKE ?)
    ORDER BY full_name LIMIT 10
");
$stmt->execute([$like,$like,$like,$like]);
$registered = $stmt->fetchAll();
if ($registered) {
    $results[] = [
        'category' => 'Registered Patients',
        'icon' => 'fa-solid fa-user',
        'color' => '#3b82f6',
        'items' => array_map(function($u) {
            return [
                'id' => $u['id'],
                'title' => $u['full_name'],
                'subtitle' => ($u['email'] ?? '') . ($u['phone'] ? ' | '.$u['phone'] : ''),
                'badge' => $u['is_active'] ? 'Active' : 'Inactive',
                'badge_color' => $u['is_active'] ? '#10b981' : '#ef4444',
                'meta' => $u['id_number'] ? 'NIC: '.$u['id_number'] : '',
                'tab' => 'patients'
            ];
        }, $registered)
    ];
}

// 2. Walk-in Patients
$stmt = $pdo->prepare("
    SELECT id, full_name, email, phone, is_active
    FROM users WHERE role='patient' AND email LIKE '%@medconnect.local'
    AND (full_name LIKE ? OR phone LIKE ?)
    ORDER BY full_name LIMIT 10
");
$stmt->execute([$like,$like]);
$walkin = $stmt->fetchAll();
if ($walkin) {
    $results[] = [
        'category' => 'Walk-in Patients',
        'icon' => 'fa-solid fa-person-walking',
        'color' => '#8b5cf6',
        'items' => array_map(function($u) {
            return [
                'id' => $u['id'],
                'title' => $u['full_name'],
                'subtitle' => $u['phone'] ?: 'No phone',
                'badge' => $u['is_active'] ? 'Active' : 'Inactive',
                'badge_color' => $u['is_active'] ? '#10b981' : '#ef4444',
                'meta' => 'Walk-in',
                'tab' => 'patients'
            ];
        }, $walkin)
    ];
}

// 3. Medical Staff
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.phone, u.is_active,
           mc.name AS center_name
    FROM users u
    LEFT JOIN medical_centers mc ON u.center_id = mc.id
    WHERE u.role IN ('staff','medical_staff','doctor')
    AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR mc.name LIKE ?)
    ORDER BY u.full_name LIMIT 10
");
$stmt->execute([$like,$like,$like,$like]);
$staff = $stmt->fetchAll();
if ($staff) {
    $results[] = [
        'category' => 'Medical Staff',
        'icon' => 'fa-solid fa-user-nurse',
        'color' => '#f59e0b',
        'items' => array_map(function($u) {
            return [
                'id' => $u['id'],
                'title' => $u['full_name'],
                'subtitle' => $u['email'] . ($u['center_name'] ? ' | '.$u['center_name'] : ''),
                'badge' => $u['is_active'] ? 'Active' : 'Inactive',
                'badge_color' => $u['is_active'] ? '#10b981' : '#ef4444',
                'meta' => $u['center_name'] ?: 'No center',
                'tab' => 'staff'
            ];
        }, $staff)
    ];
}

// 4. Active Sessions
$stmt = $pdo->prepare("
    SELECT os.id, os.opd_name, os.doctor_name, os.session_date, os.start_time, os.end_time, os.status,
           mc.name AS center_name
    FROM opd_sessions os
    JOIN medical_centers mc ON os.clinic_id = mc.id
    WHERE (os.opd_name LIKE ? OR os.doctor_name LIKE ? OR mc.name LIKE ? OR os.session_date LIKE ?)
    ORDER BY os.session_date DESC, os.start_time DESC LIMIT 10
");
$stmt->execute([$like,$like,$like,$like]);
$sessions = $stmt->fetchAll();
if ($sessions) {
    $results[] = [
        'category' => 'Sessions',
        'icon' => 'fa-solid fa-calendar-check',
        'color' => '#10b981',
        'items' => array_map(function($s) {
            $statusColors = ['active'=>'#10b981','completed'=>'#64748b','scheduled'=>'#3b82f6','blocked'=>'#ef4444'];
            return [
                'id' => $s['id'],
                'title' => ($s['doctor_name'] ?: $s['opd_name']) . ' — ' . $s['center_name'],
                'subtitle' => $s['session_date'] . ' | ' . substr($s['start_time'],0,5) . '–' . substr($s['end_time'],0,5),
                'badge' => ucfirst($s['status']),
                'badge_color' => $statusColors[$s['status']] ?? '#64748b',
                'meta' => '',
                'tab' => 'sessions'
            ];
        }, $sessions)
    ];
}

// 5. Medical Centers
$stmt = $pdo->prepare("
    SELECT id, name, type, area, phone, available
    FROM medical_centers
    WHERE (name LIKE ? OR type LIKE ? OR area LIKE ? OR phone LIKE ?)
    ORDER BY name LIMIT 10
");
$stmt->execute([$like,$like,$like,$like]);
$centers = $stmt->fetchAll();
if ($centers) {
    $results[] = [
        'category' => 'Medical Centers',
        'icon' => 'fa-solid fa-hospital',
        'color' => '#0f4c81',
        'items' => array_map(function($c) {
            return [
                'id' => $c['id'],
                'title' => $c['name'],
                'subtitle' => $c['type'] . ' | ' . $c['area'],
                'badge' => $c['available'] ? 'Active' : 'Inactive',
                'badge_color' => $c['available'] ? '#10b981' : '#ef4444',
                'meta' => $c['phone'] ?: '',
                'tab' => 'centers'
            ];
        }, $centers)
    ];
}

$total = 0;
foreach ($results as $cat) $total += count($cat['items']);

echo json_encode(['status' => 'success', 'total' => $total, 'results' => $results]);
