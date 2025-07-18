<?php

/**
 * WhimsicalFrog API Handler Functions
 * Centralized API handling functions to eliminate duplication
 * Generated: 2025-07-01 23:23:18
 */

// Include authentication and response helpers
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/logger.php';


function handleGet($pdo)
{
    $roomType = $_GET['room_type'] ?? null;
    $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';

    try {
        if ($roomType) {
            // Get backgrounds for specific room
            $sql = "SELECT * FROM backgrounds WHERE room_type = ?";
            $params = [$roomType];

            if ($activeOnly) {
                $sql .= " AND is_active = 1";
            }

            $sql .= " ORDER BY background_name = 'Original' DESC, created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $backgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($activeOnly && count($backgrounds) > 0) {
                echo json_encode(['success' => true, 'background' => $backgrounds[0]]);
            } else {
                echo json_encode(['success' => true, 'backgrounds' => $backgrounds]);
            }
        } else {
            // Get all backgrounds grouped by room
            $stmt = $pdo->prepare("
                SELECT room_type, COUNT(*) as total_count, 
                       SUM(is_active) as active_count,
                       GROUP_CONCAT(CASE WHEN is_active = 1 THEN background_name END) as active_background
                FROM backgrounds 
                GROUP BY room_type 
                ORDER BY room_type
            ");
            $stmt->execute();
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'summary' => $summary]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
// handlePost function moved to api_handlers_extended.php for centralization


function handlePut($pdo)
{
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


function handleDelete($pdo, $input)
{
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
