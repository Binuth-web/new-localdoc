<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'medical_staff') {
    header('Location: login.html?role=medical_staff');
    exit;
}
require 'api/db_connect.php';

$sessionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$sessionId) {
    header('Location: dashboard_staff.php');
    exit;
}

$stmt = $pdo->prepare("SELECT os.*, mc.name AS center_name FROM opd_sessions os JOIN medical_centers mc ON os.clinic_id = mc.id WHERE os.id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) { header('Location: dashboard_staff.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session View | MedConnect Staff</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }

        .top-bar {
            background: linear-gradient(135deg, #0f4c81, #1a73e8);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .top-bar h1 { margin: 0; font-size: 1.25rem; }
        .top-bar .meta { font-size: 0.85rem; opacity: 0.9; margin-top: 0.2rem; }
        .top-bar .back-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.4rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .top-bar .back-btn:hover { background: rgba(255,255,255,0.25); }

        .page-body { padding: 2rem; max-width: 1200px; margin: 0 auto; }

        /* Legend */
        .legend {
            display: flex; gap: 0.75rem; flex-wrap: wrap;
            background: white; padding: 0.75rem 1.25rem; border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 1.5rem; align-items: center;
        }
        .legend span { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-right: 0.5rem; }
        .legend-item { display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }

        /* Token grid */
        .token-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .token-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            border-left: 4px solid #e2e8f0;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .token-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .token-card.status-done    { border-left-color: #10b981; }
        .token-card.status-present { border-left-color: #3b82f6; }
        .token-card.status-pending { border-left-color: #f59e0b; }
        .token-card.status-absent  { border-left-color: #ef4444; }
        .token-card.status-cancelled { border-left-color: #94a3b8; opacity: 0.65; }
        .token-card.status-empty   { border-left-color: #e2e8f0; opacity: 0.5; }
        .token-card.status-late_request { border-left-color: #f97316; background: #fff7ed; }

        .token-number { font-size: 1.4rem; font-weight: 800; color: #1e293b; letter-spacing: 0.05em; }
        .token-name   { font-size: 0.82rem; color: #475569; margin: 0.2rem 0 0.6rem; min-height: 1.1rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .status-badge {
            display: inline-block; font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em;
            padding: 2px 8px; border-radius: 20px; margin-bottom: 0.6rem;
        }
        .badge-done      { background: #dcfce7; color: #166534; }
        .badge-present   { background: #dbeafe; color: #1d4ed8; }
        .badge-pending   { background: #fef3c7; color: #92400e; }
        .badge-absent    { background: #fee2e2; color: #991b1b; }
        .badge-cancelled { background: #f1f5f9; color: #64748b; }
        .badge-empty     { background: #f8fafc; color: #94a3b8; }
        .badge-late_request { background: #ffedd5; color: #c2410c; }

        .token-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.4rem; }
        .btn-mark {
            flex: 1; font-size: 0.72rem; font-weight: 600;
            padding: 0.3rem 0.4rem; border: none; border-radius: 6px;
            cursor: pointer; transition: opacity 0.2s, transform 0.1s;
        }
        .btn-mark:hover { opacity: 0.85; transform: scale(0.98); }
        .btn-present { background: #dbeafe; color: #1d4ed8; }
        .btn-absent  { background: #fee2e2; color: #991b1b; }
        .btn-approve { background: #dcfce7; color: #166534; }

        /* Late requests section */
        .section-title {
            font-size: 1rem; font-weight: 700; color: #334155;
            margin: 1.5rem 0 0.75rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .section-title .count-badge {
            background: #f97316; color: white; font-size: 0.7rem;
            padding: 1px 7px; border-radius: 20px;
        }

        /* Notification list for staff */
        .notif-panel {
            background: white; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            padding: 1rem 1.25rem; margin-bottom: 1.5rem;
        }
        .notif-item {
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: 0.6rem 0; border-bottom: 1px solid #f1f5f9; gap: 1rem;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-msg { font-size: 0.85rem; color: #334155; flex: 1; line-height: 1.4; }
        .notif-time { font-size: 0.75rem; color: #94a3b8; white-space: nowrap; }

        .refresh-btn {
            background: white; border: 1px solid #e2e8f0; color: #334155;
            padding: 0.4rem 0.9rem; border-radius: 6px; font-size: 0.8rem;
            cursor: pointer; transition: background 0.2s;
        }
        .refresh-btn:hover { background: #f8fafc; }

        .toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem;
            background: #1e293b; color: white; padding: 0.75rem 1.25rem;
            border-radius: 8px; font-size: 0.9rem; z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transform: translateY(100px); opacity: 0;
            transition: transform 0.3s, opacity 0.3s;
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { border-left: 4px solid #10b981; }
        .toast.error   { border-left: 4px solid #ef4444; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div>
            <h1><i class="fa-solid fa-clipboard-list"></i> Session Token Board</h1>
            <div class="meta">
                <?php echo htmlspecialchars($session['center_name']); ?> &nbsp;|&nbsp;
                Dr. <?php echo htmlspecialchars($session['doctor_name']); ?> &nbsp;|&nbsp;
                <?php echo htmlspecialchars($session['session_date']); ?>
                (<?php echo substr($session['start_time'],0,5); ?> – <?php echo substr($session['end_time'],0,5); ?>)
            </div>
        </div>
        <div style="display:flex;gap:0.75rem;align-items:center;">
            <button class="refresh-btn" onclick="loadTokens()"><i class="fa-solid fa-rotate"></i> Refresh</button>
            <a href="dashboard_staff.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="page-body">

        <!-- Legend -->
        <div class="legend">
            <span>Legend:</span>
            <div class="legend-item"><div class="legend-dot" style="background:#10b981"></div> Done</div>
            <div class="legend-item"><div class="legend-dot" style="background:#3b82f6"></div> Present</div>
            <div class="legend-item"><div class="legend-dot" style="background:#f59e0b"></div> Pending</div>
            <div class="legend-item"><div class="legend-dot" style="background:#ef4444"></div> Absent</div>
            <div class="legend-item"><div class="legend-dot" style="background:#94a3b8"></div> Cancelled</div>
            <div class="legend-item"><div class="legend-dot" style="background:#f97316"></div> Late Request</div>
            <div class="legend-item"><div class="legend-dot" style="background:#e2e8f0"></div> Empty</div>
            <div style="margin-left:auto; font-size:0.8rem; color:#64748b;">
                Max Tokens: <strong id="maxTokensCount">—</strong> &nbsp;|&nbsp;
                Auto-refresh in <strong id="countdownTimer">30</strong>s
            </div>
        </div>

        <!-- Token grid -->
        <div id="tokenGrid" class="token-grid">
            <div style="grid-column:1/-1;text-align:center;padding:2rem;color:#94a3b8;">
                <i class="fa-solid fa-spinner fa-spin"></i> Loading tokens...
            </div>
        </div>

        <!-- Late requests -->
        <div id="lateSection" style="display:none;">
            <div class="section-title">
                <i class="fa-solid fa-clock" style="color:#f97316"></i>
                Late Token Requests
                <span class="count-badge" id="lateCount">0</span>
            </div>
            <div id="lateGrid" class="token-grid"></div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        const SESSION_ID = <?php echo $sessionId; ?>;
        let countdownVal = 30;
        let countdownInterval;

        function startCountdown() {
            clearInterval(countdownInterval);
            countdownVal = 30;
            document.getElementById('countdownTimer').textContent = 30;
            countdownInterval = setInterval(() => {
                countdownVal--;
                document.getElementById('countdownTimer').textContent = countdownVal;
                if (countdownVal <= 0) {
                    loadTokens();
                }
            }, 1000);
        }

        function showToast(msg, type = 'success') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'toast show ' + type;
            setTimeout(() => { t.className = 'toast'; }, 3500);
        }

        const statusConfig = {
            done:         { label: 'Done',         badge: 'badge-done',         card: 'status-done' },
            present:      { label: 'Present',       badge: 'badge-present',      card: 'status-present' },
            pending:      { label: 'Pending',       badge: 'badge-pending',      card: 'status-pending' },
            absent:       { label: 'Absent',        badge: 'badge-absent',       card: 'status-absent' },
            cancelled:    { label: 'Cancelled',     badge: 'badge-cancelled',    card: 'status-cancelled' },
            empty:        { label: 'Empty',         badge: 'badge-empty',        card: 'status-empty' },
            late_request: { label: 'Late Request',  badge: 'badge-late_request', card: 'status-late_request' },
            'no-show':    { label: 'Absent',        badge: 'badge-absent',       card: 'status-absent' },
        };

        function makeCard(slot) {
            const cfg = statusConfig[slot.status] || statusConfig['pending'];
            const numLabel = slot.token_number.replace('OPD-', '');
            let actionsHtml = '';

            if (slot.status === 'pending' && slot.token_id) {
                actionsHtml = `
                    <div class="token-actions">
                        <button class="btn-mark btn-present" onclick="markAttendance(${slot.token_id}, 'present')">✓ Present</button>
                        <button class="btn-mark btn-absent"  onclick="markAttendance(${slot.token_id}, 'absent')">✗ Absent</button>
                    </div>`;
            } else if (slot.status === 'late_request' && slot.token_id) {
                actionsHtml = `
                    <div class="token-actions">
                        <button class="btn-mark btn-approve" onclick="approveLate(${slot.token_id})">✓ Approve Late</button>
                    </div>`;
            }

            return `
                <div class="token-card ${cfg.card}" id="card-${slot.token_id || 'e' + slot.slot}">
                    <div class="token-number">${numLabel}</div>
                    <div class="token-name">${slot.patient_name || '—'}</div>
                    <div><span class="status-badge ${cfg.badge}">${cfg.label}</span></div>
                    ${actionsHtml}
                </div>`;
        }

        function loadTokens() {
            fetch(`api/session_tokens.php?session_id=${SESSION_ID}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status !== 'success') return;

                    document.getElementById('maxTokensCount').textContent = data.session.max_tokens;

                    const regular = data.slots.filter(s => s.slot !== 'LATE');
                    const late    = data.slots.filter(s => s.slot === 'LATE');

                    document.getElementById('tokenGrid').innerHTML = regular.map(makeCard).join('');

                    if (late.length > 0) {
                        document.getElementById('lateSection').style.display = 'block';
                        document.getElementById('lateCount').textContent = late.length;
                        document.getElementById('lateGrid').innerHTML = late.map(makeCard).join('');
                    } else {
                        document.getElementById('lateSection').style.display = 'none';
                    }

                    startCountdown();
                })
                .catch(() => showToast('Failed to load tokens.', 'error'));
        }

        function markAttendance(tokenId, action) {
            const fd = new FormData();
            fd.append('token_id', tokenId);
            fd.append('action', action);
            fetch('api/mark_attendance.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.status);
                    loadTokens();
                });
        }

        function approveLate(tokenId) {
            if (!confirm('Approve this late token request?')) return;
            const fd = new FormData();
            fd.append('token_id', tokenId);
            fetch('api/approve_late_token.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.status);
                    loadTokens();
                });
        }

        // Initial load
        loadTokens();
    </script>
</body>
</html>
