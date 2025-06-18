<?php
// Migration script to update room_maps table from room names to room numbers
// This maintains the generic infrastructure while preserving existing room data

header('Content-Type: application/json');

// Direct database configuration for migration
$host = 'localhost';
$db   = 'whimsicalfrog';
$user = 'root';
$pass = 'Palz2516';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Room mapping from old names to new numbers
    $roomMapping = [
        'room_tshirts' => 'room2',
        'room_tumblers' => 'room3', 
        'room_artwork' => 'room4',
        'room_sublimation' => 'room5',
        'room_windowwraps' => 'room6'
    ];
    
    $pdo->beginTransaction();
    
    $updateCount = 0;
    $messages = [];
    
    foreach ($roomMapping as $oldName => $newName) {
        // Update room_maps table
        $stmt = $pdo->prepare("UPDATE room_maps SET room_type = ? WHERE room_type = ?");
        $result = $stmt->execute([$newName, $oldName]);
        
        if ($result) {
            $affected = $stmt->rowCount();
            $updateCount += $affected;
            $messages[] = "Updated {$affected} room_maps entries: {$oldName} → {$newName}";
        }
    }
    
    // Also update backgrounds table if it exists
    try {
        foreach ($roomMapping as $oldName => $newName) {
            $stmt = $pdo->prepare("UPDATE backgrounds SET room_type = ? WHERE room_type = ?");
            $result = $stmt->execute([$newName, $oldName]);
            
            if ($result) {
                $affected = $stmt->rowCount();
                $messages[] = "Updated {$affected} background entries: {$oldName} → {$newName}";
            }
        }
    } catch (PDOException $e) {
        $messages[] = "Note: backgrounds table not found or error updating: " . $e->getMessage();
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Migration completed successfully. Updated {$updateCount} room_maps entries.",
        'details' => $messages,
        'room_mapping' => $roomMapping
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage()
    ]);
}
?> 