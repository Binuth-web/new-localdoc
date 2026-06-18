<?php
session_name('medconnect_patient');
require 'api/db_connect.php';
require 'api/helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.html?role=patient&redirect=dashboard_patient.php');
    exit;
}

// Live is_active check — admin may have deactivated the account after login
$_activeCheck = $pdo->prepare('SELECT is_active FROM users WHERE id = ?');
$_activeCheck->execute([$_SESSION['user_id']]);
$_activeUser = $_activeCheck->fetch();
if (!$_activeUser || (int)$_activeUser['is_active'] === 0) {
    session_unset();
    session_destroy();
    header('Location: login.html?error=deactivated');
    exit;
}

$appointments = [];
if (hasOpdTables($pdo)) {
    $stmt = $pdo->prepare("
        SELECT ot.id AS token_db_id, ot.status, ot.attendance_marked, ot.was_late_request, ot.created_at, ot.token_number,
               os.id AS session_id, os.opd_name AS specialization,
               COALESCE(os.doctor_name, os.opd_name, 'OPD') AS doctor_first,
               os.session_date AS date, os.start_time, os.end_time, os.status AS session_status,
               os.doctor_started, os.calling_token,
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

// Fetch unread notifications (hide if the associated token is already completed)
$notifStmt = $pdo->prepare("
    SELECT n.* 
    FROM notifications n
    LEFT JOIN opd_tokens ot ON n.token_id = ot.id
    WHERE n.user_id = ? AND n.is_read = 0 
      AND (n.token_id IS NULL OR ot.status NOT IN ('served', 'called'))
    ORDER BY n.created_at DESC
");
$notifStmt->execute([$_SESSION['user_id']]);
$notifications = $notifStmt->fetchAll();

// Separate appointments into pending and completed
$pendingApts = [];
$completedApts = [];
foreach ($appointments as $apt) {
    if (in_array($apt['status'], ['waiting', 'late_request'])) {
        $pendingApts[] = $apt;
    } else {
        $completedApts[] = $apt;
    }
}

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
        /* --- Premium Design Enhancements --- */
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f1f5f9; /* sleek, cool background */
            color: #1e293b;
        }
        
        .notif-banner {
            background: linear-gradient(to right, #fff7ed, #ffedd5);
            border: 1px solid #fed7aa;
            border-radius: 12px; margin-bottom: 2rem; overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(249, 115, 22, 0.1);
        }
        .notif-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .notif-header h4 { margin: 0; font-size: 1rem; font-weight: 700; letter-spacing: 0.02em; }
        .notif-dismiss-all {
            background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4);
            color: white; font-size: 0.8rem; padding: 0.35rem 1rem;
            border-radius: 20px; cursor: pointer; transition: all 0.2s;
            font-weight: 600;
        }
        .notif-dismiss-all:hover { background: rgba(255,255,255,0.3); transform: translateY(-1px); }
        .notif-item-row {
            padding: 1rem 1.5rem; border-bottom: 1px solid #fed7aa;
            display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;
            transition: background 0.2s;
        }
        .notif-item-row:hover { background: rgba(255,255,255,0.4); }
        .notif-item-row:last-child { border-bottom: none; }
        .notif-msg { font-size: 0.9rem; color: #431407; line-height: 1.6; flex: 1; }
        .notif-time { font-size: 0.75rem; color: #9a3412; white-space: nowrap; font-weight: 600; background: #ffedd5; padding: 0.2rem 0.6rem; border-radius: 12px; }
        
        .btn-late-req {
            background: linear-gradient(135deg, #f97316, #ea580c); color: white; border: none;
            padding: 0.5rem 1.2rem; border-radius: 8px; font-size: 0.85rem;
            font-weight: 600; cursor: pointer; margin-top: 0.75rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 10px rgba(234, 88, 12, 0.2);
        }
        .btn-late-req:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(234, 88, 12, 0.3); }
        
        .btn-cancel {
            background: white; color: #ef4444; border: 1px solid #fecaca;
            padding: 0.5rem 1.2rem; border-radius: 8px; font-size: 0.85rem;
            font-weight: 600; cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.05);
        }
        .btn-cancel:hover { background: #fef2f2; border-color: #fca5a5; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(239, 68, 68, 0.1); }
        
        .apt-card {
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1.5rem;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .apt-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
            background: var(--primary); opacity: 0; transition: opacity 0.3s;
        }
        .apt-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08); 
            background: white;
            border-color: #cbd5e1;
        }
        .apt-card:hover::before { opacity: 1; }
        
        .token-pill {
            font-size: 1.75rem; font-weight: 900; letter-spacing: 0.02em;
            color: #0f172a; margin-bottom: 0.25rem;
            background: linear-gradient(135deg, #1e293b, #334155);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Live session indicator */
        .live-session-bar {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white; border-radius: 12px;
            padding: 0.85rem 1.25rem; margin-top: 1.25rem;
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
            box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .live-dot {
            width: 12px; height: 12px; border-radius: 50%;
            background: #10b981; display: inline-block;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.6);
            animation: pulse-glow 1.5s infinite;
        }
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); transform: scale(1); }
            50% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); transform: scale(0.9); }
        }
        .live-label { font-size: 0.75rem; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 2px; }
        .live-token-num { font-size: 1.4rem; font-weight: 900; color: #34d399; letter-spacing: 0.1em; text-shadow: 0 0 15px rgba(52, 211, 153, 0.3); }
        .your-token-pill {
            background: rgba(255,255,255,0.1); font-size: 0.85rem; font-weight: 600;
            padding: 0.4rem 0.8rem; border-radius: 20px; color: white;
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .toast {
            position: fixed; bottom: 2rem; right: 2rem;
            background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(8px);
            color: white; padding: 1rem 1.5rem;
            border-radius: 12px; font-size: 0.95rem; font-weight: 500; z-index: 999;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1);
            transform: translateY(100px); opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-width: 350px; display: flex; align-items: center; gap: 0.75rem;
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        
        .section-box {
            background: white; padding: 2.5rem; border-radius: 20px; 
            box-shadow: 0 4px 25px rgba(0,0,0,0.03); margin-bottom: 2rem;
        }
        .section-title {
            color: var(--secondary); margin-bottom: 1.75rem; font-size: 1.4rem; font-weight: 800;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .section-title i {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
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
            <a href="api/logout.php?portal=patient" class="btn-outline" style="padding: 0.4rem 1rem; border-radius: 4px; text-decoration: none; margin-left: 1rem;">Logout</a>
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

        <div class="section-box">
            <h2 class="section-title">
                <i class="fa-solid fa-list-check"></i> Your Pending Appointments
            </h2>

            <?php if (count($pendingApts) > 0): ?>
                <div style="display: grid; gap: 1rem;">
                    <?php foreach ($pendingApts as $apt):
                        [$label, $color, $bg] = getDisplayStatus($apt['status'], (int)$apt['attendance_marked']);
                        $tokenNum = str_replace('OPD-', '', $apt['token_number'] ?? '');
                        $canCancel = in_array($apt['status'], ['waiting']) && (!$apt['attendance_marked'] || $apt['was_late_request'] == 1);
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
                                <?php if ($apt['doctor_started'] && $apt['session_status'] === 'active' && !in_array($apt['status'], ['cancelled', 'served'])): ?>
                                <div class="live-session-bar">
                                    <span class="live-dot"></span>
                                    <div>
                                        <div class="live-label">Session Live — Now Serving</div>
                                        <div class="live-token-num">
                                            <?php
                                            $callingNum = (int)$apt['calling_token'];
                                            echo $callingNum > 0
                                                ? 'OPD-' . str_pad($callingNum, 3, '0', STR_PAD_LEFT)
                                                : '—';
                                            ?>
                                        </div>
                                    </div>
                                    <span class="your-token-pill">Your Token: <?php echo htmlspecialchars(str_replace('OPD-','',$apt['token_number']??'')); ?></span>
                                </div>
                                <?php endif; ?>
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
                <p style="color: var(--text-muted);">You have no pending appointments.</p>
                <a href="index.html#centers-section" class="btn-primary" style="display:inline-block;margin-top:1rem;text-decoration:none;">Book an appointment</a>
            <?php endif; ?>
        </div>

        <?php if (count($completedApts) > 0): ?>
        <div class="section-box">
            <h2 class="section-title">
                <i class="fa-solid fa-clock-rotate-left"></i> Past / Completed Appointments
            </h2>

            <div style="display: grid; gap: 1rem;">
                <?php foreach ($completedApts as $apt):
                    [$label, $color, $bg] = getDisplayStatus($apt['status'], (int)$apt['attendance_marked']);
                    $tokenNum = str_replace('OPD-', '', $apt['token_number'] ?? '');
                ?>
                    <div class="apt-card" style="opacity: 0.85; filter: grayscale(20%);">
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
        </div>
        <?php endif; ?>
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
            fetch('api/cancel_token.php?portal=patient', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.status);
                    if (data.status === 'success') setTimeout(() => location.reload(), 2000);
                });
        }

        function requestLate(tokenId, notifId) {
            const note = prompt('Request a late token?\nYou can optionally add a note for the medical staff:');
            if (note === null) return; // User cancelled
            
            const fd = new FormData();
            fd.append('token_id', tokenId);
            fd.append('note', note);
            fetch('api/request_late_token.php?portal=patient', { method: 'POST', body: fd })
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
            fetch('api/dismiss_notification.php?portal=patient', { method: 'POST', body: fd });
            const el = document.getElementById('notif-' + notifId);
            if (el) el.remove();
        }

        function dismissAll() {
            const fd = new FormData();
            fetch('api/dismiss_notification.php?portal=patient', { method: 'POST', body: fd })
                .then(() => {
                    document.getElementById('notifBanner').remove();
                });
        }
    </script>
</body>
</html>
