<?php
require_once 'includes/config.php';

echo "Starting migration...\n";

$json_data = file_get_contents('driver_positions.json');
$positions = json_decode($json_data, true);

$migrated = 0;
$skipped = 0;

foreach ($positions as $driver_id => $position) {
    try {
        // First check if driver exists
        $stmt = $pdo->prepare("SELECT 1 FROM drivers WHERE driver_id = ?");
        $stmt->execute([$driver_id]);
        
        if (!$stmt->fetch()) {
            echo "Skipping position for non-existent driver ID: $driver_id\n";
            $skipped++;
            continue;
        }

        $stmt = $pdo->prepare("
            INSERT INTO driver_positions (driver_id, latitude, longitude, speed, train_type, timestamp)
            VALUES (?, ?, ?, ?, ?, to_timestamp(?))
        ");
        
        $stmt->execute([
            $driver_id,
            $position['lat'],
            $position['lng'],
            $position['speed'],
            $position['train_type'],
            $position['timestamp']
        ]);
        
        $migrated++;
        
    } catch (PDOException $e) {
        echo "Error inserting position for driver $driver_id: " . $e->getMessage() . "\n";
        $skipped++;
    }
}

echo "Migration completed!\n";
echo "Successfully migrated: $migrated positions\n";
echo "Skipped: $skipped positions (invalid driver IDs)\n";
?>