<?php
/**
 * Area Mappings Management API - Conductor
 * Handles mapping of visual areas to items, categories, links, or content.
 * Delegating logic to specialized helper classes in includes/area_mappings/helpers/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/ai_image_processor.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingSchemaHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingFetchHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingActionHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingSitemapHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingUploadHelper.php';

// Ensure JSON content-type
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    Database::getInstance();
    AreaMappingSchemaHelper::ensureSchema();
} catch (Exception $e) {
    Response::serverError('Database initialization failed: ' . $e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'get_mapping_row':
                    $id = $_GET['id'] ?? null;
                    if (!$id)
                        Response::error('id is required', null, 400);
                    $row = Database::queryOne("SELECT am.*, COALESCE(img.image_path, i.image_url) AS image_url FROM area_mappings am LEFT JOIN items i ON am.item_sku = i.sku LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1 WHERE am.id = ?", [$id]);
                    if ($row)
                        Response::success(['mapping' => $row]);
                    else
                        Response::notFound('Mapping not found');
                    break;

                case 'list_room_raw':
                    $room = $_GET['room'] ?? $_GET['room_number'] ?? '';
                    if ($room === '')
                        Response::error('room is required', null, 400);
                    $rows = Database::queryAll("SELECT am.*, COALESCE(img.image_path, i.image_url) AS image_url FROM area_mappings am LEFT JOIN items i ON am.item_sku = i.sku LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1 WHERE am.room_number COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci ORDER BY am.display_order, am.id", [$room]);
                    Response::success(['mappings' => $rows]);
                    break;

                case 'get_room_coordinates':
                    $room = $_GET['room'] ?? $_GET['room_number'] ?? null;
                    if ($room === null)
                        Response::error('room is required', null, 400);
                    $room = AreaMappingFetchHelper::normalizeRoomNumber($room);
                    $where = 'room_number = ?';
                    if (AreaMappingSchemaHelper::hasColumn('room_maps', 'is_active'))
                        $where .= ' AND is_active = 1';
                    $map = Database::queryOne("SELECT coordinates FROM room_maps WHERE $where ORDER BY updated_at DESC LIMIT 1", [$room]);
                    $coords = $map ? json_decode($map['coordinates'], true) : [];

                    // Handle double-encoded coordinates (legacy bug fix)
                    if (is_string($coords)) {
                        $coords = json_decode($coords, true);
                    }

                    // Normalize various coordinate formats
                    if (is_array($coords)) {
                        if (isset($coords['rectangles'])) {
                            $coords = $coords['rectangles'];
                        } elseif (isset($coords['polygons'])) {
                            $coords = $coords['polygons'];
                        }
                        // Ensure each coordinate has a selector field
                        $coords = array_values(array_map(function ($coord, $idx) {
                            if (!isset($coord['selector']) || empty($coord['selector'])) {
                                $coord['selector'] = $coord['id'] ?? ('area-' . ($idx + 1));
                            }
                            return $coord;
                        }, (array) $coords, array_keys((array) $coords)));
                    }

                    Response::success(['coordinates' => array_values((array) $coords)]);
                    break;

                case 'get_mappings':
                    $room = $_GET['room'] ?? $_GET['room_number'] ?? null;
                    if ($room === null)
                        Response::error('room is required', null, 400);
                    $rows = AreaMappingFetchHelper::fetchMappings($room);
                    Response::success(['mappings' => $rows]);
                    break;

                case 'get_live_view':
                    $room = $_GET['room'] ?? $_GET['room_number'] ?? null;
                    if ($room === null)
                        Response::error('room is required', null, 400);
                    $debug = (isset($_GET['debug']) && $_GET['debug'] == '1');
                    Response::json(AreaMappingFetchHelper::getLiveView($room, $debug));
                    break;

                case 'get_sitemap_entries':
                    Response::success(['entries' => AreaMappingSitemapHelper::getSitemapEntries()]);
                    break;

                case 'door_sign_destinations':
                    $room = $_GET['room'] ?? $_GET['room_number'] ?? '0';
                    Response::success(['destinations' => AreaMappingSitemapHelper::getDoorSignDestinationsForRoom($room)]);
                    break;

                default:
                    Response::error('Invalid action', null, 400);
            }
            break;

        case 'POST':
            if ($action === 'upload_content_image') {
                Response::success(['image_url' => AreaMappingUploadHelper::handleUpload()]);
            } elseif ($action === 'add_mapping') {
                Response::success(AreaMappingActionHelper::addMapping($input));
            } elseif ($action === 'update_mapping') {
                Response::json(AreaMappingActionHelper::updateMapping($input));
            } elseif ($action === 'delete_mapping') {
                Response::json(AreaMappingActionHelper::deleteMapping($input['id'] ?? $input['area_id'] ?? null));
            } elseif ($action === 'swap') {
                Response::success(AreaMappingActionHelper::swapMappings($input['area1_id'] ?? null, $input['area2_id'] ?? null));
            } else {
                Response::error('Invalid action: ' . $action, null, 400);
            }
            break;

        case 'PUT':
            Response::json(AreaMappingActionHelper::updateMapping($input));
            break;

        case 'DELETE':
            Response::json(AreaMappingActionHelper::deleteMapping($input['id'] ?? null));
            break;

        default:
            Response::methodNotAllowed();
    }
} catch (Throwable $e) {
    error_log("Area Mappings API Error: " . $e->getMessage());
    $code = (int) $e->getCode();
    if ($code >= 400 && $code < 500) {
        Response::error($e->getMessage(), null, $code);
    }
    if ($e instanceof Exception) {
        Response::error($e->getMessage(), null, 400);
    }
    Response::serverError('Area mappings request failed: ' . $e->getMessage());
}
