<?php
require 'api/db_connect.php';

$sessionId = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);
if (!$sessionId) {
    die("Session ID required.");
}

$stmt = $pdo->prepare("SELECT os.*, mc.name AS center_name FROM opd_sessions os JOIN medical_centers mc ON os.clinic_id = mc.id WHERE os.id = ? AND os.status = 'active'");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    die("Active session not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>On Site Patients | MedConnect</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: 'Inter', sans-serif; }
        .kiosk-container { background: white; width: 100%; max-width: 500px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .kiosk-header { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 2rem; text-align: center; }
        .kiosk-header h1 { margin: 0; font-size: 1.8rem; }
        .kiosk-header p { margin: 0.5rem 0 0; opacity: 0.9; }
        .kiosk-body { padding: 2rem; }
        
        .tabs { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem; }
        .tab { flex: 1; text-align: center; padding: 0.75rem; cursor: pointer; font-weight: 600; color: #64748b; transition: 0.2s; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab.active { color: #10b981; border-bottom-color: #10b981; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: #334155; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; margin-bottom: 1rem; }
        
        .btn-kiosk { width: 100%; padding: 1rem; background: #10b981; color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-kiosk:hover { background: #059669; }
        
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        
        #resultView { display: none; text-align: center; }
        .token-display { font-size: 3rem; font-weight: 800; color: #1e293b; margin: 1rem 0; letter-spacing: 0.05em; }
        .warning-box { background: #fffbeb; border: 1px solid #fde68a; padding: 1rem; border-radius: 8px; color: #92400e; font-size: 0.9rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <div class="kiosk-container">
        <div class="kiosk-header">
            <h1><i class="fa-solid fa-hospital-user"></i> On Site Patients</h1>
            <p><?php echo htmlspecialchars($session['center_name']); ?> - Dr. <?php echo htmlspecialchars($session['doctor_name']); ?></p>
        </div>
        
        <div class="kiosk-body" id="mainView">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('book')">Get Token</div>
                <div class="tab" onclick="switchTab('cancel')">Cancel Token</div>
            </div>
            
            <!-- Book Tab -->
            <div id="tab-book" class="tab-content active">
                <form id="bookForm" onsubmit="bookToken(event)">
                    <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Enter your name">
                    </div>
                    <div class="form-group">
                        <label>Phone Number (Optional)</label>
                        <input type="text" name="phone" class="form-control" placeholder="Enter phone number">
                    </div>
                    <button type="submit" class="btn-kiosk">Get On Site Token</button>
                </form>
            </div>
            
            <!-- Cancel Tab -->
            <div id="tab-cancel" class="tab-content">
                <form id="cancelForm" onsubmit="cancelToken(event)">
                    <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
                    <div class="form-group">
                        <label>Your Token Number</label>
                        <input type="text" name="token_number" class="form-control" required placeholder="e.g. OPD-004">
                    </div>
                    <button type="submit" class="btn-kiosk btn-danger">Cancel My Token</button>
                </form>
            </div>
        </div>
        
        <!-- Result View -->
        <div class="kiosk-body" id="resultView">
            <h2 style="color: #10b981; margin-top: 0;"><i class="fa-solid fa-circle-check"></i> Token Issued</h2>
            <div class="token-display" id="issuedToken">OPD-000</div>
            <p style="font-size: 1.1rem; color: #475569;" id="issuedName"></p>
            
            <div class="warning-box">
                <i class="fa-solid fa-triangle-exclamation"></i> 
                <strong>Important:</strong> You have been automatically marked as Present. 
                If you decide to leave the medical center before your turn, you MUST cancel your token using the "Cancel Token" tab to let others take your place.
            </div>
            
            <button class="btn-kiosk" style="margin-top: 1.5rem;" onclick="resetKiosk()">Back</button>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }
        
        function bookToken(e) {
            e.preventDefault();
            const fd = new FormData(e.target);
            fetch('api/kiosk_book.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('mainView').style.display = 'none';
                        document.getElementById('resultView').style.display = 'block';
                        document.getElementById('issuedToken').textContent = data.token_number;
                        document.getElementById('issuedName').textContent = data.name;
                        e.target.reset();
                    } else {
                        alert(data.message);
                    }
                });
        }
        
        function cancelToken(e) {
            e.preventDefault();
            const fd = new FormData(e.target);
            fetch('api/kiosk_cancel.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        e.target.reset();
                    }
                });
        }
        
        function resetKiosk() {
            document.getElementById('resultView').style.display = 'none';
            document.getElementById('mainView').style.display = 'block';
        }
    </script>
</body>
</html>
