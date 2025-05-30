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
$train_id = $data['train_id'] ?? null;
$driver_id = $_SESSION['user']['id'];

if (!$train_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Train ID is required']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Check if train exists and is available
    $stmt = $pdo->prepare("SELECT status FROM trains WHERE train_id = ?");
    $stmt->execute([$train_id]);
    $train = $stmt->fetch();

    if (!$train) {
        throw new Exception("Train not found");
    }

    // Check if train is already in use
    if ($train['status'] === 'in_use') {
        throw new Exception("Train is already in use");
    }

    // Find a valid contract for this train
    $stmt = $pdo->prepare("
        SELECT c.*, fr.gare_depart, fr.gare_arrivee, fr.wagon_count
        FROM contracts c
        JOIN freight_requests fr ON c.freight_request_id = fr.id
        WHERE c.train_id = ? 
        AND c.status = 'validé'
        AND NOT EXISTS (
            SELECT 1 FROM expeditions e 
            WHERE e.contract_id = c.contract_id 
            AND e.status = 'in_progress'
        )
        LIMIT 1
    ");
    $stmt->execute([$train_id]);
    $contract = $stmt->fetch();

    if (!$contract) {
        throw new Exception("No valid contract found for this train");
    }

    // Create new expedition
    $stmt = $pdo->prepare("
        INSERT INTO expeditions (
            contract_id, 
            train_id, 
            number_of_wagons,
            departure_station,
            arrival_station,
            departure_date,
            status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), 'in_progress', NOW(), NOW())
    ");
    $stmt->execute([
        $contract['contract_id'],
        $train_id,
        $contract['wagon_count'],
        $contract['gare_expéditrice'],
        $contract['gare_destinataire']
    ]);

    // Update train status
    $stmt = $pdo->prepare("UPDATE trains SET status = 'in_use' WHERE train_id = ?");
    $stmt->execute([$train_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Expedition started successfully']);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 