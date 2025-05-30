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
// Activer le reporting d'erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Vérifier si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Méthode non autorisée']));
}

// Récupérer les données
$input = json_decode(file_get_contents('php://input'), true);
$driver_id = $input['driver_id'] ?? null;
$driver_name = $input['driver_name'] ?? 'Inconnu';
$lat = $input['lat'] ?? null;
$lng = $input['lng'] ?? null;
$speed = $input['speed'] ?? 0;
$train_type = $input['train_type'] ?? 'Non spécifié';

if (!$driver_id || !$lat || !$lng) {
    die(json_encode(['error' => 'Données manquantes', 'received' => $input]));
}

// Chemin absolu pour le fichier
$positions_file = __DIR__ . '/driver_positions.json';

// Lire les positions existantes
$positions = [];
if (file_exists($positions_file)) {
    $content = file_get_contents($positions_file);
    if ($content !== false) {
        $positions = json_decode($content, true) ?: [];
    }
}

// Mettre à jour la position avec toutes les informations
$positions[$driver_id] = [
    'id' => $driver_id,
    'name' => $driver_name,
    'lat' => (float)$lat,
    'lng' => (float)$lng,
    'speed' => (int)$speed,
    'train_type' => $train_type,
    'timestamp' => time()
];

// Enregistrer
$result = file_put_contents($positions_file, json_encode($positions));

if ($result === false) {
    die(json_encode(['error' => 'Échec de l\'écriture du fichier', 'file' => $positions_file]));
}

echo json_encode(['success' => true]);
?>