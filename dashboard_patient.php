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
        SELECT ot.id AS token_db_id, ot.status, ot.attendance_marked, ot.created_at, ot.token_number,
               os.id AS session_id, os.opd_name AS specialization,
               COALESCE(os.doctor_name, os.opd_name, 'OPD') AS doctor_first,
               os.session_date AS date, os.start_time, os.end_time, os.status AS session_status,
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

// Fetch unread notifications
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$notifStmt->execute([$_SESSION['user_id']]);
$notifications = $notifStmt->fetchAll();

// Map DB status to display label + color
function getDisplayStatus(string $status, int $attendanceMarked): array {
    if ($status === 'waiting' && $attendanceMarked) return ['Present',      '#3b82f6', '#dbeafe'];
    if ($status === 'waiting')                       return ['Pending',      '#f59e0b', '#fef3c7'];
    if ($status === 'served' || $status === 'called') return ['Done',        '#10b981', '#dcfce7'];
    if ($status === 'no-show')                        return ['Absent',      '#ef4444', '#fee2e2'];
    if ($status === 'cancelled')                      return ['Cancelled',   '#94a3b8', '#f1f5f9'];
    if ($status === 'late_request')                   return ['Late Request','#f97316', '#ffedd5'];
    return [ucfirst($status), '#64748b', '#f8fafc'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | MedConnect</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notif-banner {
            background: #fff7ed; border: 1px solid #fed7aa;
            border-radius: 10px; margin-bottom: 1.5rem; overflow: hidden;
        }
        .notif-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.75rem 1.25rem;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
        }
        .notif-header h4 { margin: 0; font-size: 0.95rem; }
        .notif-dismiss-all {
            background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4);
            color: white; font-size: 0.8rem; padding: 0.25rem 0.75rem;
            border-radius: 5px; cursor: pointer;
        }
        .notif-item-row {
            padding: 0.75rem 1.25rem; border-bottom: 1px solid #fed7aa;
            display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;
        }
        .notif-item-row:last-child { border-bottom: none; }
        .notif-msg { font-size: 0.875rem; color: #431407; line-height: 1.5; flex: 1; }
        .notif-time { font-size: 0.75rem; color: #9a3412; white-space: nowrap; }
        .btn-late-req {
            background: #f97316; color: white; border: none;
            padding: 0.4rem 0.9rem; border-radius: 6px; font-size: 0.82rem;
            font-weight: 600; cursor: pointer; margin-top: 0.5rem;
        }
        .btn-late-req:hover { background: #ea580c; }
        .btn-cancel {
            background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;
            padding: 0.4rem 0.9rem; border-radius: 6px; font-size: 0.82rem;
            font-weight: 600; cursor: pointer;
        }
        .btn-cancel:hover { background: #fca5a5; }
        .apt-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            background: white;
            transition: box-shadow 0.2s;
        }
        .apt-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        .token-pill {
            font-size: 1.4rem; font-weight: 800; letter-spacing: 0.05em;
            color: #0f172a; margin-bottom: 0.1rem;
        }
        .toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem;
            background: #1e293b; color: white; padding: 0.75rem 1.25rem;
            border-radius: 8px; font-size: 0.9rem; z-index: 999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transform: translateY(80px); opacity: 0; transition: all 0.3s;
            max-width: 320px;
        }
        .toast.show { transform: translateY(0); opacity: 1; }
    </style>
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

    <div style="max-width: 900px; margin: 0 auto;">

        <?php if (count($notifications) > 0): ?>
        <div class="notif-banner" id="notifBanner">
            <div class="notif-header">
                <h4><i class="fa-solid fa-bell"></i> Notifications (<?php echo count($notifications); ?>)</h4>
                <button class="notif-dismiss-all" onclick="dismissAll()">Dismiss All</button>
            </div>
            <?php foreach ($notifications as $n): ?>
            <div class="notif-item-row" id="notif-<?php echo $n['id']; ?>">
                <div class="notif-msg">
                    <?php echo nl2br(htmlspecialchars($n['message'])); ?>
                    <?php
                    // Show request late token button for any action notification with a token_id
                    if ($n['type'] === 'action' && $n['token_id']):
                    ?>
                        <br>
                        <button class="btn-late-req" onclick="requestLate(<?php echo (int)$n['token_id']; ?>, <?php echo (int)$n['id']; ?>)">
                            <i class="fa-solid fa-clock"></i> Request Late Token
                        </button>
                    <?php endif; ?>
                </div>
                <div class="notif-time"><?php echo date('d M, H:i', strtotime($n['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.07);">
            <h2 style="color: var(--secondary); margin-bottom: 1.5rem;">
                <i class="fa-solid fa-list-check"></i> Your Appointments
            </h2>

            <?php if (count($appointments) > 0): ?>
                <div style="display: grid; gap: 1rem;">
                    <?php foreach ($appointments as $apt):
                        [$label, $color, $bg] = getDisplayStatus($apt['status'], (int)$apt['attendance_marked']);
                        $tokenNum = str_replace('OPD-', '', $apt['token_number'] ?? '');
                        $canCancel = in_array($apt['status'], ['waiting']) && !$apt['attendance_marked'];
                        $canLateReq = in_array($apt['status'], ['waiting', 'no-show']) && !$apt['attendance_marked'] && $apt['session_status'] === 'active';
                    ?>
                        <div class="apt-card">
                            <div style="flex:1;">
                                <?php if ($tokenNum): ?>
                                    <div class="token-pill">Token <?php echo htmlspecialchars($tokenNum); ?></div>
                                <?php endif; ?>
                                <h4 style="color: var(--primary); margin: 0.1rem 0;">
                                    <?php echo htmlspecialchars($apt['doctor_first']); ?>
                                </h4>
                                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0.25rem 0;">
                                    <i class="fa-solid fa-hospital"></i> <?php echo htmlspecialchars($apt['center_name']); ?>
                                </p>
                                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                                    <i class="fa-solid fa-calendar"></i> <?php echo htmlspecialchars($apt['date']); ?>
                                    &nbsp;|&nbsp; <i class="fa-solid fa-clock"></i>
                                    <?php echo substr($apt['start_time'],0,5); ?> – <?php echo substr($apt['end_time'],0,5); ?>
                                </p>
                                <div style="margin-top: 0.6rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                                    <?php if ($canCancel): ?>
                                        <button class="btn-cancel" onclick="cancelToken(<?php echo $apt['token_db_id']; ?>)">
                                            <i class="fa-solid fa-xmark"></i> Cancel Booking
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canLateReq): ?>
                                        <button class="btn-late-req" onclick="requestLate(<?php echo $apt['token_db_id']; ?>, null)">
                                            <i class="fa-solid fa-clock"></i> Request Late Token
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <span style="
                                    display:inline-block; padding: 0.3rem 0.9rem; border-radius: 20px;
                                    background:<?php echo $bg; ?>; color:<?php echo $color; ?>;
                                    font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;
                                ">
                                    <?php echo htmlspecialchars($label); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted);">You have no appointments yet.</p>
                <a href="index.html#centers-section" class="btn-primary" style="display:inline-block;margin-top:1rem;text-decoration:none;">Book an appointment</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        function showToast(msg, type = 'success') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'toast show';
            t.style.borderLeft = type === 'success' ? '4px solid #10b981' : '4px solid #ef4444';
            setTimeout(() => { t.className = 'toast'; }, 4000);
        }

        function cancelToken(tokenId) {
            if (!confirm('Are you sure you want to cancel this booking?\nA new slot will open for other patients.')) return;
            const fd = new FormData();
            fd.append('token_id', tokenId);
            fetch('api/cancel_token.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.status);
                    if (data.status === 'success') setTimeout(() => location.reload(), 2000);
                });
        }

        function requestLate(tokenId, notifId) {
            if (!confirm('Request a late token?\nThe medical staff will be notified. They will decide if a late token can be given.')) return;
            const fd = new FormData();
            fd.append('token_id', tokenId);
            fetch('api/request_late_token.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.status);
                    if (data.status === 'success' && notifId) {
                        dismissOne(notifId);
                    }
                    if (data.status === 'success') setTimeout(() => location.reload(), 3000);
                });
        }

        function dismissOne(notifId) {
            const fd = new FormData();
            fd.append('notification_id', notifId);
            fetch('api/dismiss_notification.php', { method: 'POST', body: fd });
            const el = document.getElementById('notif-' + notifId);
            if (el) el.remove();
        }

        function dismissAll() {
            const fd = new FormData();
            fetch('api/dismiss_notification.php', { method: 'POST', body: fd })
                .then(() => {
                    document.getElementById('notifBanner').remove();
                });
        }
    </script>
</body>
</html>
