<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'medical_staff') {
    header('Location: login.html?role=medical_staff');
    exit;
}
require 'api/db_connect.php';
require 'api/helpers.php';

$center_id = $_SESSION['center_id'] ?? null;
if ($center_id) {
    $centersStmt = $pdo->prepare("SELECT id, name FROM medical_centers WHERE id = ?");
    $centersStmt->execute([$center_id]);
} else {
    $centersStmt = $pdo->query("SELECT id, name FROM medical_centers");
}
$centers = $centersStmt->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_session') {
    $clinic_id   = (int)$_POST['clinic_id'];
    $doctor_name = trim($_POST['doctor_name']);
    $session_date = $_POST['session_date'];
    $start_time  = $_POST['start_time'];
    $end_time    = $_POST['end_time'];
    $max_tokens  = (int)$_POST['max_tokens'];

    $stmt = $pdo->prepare("INSERT INTO opd_sessions (clinic_id, opd_name, doctor_name, session_date, start_time, end_time, max_tokens, current_token, is_active) VALUES (?, 'General OPD', ?, ?, ?, ?, ?, 0, 1)");
    $stmt->execute([$clinic_id, $doctor_name, $session_date, $start_time, $end_time, $max_tokens]);
    header("Location: dashboard_staff.php?success=1");
    exit;
}

