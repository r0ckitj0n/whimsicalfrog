<?php

require_once __DIR__ . '/../includes/functions.php';
// Background management API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection
$host = 'localhost';
$dbname = 'whimsicalfrog';
$username = 'root';
$password = 'Palz2516';

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo, $input);
        break;
    case 'PUT':
        handlePut($pdo, $input);
        break;
    case 'DELETE':
        handleDelete($pdo, $input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
// handlePost function moved to api_handlers_extended.php for centralization

function saveBackground($pdo, $input)
{
    $roomType = $input['room_type'] ?? '';
    $backgroundName = $input['background_name'] ?? '';
    $imageFilename = $input['image_filename'] ?? '';
    $webpFilename = $input['webp_filename'] ?? null;

    if (empty($roomType) || empty($backgroundName) || empty($imageFilename)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    try {
        // Check if background name already exists for this room
        $checkStmt = $pdo->prepare("SELECT id FROM backgrounds WHERE room_type = ? AND background_name = ?");
        $checkStmt->execute([$roomType, $backgroundName]);

        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Background name already exists for this room']);
            return;
        }

        // Insert new background
        $stmt = $pdo->prepare("
            INSERT INTO backgrounds (room_type, background_name, image_filename, webp_filename, is_active) 
            VALUES (?, ?, ?, ?, 0)
        ");

        if ($stmt->execute([$roomType, $backgroundName, $imageFilename, $webpFilename])) {
            $backgroundId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Background saved successfully', 'id' => $backgroundId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save background']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function applyBackground($pdo, $input)
{
    $roomType = $input['room_type'] ?? '';
    $backgroundId = $input['background_id'] ?? '';

    if (empty($roomType) || empty($backgroundId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Deactivate all backgrounds for this room
        $deactivateStmt = $pdo->prepare("UPDATE backgrounds SET is_active = 0 WHERE room_type = ?");
        $deactivateStmt->execute([$roomType]);

        // Activate the selected background
        $activateStmt = $pdo->prepare("UPDATE backgrounds SET is_active = 1 WHERE id = ? AND room_type = ?");
        $activateStmt->execute([$backgroundId, $roomType]);

        if ($activateStmt->rowCount() > 0) {
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Background applied successfully']);
        } else {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => 'Background not found or invalid room type']);
        }
    } catch (PDOException $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
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

function handleUpload($pdo, $input)
{
    // This would handle file uploads - for now, just return success
    // In a full implementation, this would process uploaded files
    echo json_encode(['success' => true, 'message' => 'Upload endpoint ready']);
}
?> 