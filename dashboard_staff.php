<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'medical_staff') {
    header('Location: login.html?role=medical_staff');
    exit;
}
require 'api/db_connect.php';
require 'api/helpers.php';

// Fetch staff center
$center_id = $_SESSION['center_id'] ?? null;
if ($center_id) {
    $centersStmt = $pdo->prepare("SELECT id, name FROM medical_centers WHERE id = ?");
    $centersStmt->execute([$center_id]);
} else {
    $centersStmt = $pdo->query("SELECT id, name FROM medical_centers");
}
$centers = $centersStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle new session submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_session') {
    $clinic_id = (int)$_POST['clinic_id'];
    $doctor_name = trim($_POST['doctor_name']);
    $specialization = 'General OPD';
    $session_date = $_POST['session_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $max_tokens = (int)$_POST['max_tokens'];

    $stmt = $pdo->prepare("INSERT INTO opd_sessions (clinic_id, opd_name, doctor_name, session_date, start_time, end_time, max_tokens, current_token, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)");
    $stmt->execute([$clinic_id, $specialization, $doctor_name, $session_date, $start_time, $end_time, $max_tokens]);
    header("Location: dashboard_staff.php?success=1");
    exit;
}

// Fetch sessions
if ($center_id) {
    $sessionsStmt = $pdo->prepare("
        SELECT os.*, mc.name as center_name 
        FROM opd_sessions os 
        JOIN medical_centers mc ON os.clinic_id = mc.id 
        WHERE os.clinic_id = ?
        ORDER BY os.session_date DESC, os.start_time DESC
    ");
    $sessionsStmt->execute([$center_id]);
} else {
    $sessionsStmt = $pdo->query("
        SELECT os.*, mc.name as center_name 
        FROM opd_sessions os 
        JOIN medical_centers mc ON os.clinic_id = mc.id 
        ORDER BY os.session_date DESC, os.start_time DESC
    ");
}
$sessions = $sessionsStmt->fetchAll();
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
        .staff-card { background: white; padding: 2rem; border-radius: 8px; box-shadow: var(--shadow); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
        @media (max-width: 768px) {
            .staff-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body style="padding: 2rem; background: #f8fafc;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: white; padding: 1rem 2rem; border-radius: 8px; box-shadow: var(--shadow);">
            <h1 style="color: var(--primary); margin: 0;"><i class="fa-solid fa-user-doctor"></i> Medical Staff Dashboard</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-weight: 600;">Hello, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="api/logout.php" class="btn-outline" style="text-decoration:none; padding: 0.5rem 1rem;">Logout</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                <i class="fa-solid fa-circle-check"></i> Session and doctor added successfully.
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
                <h3 style="color: var(--secondary); margin-top: 0;"><i class="fa-solid fa-list-ul"></i> Scheduled Doctors & Sessions</h3>
                <table>
                    <thead>
                        <tr style="background: var(--bg-color);">
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Center</th>
                            <th>Time</th>
                            <th>Tokens</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['session_date']); ?></td>
                            <td style="font-weight: 500; color: var(--primary);"><?php echo htmlspecialchars($s['doctor_name'] ?? 'N/A'); ?></td>
                            <td><i class="fa-solid fa-hospital" style="color: #94a3b8; font-size: 0.8rem;"></i> <?php echo htmlspecialchars($s['center_name']); ?></td>
                            <td><?php echo substr($s['start_time'], 0, 5) . ' - ' . substr($s['end_time'], 0, 5); ?></td>
                            <td>
                                <span style="background: #e0f2fe; color: #0284c7; padding: 2px 8px; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">
                                    <?php echo $s['current_token'] . '/' . $s['max_tokens']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($sessions) === 0): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No sessions scheduled.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
