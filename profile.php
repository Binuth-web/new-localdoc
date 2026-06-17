<?php
session_name('medconnect_patient');
require 'api/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.html?role=patient");
    exit;
}
require 'api/db_connect.php';
require 'api/helpers.php';

$stmt = $pdo->prepare('SELECT full_name, email, phone, id_number, date_of_birth FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch();
$user = splitFullName($row['full_name'] ?? '');
$user['email'] = $row['email'] ?? '';
$user['phone'] = $row['phone'] ?? '';
$user['id_number'] = $row['id_number'] ?? '';
$user['date_of_birth'] = $row['date_of_birth'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | MedConnect</title>
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
            <a href="dashboard_patient.php">My Appointments</a>
            <span style="font-weight: 600; color: var(--primary);">My Profile</span>
            <a href="api/logout.php?portal=patient" class="btn-outline" style="padding: 0.4rem 1rem; border-radius: 4px; text-decoration: none; margin-left: 1rem;">Logout</a>
        </div>
    </nav>

    <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold;">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div>
                    <h2 style="color: var(--secondary); margin: 0;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p style="color: var(--text-muted); margin: 0; font-size: 0.9rem;">Patient Account</p>
                </div>
            </div>
            <button class="btn-outline" style="padding: 0.5rem 1rem; border-radius: 4px;" onclick="enableEdit()" id="edit-btn">Edit Profile</button>
        </div>
        
        <div id="profile-alert" class="alert"></div>

        <form id="profileForm" onsubmit="updateProfile(event)">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required disabled>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required disabled>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required disabled>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" disabled>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>NIC Number</label>
                <input type="text" name="id_number" class="form-control" value="<?php echo htmlspecialchars($user['id_number']); ?>" required disabled>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Date of Birth</label>
                <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($user['date_of_birth']); ?>" required disabled>
            </div>

            <button type="submit" class="btn-primary" id="save-btn" style="width: 100%; margin-top: 1rem; display: none;">Save Changes</button>
        </form>
    </div>

    <script>
        function enableEdit() {
            document.querySelectorAll('#profileForm input').forEach(input => input.disabled = false);
            document.getElementById('edit-btn').style.display = 'none';
            document.getElementById('save-btn').style.display = 'block';
        }
        function updateProfile(event) {
            event.preventDefault();
            const form = document.getElementById('profileForm');
            const alertBox = document.getElementById('profile-alert');
            const formData = new FormData(form);

            fetch('api/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alertBox.style.display = 'block';
                if(data.status === 'success') {
                    alertBox.className = 'alert success';
                    alertBox.innerText = data.message;
                    setTimeout(() => window.location.href = 'index.html', 1500);
                } else {
                    alertBox.className = 'alert error';
                    alertBox.innerText = data.message;
                }
            })
            .catch(err => {
                alertBox.style.display = 'block';
                alertBox.className = 'alert error';
                alertBox.innerText = 'Network error occurred.';
            });
        }
    </script>
</body>
</html>
