<?php
/**
 * Area Mappings Management API - Conductor
 * Handles mapping of visual areas to items, categories, links, or content.
 * Delegating logic to specialized helper classes in includes/area_mappings/helpers/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/ai_image_processor.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingSchemaHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingFetchHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingActionHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingSitemapHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingUploadHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingSignHelper.php';

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
$action = trim((string) ($_GET['action'] ?? $input['action'] ?? ''));

$allowedGetActions = [
    'get_mapping_row',
    'list_room_raw',
    'get_room_coordinates',
    'get_mappings',
    'get_live_view',
    'get_sitemap_entries',
    'door_sign_destinations',
    'get_shortcut_sign_assets',
];
$allowedPostActions = [
    'upload_content_image',
    'generate_shortcut_image',
    'add_mapping',
    'update_mapping',
    'delete_mapping',
    'swap',
    'set_shortcut_sign_active',
    'delete_shortcut_sign',
];

try {
    switch ($method) {
        case 'GET':
            if (!in_array($action, $allowedGetActions, true)) {
                Response::error('Invalid action', null, 400);
            }
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
                    $rawCoords = $map['coordinates'] ?? '[]';
                    $coords = $rawCoords;

                    // Decode nested legacy payloads (double/triple encoded JSON).
                    for ($i = 0; $i < 4 && is_string($coords); $i++) {
                        $decoded = json_decode($coords, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            break;
                        }
                        $coords = $decoded;
                    }

                    if (is_array($coords) && isset($coords['rectangles']) && is_string($coords['rectangles'])) {
                        $decodedRects = json_decode($coords['rectangles'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $coords['rectangles'] = $decodedRects;
                        }
                    }
                    if (is_array($coords) && isset($coords['polygons']) && is_string($coords['polygons'])) {
                        $decodedPolygons = json_decode($coords['polygons'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $coords['polygons'] = $decodedPolygons;
                        }
                    }

                    $list = [];
                    if (is_array($coords)) {
                        if (isset($coords['rectangles']) && is_array($coords['rectangles'])) {
                            $list = $coords['rectangles'];
                        } elseif (isset($coords['polygons']) && is_array($coords['polygons'])) {
                            $list = $coords['polygons'];
                        } elseif (isset($coords['coordinates']) && is_array($coords['coordinates'])) {
                            $list = $coords['coordinates'];
                        } elseif (array_values($coords) === $coords) {
                            $list = $coords;
                        }
                    }

                    // Ensure each coordinate is an array row with a selector.
                    $normalized = [];
                    $idx = 0;
                    foreach ($list as $coord) {
                        if (!is_array($coord)) {
                            continue;
                        }
                        if (!isset($coord['selector']) || $coord['selector'] === '') {
                            $coord['selector'] = $coord['id'] ?? ('area-' . ($idx + 1));
                        }
                        $normalized[] = $coord;
                        $idx++;
                    }

                    Response::success(['coordinates' => array_values($normalized)]);
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
                case 'get_shortcut_sign_assets':
                    $room = $_GET['room'] ?? $_GET['room_number'] ?? '';
                    $mappingId = (int) ($_GET['mapping_id'] ?? 0);
                    if ($room === '' || $mappingId <= 0) {
                        Response::error('room and mapping_id are required', null, 400);
                    }
                    $assets = AreaMappingSignHelper::fetchAssets($mappingId, $room);
                    Response::success(['assets' => $assets]);
                    break;

                default:
                    Response::error('Invalid action', null, 400);
            }
            break;

        case 'POST':
            if (!in_array($action, $allowedPostActions, true)) {
                Response::error('Invalid action', null, 400);
            }
            requireAdmin(true);
            if ($action === 'upload_content_image') {
                Response::success(['image_url' => AreaMappingUploadHelper::handleUpload()]);
            } elseif ($action === 'generate_shortcut_image') {
                Response::success(AreaMappingUploadHelper::handleGenerateShortcutImage($input));
            } elseif ($action === 'add_mapping') {
                Response::success(AreaMappingActionHelper::addMapping($input));
            } elseif ($action === 'update_mapping') {
                Response::json(AreaMappingActionHelper::updateMapping($input));
            } elseif ($action === 'delete_mapping') {
                Response::json(AreaMappingActionHelper::deleteMapping($input['id'] ?? $input['area_id'] ?? null));
            } elseif ($action === 'swap') {
                Response::success(AreaMappingActionHelper::swapMappings($input['area1_id'] ?? null, $input['area2_id'] ?? null));
            } elseif ($action === 'set_shortcut_sign_active') {
                $room = (string) ($input['room'] ?? $input['room_number'] ?? '');
                $mappingId = (int) ($input['mapping_id'] ?? 0);
                $assetId = (int) ($input['asset_id'] ?? 0);
                $asset = AreaMappingSignHelper::setActiveAsset($mappingId, $assetId, $room);
                Response::success(['asset' => $asset]);
            } elseif ($action === 'delete_shortcut_sign') {
                $room = (string) ($input['room'] ?? $input['room_number'] ?? '');
                $mappingId = (int) ($input['mapping_id'] ?? 0);
                $assetId = (int) ($input['asset_id'] ?? 0);
                $assets = AreaMappingSignHelper::deleteAsset($mappingId, $assetId, $room);
                Response::success(['assets' => $assets]);
            } else {
                Response::error('Invalid action: ' . $action, null, 400);
            }
            break;

        case 'PUT':
            requireAdmin(true);
            Response::json(AreaMappingActionHelper::updateMapping($input));
            break;

        case 'DELETE':
            requireAdmin(true);
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
