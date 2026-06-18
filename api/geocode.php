<?php
session_name('medconnect_staff');
require 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if (!$query) {
    echo json_encode(['status' => 'error', 'message' => 'Query is required.']);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($query);

// Use cURL (more reliable than file_get_contents for external URLs)
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'MedConnect/1.0 (medical center management; contact@medconnect.local)',
        CURLOPT_HTTPHEADER     => ['Accept-Language: en'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $response === '') {
        echo json_encode(['status' => 'error', 'message' => 'Could not reach geocoding service. cURL error: ' . $curlError]);
        exit;
    }
} elseif (ini_get('allow_url_fopen')) {
    // Fallback to file_get_contents
    $context = stream_context_create([
        'http' => [
            'header'  => "User-Agent: MedConnect/1.0\r\nAccept-Language: en\r\n",
            'timeout' => 10,
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        echo json_encode(['status' => 'error', 'message' => 'Could not reach geocoding service. Please place the pin on the map manually.']);
        exit;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Server cannot make outbound HTTP requests. Please click the map to set the pin manually.']);
    exit;
}

$results = json_decode($response, true);

if (!is_array($results) || count($results) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Address not found. Try a more specific query, e.g. "Kandy General Hospital, Kandy, Sri Lanka".']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'lat'    => (float)$results[0]['lat'],
    'lng'    => (float)$results[0]['lon'],
    'label'  => $results[0]['display_name'],
]);
