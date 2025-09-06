<?php

// Bootstrap consistent API behavior and DB config
require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/response.php';

// Early exit on preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection (centralized via Database singleton)
try {
    $pdo = Database::getInstance();
} catch (Throwable $e) {
    Response::serverError('Database connection failed', $e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Router
switch ($method) {
    case 'GET':
        wf_handle_backgrounds_get($pdo);
        break;
    case 'POST':
        wf_handle_backgrounds_post($pdo);
        break;
    case 'PUT':
        wf_handle_backgrounds_put($pdo);
        break;
    case 'DELETE':
        wf_handle_backgrounds_delete($pdo);
        break;
    default:
        Response::methodNotAllowed();
}

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
            Response::success(['id' => $backgroundId], 'Background saved successfully');
        } else {
            Response::error('Failed to save background');
        }
    } catch (PDOException $e) {
        Response::serverError('Database error', $e->getMessage());
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
            Response::success(null, 'Background applied successfully');
        } else {
            $pdo->rollBack();
            Response::error('Background not found or invalid room type');
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        Response::serverError('Database error', $e->getMessage());
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

            Response::success(null, 'Background deleted successfully');
        } else {
            Response::error('Failed to delete background');
        }
    } catch (PDOException $e) {
        Response::serverError('Database error', $e->getMessage());
    }
}

function handleUpload($pdo, $input)
{
    // This would handle file uploads - for now, just return success
    // In a full implementation, this would process uploaded files
    Response::success(null, 'Upload endpoint ready');
}

// Local request handlers using Response helper
function wf_handle_backgrounds_get(PDO $pdo): void {
    try {
        $roomType = $_GET['room_type'] ?? null;
        $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';

        if ($roomType !== null) {
            $sql = "SELECT * FROM backgrounds WHERE room_type = ?";
            $params = [$roomType];
            if ($activeOnly) { $sql .= " AND is_active = 1"; }
            $sql .= " ORDER BY background_name = 'Original' DESC, created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($activeOnly && count($rows) > 0) {
                Response::success(['background' => $rows[0]]);
            } else {
                Response::success(['backgrounds' => $rows]);
            }
        } else {
            $stmt = $pdo->prepare("SELECT room_type, COUNT(*) as total_count, SUM(is_active) as active_count, GROUP_CONCAT(CASE WHEN is_active = 1 THEN background_name END) as active_background FROM backgrounds GROUP BY room_type ORDER BY room_type");
            $stmt->execute();
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::success(['summary' => $summary]);
        }
    } catch (Throwable $e) {
        Response::serverError('Database error', $e->getMessage());
    }
}

function wf_handle_backgrounds_post(PDO $pdo): void {
    $data = Response::getPostData(true);
    if (!is_array($data)) { Response::validationError('Invalid payload'); }
    $action = $data['action'] ?? '';
    switch ($action) {
        case 'save':
            saveBackground($pdo, $data);
            return;
        case 'apply':
            applyBackground($pdo, $data);
            return;
        case 'upload':
            handleUpload($pdo, $data);
            return;
        default:
            Response::error('Invalid action');
    }
}

function wf_handle_backgrounds_put(PDO $pdo): void {
    // Expect x-www-form-urlencoded or JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = Response::getJsonInput() ?? [];
    } else {
        parse_str(file_get_contents('php://input'), $data);
    }
    $id = $data['id'] ?? '';
    $cssValue = $data['css_value'] ?? '';
    if (empty($id) || $cssValue === '') {
        Response::validationError(['id' => 'required', 'css_value' => 'required']);
    }
    try {
        $stmt = $pdo->prepare("UPDATE global_css_rules SET css_value = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$cssValue, $id]);
        Response::success(null, 'CSS rule updated successfully');
    } catch (Throwable $e) {
        Response::serverError('Database error', $e->getMessage());
    }
}

function wf_handle_backgrounds_delete(PDO $pdo): void {
    $data = Response::getJsonInput() ?? [];
    handleDelete($pdo, $data);
}
?> 