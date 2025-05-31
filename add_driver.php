<?php
header('Content-Type: application/json');

$host = '127.0.0.1';
$dbname = 'conducteur_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'];
    $password = password_hash($data['password'], PASSWORD_BCRYPT);
    $email = $data['email'];

    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'driver')");
    $stmt->execute([$username, $password, $email]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>