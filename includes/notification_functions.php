<?php
require_once 'includes/config.php';
require_once 'includes/auth_functions.php';

function createNotification($user_id, $type, $title, $message, $related_request_id = null, $metadata = []) {
    global $pdo;
    
    // Add target_audience to metadata if user_id is null
    if ($user_id === null && !isset($metadata['target_audience'])) {
        $metadata['target_audience'] = 'admins';
    }

    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, type, title, message, metadata, related_request_id)
        VALUES (?, ?, ?, ?, ?::jsonb, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $type,
        $title,
        $message,
        json_encode($metadata),
        $related_request_id
    ]);
    
    return $pdo->lastInsertId();
}

function getUnreadNotifications($user_id, $limit = 5) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id, type, title, message, metadata, 
               related_request_id, created_at
        FROM notifications 
        WHERE user_id = ? AND is_read = FALSE
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    
    return $stmt->fetchAll();
}

function markAsRead($notification_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE notifications SET is_read = TRUE 
        WHERE id = ?
    ");
    return $stmt->execute([$notification_id]);
}