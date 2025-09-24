<?php

// Bootstrap consistent API behavior and DB config
require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

// Early exit on preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection (centralized via Database singleton)
try {
    Database::getInstance();
} catch (Throwable $e) {
    Response::serverError('Database connection failed', $e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Router
switch ($method) {
    case 'GET':
        wf_handle_backgrounds_get();
        break;
    case 'POST':
        wf_handle_backgrounds_post();
        break;
    case 'PUT':
        wf_handle_backgrounds_put();
        break;
    case 'DELETE':
        wf_handle_backgrounds_delete();
        break;
    default:
        Response::methodNotAllowed();
}

function normalizeRoomNumberFromInput($input)
{
    $roomParam = $input['room'] ?? $input['room_number'] ?? null;
    if ($roomParam === null || $roomParam === '') {
        return '';
    }
    if (preg_match('/^room(\d+)$/i', (string)$roomParam, $m)) {
        return (string)((int)$m[1]);
    }
    return (string)((int)$roomParam);
}

function saveBackground($input)
{
    $roomNumber = normalizeRoomNumberFromInput($input);
    $backgroundName = $input['background_name'] ?? '';
    $imageFilename = $input['image_filename'] ?? '';
    $webpFilename = $input['webp_filename'] ?? null;

    if ($roomNumber === '' || !preg_match('/^[1-5]$/', $roomNumber) || empty($backgroundName) || empty($imageFilename)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']);
        return;
    }

    try {
        // Check if background name already exists for this room
        $exists = Database::queryOne("SELECT id FROM backgrounds WHERE room_number = ? AND background_name = ? LIMIT 1", [$roomNumber, $backgroundName]);

        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Background name already exists for this room']);
            return;
        }

        // Insert new background (populate room_number as well during migration)
        $rows = Database::execute(
            "INSERT INTO backgrounds (room_number, background_name, image_filename, webp_filename, is_active) VALUES (?, ?, ?, ?, 0)",
            [$roomNumber, $backgroundName, $imageFilename, $webpFilename]
        );

        if ($rows > 0) {
            $backgroundId = Database::lastInsertId();
            Response::success(['id' => $backgroundId], 'Background saved successfully');
        } else {
            Response::error('Failed to save background');
        }
    } catch (PDOException $e) {
        Response::serverError('Database error', $e->getMessage());
    }
}

function applyBackground($input)
{
    $roomNumber = normalizeRoomNumberFromInput($input);
    $backgroundId = $input['background_id'] ?? '';

    if ($roomNumber === '' || !preg_match('/^[1-5]$/', $roomNumber) || empty($backgroundId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']);
        return;
    }

    try {
        Database::beginTransaction();

        // Deactivate all backgrounds for this room
        Database::execute("UPDATE backgrounds SET is_active = 0 WHERE room_number = ?", [$roomNumber]);

        // Activate the selected background (by id is sufficient)
        $affected = Database::execute("UPDATE backgrounds SET is_active = 1 WHERE id = ?", [$backgroundId]);

        if ($affected > 0) {
            Database::commit();
            Response::success(null, 'Background applied successfully');
        } else {
            Database::rollBack();
            Response::error('Background not found or invalid room');
        }
    } catch (PDOException $e) {
        Database::rollBack();
        Response::serverError('Database error', $e->getMessage());
    }
}

function handleDelete($input)
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
        $deleted = Database::execute("DELETE FROM backgrounds WHERE id = ?", [$backgroundId]);

        if ($deleted > 0) {
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

function handleUpload($input)
{
    // This would handle file uploads - for now, just return success
    // In a full implementation, this would process uploaded files
    Response::success(null, 'Upload endpoint ready');
}

// Local request handlers using Response helper
function wf_handle_backgrounds_get(): void
{
    try {
        // Accept 'room' or 'room_number' and normalize to numeric string
        $roomNumber = normalizeRoomNumberFromInput($_GET);
        $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';

        if ($roomNumber !== '') {
            $sql = "SELECT * FROM backgrounds WHERE room_number = ?";
            $params = [$roomNumber];
            if ($activeOnly) {
                $sql .= " AND is_active = 1";
            }
            $sql .= " ORDER BY background_name = 'Original' DESC, created_at DESC";
            $rows = Database::queryAll($sql, $params);
            if ($activeOnly && count($rows) > 0) {
                Response::success(['background' => $rows[0]]);
            } else {
                Response::success(['backgrounds' => $rows]);
            }
        } else {
            $summary = Database::queryAll("SELECT room_number AS room_key, COUNT(*) as total_count, SUM(is_active) as active_count, STRING_AGG(CASE WHEN is_active = 1 THEN background_name END, ', ') as active_background FROM backgrounds GROUP BY room_number ORDER BY room_number");
            Response::success(['summary' => $summary]);
        }
    } catch (Throwable $e) {
        Response::serverError('Database error', $e->getMessage());
    }
}

function wf_handle_backgrounds_post(): void
{
    $data = Response::getPostData(true);
    if (!is_array($data)) {
        Response::validationError('Invalid payload');
    }
    $action = $data['action'] ?? '';
    switch ($action) {
        case 'save':
            saveBackground($data);
            return;
        case 'apply':
            applyBackground($data);
            return;
        case 'upload':
            handleUpload($data);
            return;
        default:
            Response::error('Invalid action');
    }
}

function wf_handle_backgrounds_put(): void
{
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
        Database::execute("UPDATE global_css_rules SET css_value = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$cssValue, $id]);
        Response::success(null, 'CSS rule updated successfully');
    } catch (Throwable $e) {
        Response::serverError('Database error', $e->getMessage());
    }
}

function wf_handle_backgrounds_delete(): void
{
    $data = Response::getJsonInput() ?? [];
    handleDelete($data);
}
?> 