<?php
require_once 'includes/config.php';
require_once 'includes/auth_functions.php';

// Check if user is logged in and is a driver
if (!isLoggedIn() || $_SESSION['user']['role'] !== 'driver') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$driver_id = $_SESSION['user']['id'];

try {
    $pdo->beginTransaction();

    // Find active expedition for this driver
    $stmt = $pdo->prepare("
        SELECT e.*, c.contract_id, c.train_id
        FROM expeditions e
        JOIN contracts c ON e.contract_id = c.contract_id
        WHERE e.status = 'in_progress'
        AND c.train_id IN (
            SELECT train_id 
            FROM trains 
            WHERE status = 'in_use'
        )
        LIMIT 1
    ");
    $stmt->execute();
    $expedition = $stmt->fetch();

    if (!$expedition) {
        throw new Exception("No active expedition found");
    }

    // Update expedition status
    $stmt = $pdo->prepare("
        UPDATE expeditions 
        SET status = 'completed',
            arrival_date = NOW(),
            updated_at = NOW()
        WHERE expedition_id = ?
    ");
    $stmt->execute([$expedition['expedition_id']]);

    // Update train status
    $stmt = $pdo->prepare("
        UPDATE trains 
        SET status = 'available',
            next_available_date = NULL
        WHERE train_id = ?
    ");
    $stmt->execute([$expedition['train_id']]);

    // Update contract status
    $stmt = $pdo->prepare("
        UPDATE contracts 
        SET status = 'completed',
            updated_at = NOW()
        WHERE contract_id = ?
    ");
    $stmt->execute([$expedition['contract_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Expedition completed successfully']);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 