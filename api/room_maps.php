<?php
// Version: 2.0 - With Original map protection
require_once 'config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Create room_maps table if it doesn't exist
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS room_maps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_type VARCHAR(50) NOT NULL,
        map_name VARCHAR(100) NOT NULL,
        coordinates TEXT,
        is_active BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_room_type (room_type),
        INDEX idx_active (is_active),
        INDEX idx_room_active (room_type, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($createTableSQL);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($method) {
        case 'POST':
            if ($input['action'] === 'save') {
                // Save a new room map
                $stmt = $pdo->prepare("INSERT INTO room_maps (room_type, map_name, coordinates) VALUES (?, ?, ?)");
                $result = $stmt->execute([
                    $input['room_type'],
                    $input['map_name'],
                    json_encode($input['coordinates'])
                ]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Room map saved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save room map']);
                }
            } elseif ($input['action'] === 'apply') {
                // Apply a map to a room (set as active and deactivate others)
                $pdo->beginTransaction();
                try {
                    // Deactivate all maps for this room type
                    $stmt = $pdo->prepare("UPDATE room_maps SET is_active = FALSE WHERE room_type = ?");
                    $stmt->execute([$input['room_type']]);
                    
                    // Activate the selected map
                    $stmt = $pdo->prepare("UPDATE room_maps SET is_active = TRUE WHERE id = ?");
                    $stmt->execute([$input['map_id']]);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Room map applied successfully']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to apply room map: ' . $e->getMessage()]);
                }
            } elseif ($input['action'] === 'restore') {
                // Restore a historical map (create a new map based on an old one)
                $pdo->beginTransaction();
                try {
                    // Get the historical map data
                    $stmt = $pdo->prepare("SELECT * FROM room_maps WHERE id = ?");
                    $stmt->execute([$input['map_id']]);
                    $originalMap = $stmt->fetch();
                    
                    if (!$originalMap) {
                        throw new Exception('Original map not found');
                    }
                    
                    // Create a new map with restored data
                    $newMapName = $originalMap['map_name'] . ' (Restored ' . date('Y-m-d H:i') . ')';
                    $stmt = $pdo->prepare("INSERT INTO room_maps (room_type, map_name, coordinates) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $originalMap['room_type'],
                        $newMapName,
                        $originalMap['coordinates']
                    ]);
                    
                    $newMapId = $pdo->lastInsertId();
                    
                    // Optionally apply it immediately if requested
                    if (isset($input['apply_immediately']) && $input['apply_immediately']) {
                        // Deactivate all maps for this room type
                        $stmt = $pdo->prepare("UPDATE room_maps SET is_active = FALSE WHERE room_type = ?");
                        $stmt->execute([$originalMap['room_type']]);
                        
                        // Activate the restored map
                        $stmt = $pdo->prepare("UPDATE room_maps SET is_active = TRUE WHERE id = ?");
                        $stmt->execute([$newMapId]);
                    }
                    
                    $pdo->commit();
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Map restored successfully',
                        'new_map_id' => $newMapId,
                        'new_map_name' => $newMapName
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to restore map: ' . $e->getMessage()]);
                }
            }
            break;
            
        case 'GET':
            if (isset($_GET['room_type'])) {
                if (isset($_GET['active_only']) && $_GET['active_only'] === 'true') {
                    // Get active map for a specific room
                    $stmt = $pdo->prepare("SELECT * FROM room_maps WHERE room_type = ? AND is_active = TRUE");
                    $stmt->execute([$_GET['room_type']]);
                    $map = $stmt->fetch();
                    
                    if ($map) {
                        $map['coordinates'] = json_decode($map['coordinates'], true);
                        echo json_encode(['success' => true, 'map' => $map]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'No active map found']);
                    }
                } else {
                    // Get all maps for a specific room
                    $stmt = $pdo->prepare("SELECT * FROM room_maps WHERE room_type = ? ORDER BY created_at DESC");
                    $stmt->execute([$_GET['room_type']]);
                    $maps = $stmt->fetchAll();
                    
                    foreach ($maps as &$map) {
                        $map['coordinates'] = json_decode($map['coordinates'], true);
                    }
                    
                    echo json_encode(['success' => true, 'maps' => $maps]);
                }
            } else {
                // Get all room maps
                $stmt = $pdo->query("SELECT * FROM room_maps ORDER BY room_type, created_at DESC");
                $maps = $stmt->fetchAll();
                
                foreach ($maps as &$map) {
                    $map['coordinates'] = json_decode($map['coordinates'], true);
                }
                
                echo json_encode(['success' => true, 'maps' => $maps]);
            }
            break;
            
        case 'DELETE':
            if (isset($input['map_id'])) {
                // Check if this is an "Original" map - these cannot be deleted
                $checkStmt = $pdo->prepare("SELECT map_name FROM room_maps WHERE id = ?");
                $checkStmt->execute([$input['map_id']]);
                $map = $checkStmt->fetch();
                
                if (!$map) {
                    echo json_encode(['success' => false, 'message' => 'Map not found']);
                    break;
                }
                
                if ($map['map_name'] === 'Original') {
                    echo json_encode(['success' => false, 'message' => 'Original maps cannot be deleted - they are protected']);
                    break;
                }
                
                $stmt = $pdo->prepare("DELETE FROM room_maps WHERE id = ?");
                $result = $stmt->execute([$input['map_id']]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Room map deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete room map']);
                }
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 