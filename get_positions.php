<?php
require_once 'includes/config.php';
require_once 'includes/auth_functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify client role and session
if (!isLoggedIn() || $_SESSION['user']['role'] !== 'client') {
    header('Location: ../index.php');
    exit();
}

// Ensure client_id in session
if (!isset($_SESSION['user']['client_id'])) {
    $stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $c = $stmt->fetch();
    if (!$c) {
        die("Error: Client account not properly configured");
    }
    $_SESSION['user']['client_id'] = $c['client_id'];
}
$client_id = $_SESSION['user']['client_id'];

// Fetch client info
$stmt = $pdo->prepare("
    SELECT c.*, u.email 
      FROM clients c 
      JOIN users   u ON c.user_id = u.user_id 
     WHERE c.client_id = ?
");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) {
    die("Error: Could not retrieve client information");
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$positions_file = __DIR__ . '/driver_positions.json';

if (!file_exists($positions_file)) {
    die(json_encode(['error' => 'Fichier de positions introuvable']));
}

$content = file_get_contents($positions_file);
if ($content === false) {
    die(json_encode(['error' => 'Impossible de lire le fichier de positions']));
}

$positions = json_decode($content, true);
if ($positions === null) {
    die(json_encode(['error' => 'Données de positions corrompues']));
}

// Filtrer les positions trop anciennes (plus de 5 minutes)
$current_time = time();
$active_positions = [];
foreach ($positions as $driver_id => $position) {
    if ($current_time - $position['timestamp'] <= 300) { // 5 minutes
        $active_positions[] = [
            'id' => $driver_id,
            'name' => $position['name'] ?? 'Conducteur '.$driver_id,
            'lat' => (float)$position['lat'],
            'lng' => (float)$position['lng'],
            'speed' => (int)$position['speed'],
            'train_type' => $position['train_type'] ?? 'Non spécifié',
            'timestamp' => $position['timestamp']
        ];
    }
}

echo json_encode(array_values($active_positions));
?>