if ($center_id) {
    $sessionsStmt = $pdo->prepare("
        SELECT os.*, mc.name as center_name,
               (SELECT COUNT(*) FROM opd_tokens WHERE session_id = os.id AND status NOT IN ('cancelled','no-show')) AS booked_count,
               (SELECT COUNT(*) FROM opd_tokens WHERE session_id = os.id AND status = 'late_request') AS late_count
        FROM opd_sessions os
        JOIN medical_centers mc ON os.clinic_id = mc.id
        WHERE os.clinic_id = ?
        ORDER BY os.session_date DESC, os.start_time DESC
    ");
    $sessionsStmt->execute([$center_id]);
} else {
    $sessionsStmt = $pdo->query("
        SELECT os.*, mc.name as center_name,
               (SELECT COUNT(*) FROM opd_tokens WHERE session_id = os.id AND status NOT IN ('cancelled','no-show')) AS booked_count,
               (SELECT COUNT(*) FROM opd_tokens WHERE session_id = os.id AND status = 'late_request') AS late_count
        FROM opd_sessions os
        JOIN medical_centers mc ON os.clinic_id = mc.id
        ORDER BY os.session_date DESC, os.start_time DESC
    ");
}
$sessions = $sessionsStmt->fetchAll();

// Fetch staff notifications
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
$notifStmt->execute([$_SESSION['user_id']]);
$notifications = $notifStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard | MedConnect</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .staff-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }
        .staff-card { background: white; padding: 2rem; border-radius: 10px; box-shadow: var(--shadow); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
        @media (max-width: 768px) { .staff-grid { grid-template-columns: 1fr; } }

        .late-badge {
            display: inline-flex; align-items: center; gap: 4px;
            background: #ffedd5; color: #c2410c;
            font-size: 0.72rem; font-weight: 700;
            padding: 2px 7px; border-radius: 20px;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }

        .notif-strip {
            background: linear-gradient(135deg, #fff7ed, #fef3c7);
            border: 1px solid #fde68a;
            border-radius: 8px; padding: 0.75rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .notif-strip-header {
            font-weight: 700; color: #92400e; font-size: 0.9rem; margin-bottom: 0.5rem;
            display: flex; justify-content: space-between;
        }
        .notif-strip-item {
            font-size: 0.83rem; color: #78350f;
            padding: 0.4rem 0; border-bottom: 1px dashed #fde68a;
            line-height: 1.4;
        }
        .notif-strip-item:last-child { border-bottom: none; }
        .btn-dismiss-notifs {
            background: #f59e0b; color: white; border: none;
            padding: 0.25rem 0.75rem; border-radius: 5px;
            font-size: 0.78rem; cursor: pointer;
        }
        .manage-btn {
            display: inline-flex; align-items: center; gap: 5px;
            background: #dbeafe; color: #1d4ed8;
            padding: 0.3rem 0.75rem; border-radius: 6px;
            text-decoration: none; font-size: 0.8rem; font-weight: 600;
            transition: background 0.2s;
        }
        .manage-btn:hover { background: #bfdbfe; }
    </style>
</head>
<body style="padding: 2rem; background: #f8fafc;">
    <div style="max-width: 1200px; margin: 0 auto;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; background: white; padding: 1rem 2rem; border-radius: 10px; box-shadow: var(--shadow);">
            <h1 style="color: var(--primary); margin: 0;"><i class="fa-solid fa-user-doctor"></i> Medical Staff Dashboard</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-weight: 600;">Hello, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="api/logout.php" class="btn-outline" style="text-decoration:none; padding: 0.5rem 1rem;">Logout</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                <i class="fa-solid fa-circle-check"></i> Session added successfully.
            </div>
        <?php endif; ?>

        <?php if (count($notifications) > 0): ?>
        <div class="notif-strip" id="staffNotifStrip">
            <div class="notif-strip-header">
                <span><i class="fa-solid fa-bell"></i> Notifications (<?php echo count($notifications); ?>)</span>
                <button class="btn-dismiss-notifs" onclick="dismissAllStaffNotifs()">Dismiss All</button>
            </div>
            <?php foreach ($notifications as $n): ?>
            <div class="notif-strip-item"><?php echo htmlspecialchars($n['message']); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="staff-grid">
            <div class="staff-card">
                <h3 style="color: var(--secondary); margin-top: 0;"><i class="fa-solid fa-plus"></i> Add Doctor & Session</h3>
                <form method="POST" style="margin-top: 1rem; display: flex; flex-direction: column; gap: 1rem;">
                    <input type="hidden" name="action" value="add_session">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Medical Center</label>
                        <select name="clinic_id" class="form-control" required>
                            <?php foreach ($centers as $id => $name): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Doctor Name</label>
                        <input type="text" name="doctor_name" class="form-control" required placeholder="e.g. Dr. John Doe">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Session Date</label>
                        <input type="date" name="session_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Max Patients (Tokens)</label>
                        <input type="number" name="max_tokens" class="form-control" required value="20" min="1">
                    </div>
                    <button type="submit" class="btn-primary" style="margin-top: 0.5rem;">Save Session</button>
                </form>
            </div>

            <div class="staff-card" style="overflow-x: auto;">
                <h3 style="color: var(--secondary); margin-top: 0;"><i class="fa-solid fa-list-ul"></i> Scheduled Sessions</h3>
                <table>
                    <thead>
                        <tr style="background: var(--bg-color);">
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Center</th>
                            <th>Time</th>
                            <th>Tokens</th>
                            <th>Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['session_date']); ?></td>
                            <td style="font-weight: 500; color: var(--primary);"><?php echo htmlspecialchars($s['doctor_name'] ?? 'N/A'); ?></td>
                            <td><i class="fa-solid fa-hospital" style="color: #94a3b8; font-size: 0.8rem;"></i> <?php echo htmlspecialchars($s['center_name']); ?></td>
                            <td><?php echo substr($s['start_time'],0,5) . ' - ' . substr($s['end_time'],0,5); ?></td>
                            <td>
                                <span style="background: #e0f2fe; color: #0284c7; padding: 2px 8px; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">
                                    <?php echo $s['booked_count'] . '/' . $s['max_tokens']; ?>
                                </span>
                                <?php if ($s['late_count'] > 0): ?>
                                    <span class="late-badge"><i class="fa-solid fa-clock"></i> <?php echo $s['late_count']; ?> Late</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="session_view.php?id=<?php echo $s['id']; ?>" class="manage-btn">
                                    <i class="fa-solid fa-clipboard-list"></i> Manage
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($sessions) === 0): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No sessions scheduled.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function dismissAllStaffNotifs() {
            const fd = new FormData();
            fetch('api/dismiss_notification.php', { method: 'POST', body: fd })
                .then(() => document.getElementById('staffNotifStrip').remove());
        }
    </script>
</body>
</html>
