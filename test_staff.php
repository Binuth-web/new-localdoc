<?php
$cj = tempnam(sys_get_temp_dir(), "cj_");
$ch = curl_init("http://localhost:8000/api/login.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => "email=admin@medconnect.com&password=admin123&role=admin&portal=admin",
    CURLOPT_COOKIEJAR => $cj,
    CURLOPT_COOKIEFILE => $cj
]);
echo "LOGIN: " . curl_exec($ch) . "\n";

$ch2 = curl_init("http://localhost:8000/api/mark_attendance.php?portal=staff");
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => "token_id=1&action=present",
    CURLOPT_COOKIEFILE => $cj,
    CURLOPT_COOKIEJAR => $cj
]);
echo "MARK: " . curl_exec($ch2) . "\n";
