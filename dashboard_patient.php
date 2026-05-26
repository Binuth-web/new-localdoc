<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.html?role=patient");
    exit;
}
require 'api/db_connect.php';

// Fetch patient's appointments
$stmt = $pdo->prepare("
    SELECT a.appointment_id, a.status, a.created_at,
           d.specialization, u.first_name as doctor_first, u.last_name as doctor_last,
           av.date, av.start_time, av.end_time,
           c.name as center_name, c.address
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN users u ON d.user_id = u.user_id
    JOIN availability av ON a.availability_id = av.availability_id
    JOIN medical_centers c ON d.center_id = c.center_id
    WHERE a.patient_id = ?
    ORDER BY av.date DESC, av.start_time DESC
");
$stmt->execute([$_SESSION['user_id']]);
$appointments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Dashboard | MediConnect</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="padding: 2rem; background: #f8fafc;">
    
    <nav class="navbar" style="margin: -2rem -2rem 2rem -2rem;">
        <a href="index.html" class="navbar-brand">
            <i class="fa-solid fa-heart-pulse"></i> MediConnect
        </a>
        <div class="navbar-nav">
            <span style="font-weight: 600; margin-right: 1rem;">Hello, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="api/logout.php" class="btn-outline" style="padding: 0.4rem 1rem; border-radius: 4px; text-decoration: none;">Logout</a>
        </div>
    </nav>

    <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 900px; margin: 0 auto; box-shadow: var(--shadow-lg);">
        <h2 style="color: var(--secondary); margin-bottom: 1.5rem;">Your Appointments</h2>
        
        <?php if(count($appointments) > 0): ?>
            <div style="display: grid; gap: 1rem;">
                <?php foreach($appointments as $apt): ?>
                    <div style="border: 1px solid var(--border); padding: 1rem; border-radius: var(--radius); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h4 style="color: var(--primary);">Dr. <?php echo htmlspecialchars($apt['doctor_first'] . ' ' . $apt['doctor_last']); ?> (<?php echo htmlspecialchars($apt['specialization']); ?>)</h4>
                            <p style="font-size: 0.9rem; color: var(--text-muted);"><i class="fa-solid fa-hospital"></i> <?php echo htmlspecialchars($apt['center_name']); ?></p>
                            <p style="font-size: 0.9rem; color: var(--text-muted);"><i class="fa-solid fa-calendar"></i> <?php echo htmlspecialchars($apt['date']); ?> | <i class="fa-solid fa-clock"></i> <?php echo htmlspecialchars($apt['start_time']); ?> - <?php echo htmlspecialchars($apt['end_time']); ?></p>
                        </div>
                        <div>
                            <span class="distance-badge" style="background: <?php echo $apt['status'] === 'scheduled' ? '#e0f2fe' : '#f1f5f9'; ?>; color: <?php echo $apt['status'] === 'scheduled' ? '#0284c7' : '#64748b'; ?>;">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted);">You have no upcoming appointments.</p>
            <a href="index.html" class="btn-primary" style="display: inline-block; margin-top: 1rem; text-decoration: none;">Find a Doctor</a>
        <?php endif; ?>
    </div>
</body>
</html>
