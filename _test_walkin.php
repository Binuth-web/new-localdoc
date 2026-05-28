<?php
require 'api/db_connect.php';

try {
    $stmt = $pdo->query("SELECT * FROM opd_tokens WHERE token_type = 'walk-in' LIMIT 1");
    $token = $stmt->fetch();
    
    if (!$token) die('No walk-in tokens found');
    
    echo 'Found token ' . $token['id'] . ' for patient ' . $token['patient_id'] . PHP_EOL;
    
    $pdo->prepare("UPDATE opd_tokens SET status = 'no-show', attendance_marked = 0 WHERE id = ?")->execute([$token['id']]);
    
    $message = 'test message';
    $pdo->prepare("INSERT INTO notifications (user_id, token_id, message, type, action_label, action_data) VALUES (?, ?, ?, 'action', 'Request Late Token', ?)")
        ->execute([
            $token['patient_id'],
            $token['id'],
            $message,
            json_encode(['token_id' => $token['id']])
        ]);
        
    echo 'Success';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
