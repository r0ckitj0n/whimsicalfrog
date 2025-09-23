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
    // Prefer 'room' (1..5), fallback to legacy 'room_type' ('room1'..'room5')
    $roomType = null;
    if (isset($_GET['room']) && $_GET['room'] !== '') {
        $r = $_GET['room'];
        if (preg_match('/^room(\d+)$/i', (string)$r, $m)) {
            $roomType = 'room' . (int)$m[1];
        } else {
            $roomType = 'room' . (int)$r;
        }
    } elseif (isset($_GET['room_type'])) {
        $roomType = $_GET['room_type'];
    }
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

            $backgrounds = Database::queryAll($sql, $params);

            if ($activeOnly && count($backgrounds) > 0) {
                echo json_encode(['success' => true, 'background' => $backgrounds[0]]);
            } else {
                echo json_encode(['success' => true, 'backgrounds' => $backgrounds]);
            }
        } else {
            // Get all backgrounds grouped by room
            $summary = Database::queryAll("\n                SELECT room_type, COUNT(*) as total_count, \n                       SUM(is_active) as active_count,\n                       GROUP_CONCAT(CASE WHEN is_active = 1 THEN background_name END) as active_background\n                FROM backgrounds \n                GROUP BY room_type \n                ORDER BY room_type\n            ");

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

    Database::execute("\n        UPDATE global_css_rules \n        SET css_value = ?, updated_at = CURRENT_TIMESTAMP\n        WHERE id = ?\n    ", [$cssValue, $id]);

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
        $background = Database::queryOne("SELECT background_name, image_filename, webp_filename FROM backgrounds WHERE id = ?", [$backgroundId]);

        if (!$background) {
            echo json_encode(['success' => false, 'message' => 'Background not found']);
            return;
        }

        if ($background['background_name'] === 'Original') {
            echo json_encode(['success' => false, 'message' => 'Original backgrounds cannot be deleted - they are protected']);
            return;
        }

        // Delete the background
        $affected = Database::execute("DELETE FROM backgrounds WHERE id = ?", [$backgroundId]);

        if ($affected !== false) {
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
