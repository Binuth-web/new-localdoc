<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.html?role=patient&redirect=dashboard_patient.php');
    exit;
}
require 'api/db_connect.php';
require 'api/helpers.php';

$appointments = [];
if (hasOpdTables($pdo)) {
    $stmt = $pdo->prepare("
        SELECT ot.id AS appointment_id, ot.status, ot.created_at, ot.token_number,
               os.opd_name AS specialization,
               COALESCE(os.doctor_name, os.opd_name, 'OPD') AS doctor_first,
               '' AS doctor_last,
               os.session_date AS date, os.start_time, os.end_time,
               mc.name AS center_name, mc.address
        FROM opd_tokens ot
        JOIN opd_sessions os ON ot.session_id = os.id
        JOIN medical_centers mc ON os.clinic_id = mc.id
        WHERE ot.patient_id = ?
        ORDER BY os.session_date DESC, os.start_time DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $appointments = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Appointments | MedConnect</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="padding: 2rem; background: #f8fafc;">

    <nav class="navbar" style="margin: -2rem -2rem 2rem -2rem;">
        <a href="index.html" class="navbar-brand">
            <i class="fa-solid fa-heart-pulse"></i> MedConnect
        </a>
        <div class="navbar-nav">
            <a href="index.html#centers-section">Get Appointment</a>
            <span style="font-weight: 600; color: var(--primary);">My Appointments</span>
            <a href="profile.php">My Profile</a>
            <span style="font-weight: 600; margin-left: 1rem;">Hello, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="api/logout.php" class="btn-outline" style="padding: 0.4rem 1rem; border-radius: 4px; text-decoration: none; margin-left: 1rem;">Logout</a>
        </div>
    </nav>

    <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 900px; margin: 0 auto; box-shadow: var(--shadow-lg);">
        <h2 style="color: var(--secondary); margin-bottom: 1.5rem;">Your Appointments</h2>

        <?php if (count($appointments) > 0): ?>
            <div style="display: grid; gap: 1rem;">
                <?php foreach ($appointments as $apt): ?>
                    <div style="border: 1px solid var(--border); padding: 1rem; border-radius: var(--radius); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h4 style="color: var(--primary); margin: 0;">
                                <?php if (!empty($apt['token_number'])): ?>
                                    Token <?php echo htmlspecialchars($apt['token_number']); ?> —
                                <?php endif; ?>
                                <?php echo htmlspecialchars($apt['doctor_first']); ?>
                                (<?php echo htmlspecialchars($apt['specialization']); ?>)
                            </h4>
                            <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0.25rem 0;">
                                <i class="fa-solid fa-hospital"></i> <?php echo htmlspecialchars($apt['center_name']); ?>
                            </p>
                            <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                                <i class="fa-solid fa-calendar"></i> <?php echo htmlspecialchars($apt['date']); ?>
                                | <i class="fa-solid fa-clock"></i>
                                <?php echo htmlspecialchars(substr($apt['start_time'], 0, 5)); ?> –
                                <?php echo htmlspecialchars(substr($apt['end_time'], 0, 5)); ?>
                            </p>
                        </div>
                        <span class="distance-badge" style="background: #e0f2fe; color: #0284c7;">
                            <?php echo ucfirst(htmlspecialchars($apt['status'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted);">You have no appointments yet.</p>
            <a href="index.html#centers-section" class="btn-primary" style="display: inline-block; margin-top: 1rem; text-decoration: none;">Book an appointment</a>
        <?php endif; ?>
    </div>
</body>
</html>
