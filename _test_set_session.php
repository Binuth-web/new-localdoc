<?php
require 'api/db_connect.php';

// Force a staff session
$_SESSION['user_id'] = 13; // Staff Member
$_SESSION['role'] = 'staff';
$_SESSION['name'] = 'Staff Member';

echo "Session set as staff.\n";
