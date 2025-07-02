<?php
/**
 * WhimsicalFrog Extended API Handler Functions
 * Centralized PHP functions to eliminate duplication
 * Generated: 2025-07-01 23:42:24
 */

// Include API and response dependencies
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/response.php';



function handlePost($pdo, $input) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'save':
            saveBackground($pdo, $input);
            break;
        case 'apply':
            applyBackground($pdo, $input);
            break;
        case 'upload':
            handleUpload($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}



function handleDelete($pdo, $input) {
    $backgroundId = $input['background_id'] ?? '';
    
    if (empty($backgroundId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Background ID is required']);
        return;
    }
    
    try {
        // Check if it's an Original background
        $checkStmt = $pdo->prepare("SELECT background_name, image_filename, webp_filename FROM backgrounds WHERE id = ?");
        $checkStmt->execute([$backgroundId]);
        $background = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$background) {
            echo json_encode(['success' => false, 'message' => 'Background not found']);
            return;
        }
        
        if ($background['background_name'] === 'Original') {
            echo json_encode(['success' => false, 'message' => 'Original backgrounds cannot be deleted - they are protected']);
            return;
        }
        
        // Delete the background
        $deleteStmt = $pdo->prepare("DELETE FROM backgrounds WHERE id = ?");
        
        if ($deleteStmt->execute([$backgroundId])) {
            // Optionally delete the image files (commented out for safety)
            // if (file_exists("../images/" . $background['image_filename'])) {
            //     unlink("../images/" . $background['image_filename']);
            // }
            // if ($background['webp_filename'] && file_exists("../images/" . $background['webp_filename'])) {
            //     unlink("../images/" . $background['webp_filename']);
            // }
            
            echo json_encode(['success' => true, 'message' => 'Background deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete background']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}



function handlePut($pdo) {
    // Update single rule
    parse_str(file_get_contents("php://input"), $data);
    
    $id = $data['id'] ?? '';
    $cssValue = $data['css_value'] ?? '';
    
    if (empty($id) || empty($cssValue)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE global_css_rules 
        SET css_value = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([$cssValue, $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'CSS rule updated successfully'
    ]);
}

?>