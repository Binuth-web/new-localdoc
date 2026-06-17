<?php
// Quick test - just check login doesn't break staff session
$cookieJar = tempnam(sys_get_temp_dir(), 'cookies_');

// Step 1: Admin/Staff login
$ch = curl_init('http://localhost:8000/api/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'email=admin@medconnect.com&password=admin123&role=admin&portal=admin',
    CURLOPT_HEADER => true,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_COOKIEJAR  => $cookieJar,
]);
$res = curl_exec($ch);
preg_match('/\r\n\r\n(.*)$/s', $res, $m);
echo "ADMIN LOGIN: " . ($m[1] ?? '') . "\n";

// Step 2: admin_sessions should work
$ch2 = curl_init('http://localhost:8000/api/admin_sessions.php');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_COOKIEJAR  => $cookieJar,
]);
$r2 = json_decode(curl_exec($ch2), true);
echo "ADMIN SESSIONS (before patient login): status=" . ($r2['status'] ?? 'unknown') . "\n";

// Step 3: Simulate patient login on same browser (overrides medconnect_portal cookie)
$ch3 = curl_init('http://localhost:8000/api/login.php');
curl_setopt_array($ch3, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'first_name=Test&last_name=Patient&id_number=TEST123&role=patient&portal=patient',
    CURLOPT_HEADER => true,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_COOKIEJAR  => $cookieJar,
]);
$res3 = curl_exec($ch3);
preg_match('/\r\n\r\n(.*)$/s', $res3, $m3);
echo "PATIENT LOGIN: " . ($m3[1] ?? '') . "\n";

// Step 4: admin_sessions should STILL work (this was failing before the fix!)
$ch4 = curl_init('http://localhost:8000/api/admin_sessions.php');
curl_setopt_array($ch4, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_COOKIEJAR  => $cookieJar,
]);
$r4 = json_decode(curl_exec($ch4), true);
echo "ADMIN SESSIONS (after patient login): status=" . ($r4['status'] ?? 'unknown') . "\n";
if (($r4['status'] ?? '') === 'success') {
    echo "✅ FIX VERIFIED: Staff/Admin session survived a patient login!\n";
} else {
    echo "❌ STILL BROKEN: " . ($r4['message'] ?? 'unknown error') . "\n";
}

unlink($cookieJar);
