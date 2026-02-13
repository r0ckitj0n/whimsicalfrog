<?php
/**
 * Load Room Content API
 * Following .windsurfrules: < 300 lines.
 */

require_once __DIR__ . '/api_bootstrap.php';

// Disable caching for room content to ensure stock updates are reflected immediately
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/room_helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/room_content_generator.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingSchemaHelper.php';
require_once __DIR__ . '/../includes/area_mappings/helpers/AreaMappingFetchHelper.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed('Only GET allowed');
}

$room_number = $_GET['room'] ?? $_GET['room_number'] ?? 'A';
$isModal = isset($_GET['modal']);
$withPerf = isset($_GET['perf']);

// Allow admins to load inactive rooms (e.g., for Room Manager preview)
$includeInactive = isAdmin();

if (!isValidRoom($room_number, $includeInactive)) {
    Response::error('Invalid room', null, 400);
}


try {
    $pdo = Database::getInstance();
    $t0 = microtime(true);

    // Logic for generating HTML content moved to helper to keep shell small
    // In a real refactor, generateRoomContent would be in room_content_generator.php
    // For now, we'll keep the core orchestration here.

    $metadata = getRoomMetadata($room_number, $pdo);
    // Prefer active background; if none active, fall back to the most recent background
    // so the room modal never renders with a blank/white background.
    $bgMeta = Database::queryOne(
        "SELECT name, image_filename, webp_filename
         FROM backgrounds
         WHERE room_number = ?
         ORDER BY is_active DESC, id DESC
         LIMIT 1",
        [$room_number]
    ) ?: null;

    // Use manually set background_url from room_settings if present
    if (!empty($metadata['background_url'])) {
        if (!$bgMeta) {
            $bgMeta = [
                'name' => 'Custom Background',
                'image_filename' => $metadata['background_url'],
                'webp_filename' => null
            ];
        } else {
            // Override the filename with the custom URL
            $bgMeta['image_filename'] = $metadata['background_url'];
            $bgMeta['webp_filename'] = null;
        }
    }

    if (isset($_GET['hide_bg'])) {
        $bgMeta = null;
        if (isset($metadata)) {
            $metadata['background_url'] = null;
        }
    }

    // fetch room settings for panel color
    $rs = Database::queryOne("SELECT icon_panel_color, has_icons_white_background FROM room_settings WHERE room_number = ?", [$room_number]) ?: [];
    $panelColor = wf_normalize_icon_panel_color_value($rs['icon_panel_color'] ?? null);

    // Fetch mappings (derived + explicit)
    $map_id = $_GET['map_id'] ?? null;
    $viewData = AreaMappingFetchHelper::getLiveView($room_number, false, $map_id);
    $mappings = $viewData['mappings'] ?? [];

    // Generate HTML content for the room
    $is_bare = ($_GET['bare'] ?? $_GET['is_bare'] ?? '0') === '1';
    $hide_items_param = ($_GET['hide_items'] ?? '0') === '1';
    $should_hide = $is_bare || $hide_items_param;

    $htmlContent = '<div class="room-items-container room-items-container--api">';

    // Only generate items if NOT in bare mode or hide_items mode
    if (!$should_hide) {
        foreach ($mappings as $index => $mapping) {
            $type = $mapping['mapping_type'] ?? 'item';
            $selector = $mapping['area_selector'] ?? '';
            $imgUrl = $mapping['image_url'] ?? $mapping['content_image'] ?? $mapping['link_image'] ?? '/images/items/placeholder.webp';
            $coords = $mapping['coords'] ?? null;

            $coordAttrs = '';
            $inlineStyle = '';
            if ($coords) {
                $t = $coords['top'] ?? 0;
                $l = $coords['left'] ?? 0;
                $w = $coords['width'] ?? 80;
                $h = $coords['height'] ?? 80;
                $coordAttrs = sprintf(
                    'data-original-top="%s" data-original-left="%s" data-original-width="%s" data-original-height="%s"',
                    $t,
                    $l,
                    $w,
                    $h
                );
                $inlineStyle = sprintf(
                    'data-top="%s" data-left="%s" data-width="%s" data-height="%s" data-icon-width="%s" data-icon-height="%s"',
                    $t,
                    $l,
                    $w,
                    $h,
                    $w,
                    $h
                );
            }

            $selectorClass = str_replace('.', '', $selector);

            if ($type === 'item') {
                $sku = $mapping['item_sku'] ?? $mapping['sku'] ?? '';
                $name = $mapping['name'] ?? '';
                $price = $mapping['price'] ?? 0;
                $stock = $mapping['stock_quantity'] ?? 0;

                $itemData = htmlspecialchars(json_encode([
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $price,
                    'stock_quantity' => $stock,
                    'image' => $imgUrl
                ]), ENT_QUOTES);

                $htmlContent .= sprintf(
                    '<div class="room-item room-item-icon %s %s" data-sku="%s" data-item=\'%s\' data-action="openItemModal" data-params=\'{"sku":"%s"}\' %s %s>
                    <img src="%s" alt="%s" loading="lazy">
                    %s
                </div>',
                    $selectorClass,
                    $stock <= 0 ? 'sold-out' : '',
                    htmlspecialchars($sku),
                    $itemData,
                    htmlspecialchars($sku),
                    $coordAttrs,
                    $inlineStyle,
                    htmlspecialchars($imgUrl),
                    htmlspecialchars($name),
                    $stock <= 0 ? '<span class="room-item-oos-badge">Out of Stock</span>' : ''
                );
            } elseif ($type === 'category') {
                $categoryId = $mapping['category_id'] ?? '';
                $label = $mapping['link_label'] ?? 'Category';
                $htmlContent .= sprintf(
                    '<div class="room-item room-item-icon room-item-category %s" data-action="navigateToCategory" data-category-id="%s" data-params=\'{"category_id":"%s"}\' %s %s>
                    <img src="%s" alt="%s" loading="lazy">
                </div>',
                    $selectorClass,
                    htmlspecialchars($categoryId),
                    htmlspecialchars($categoryId),
                    $coordAttrs,
                    $inlineStyle,
                    htmlspecialchars($imgUrl),
                    htmlspecialchars($label)
                );
            } elseif ($type === 'link') {
                $url = $mapping['link_url'] ?? '#';
                $label = $mapping['link_label'] ?? 'Link';
                $htmlContent .= sprintf(
                    '<a href="%s" class="room-item room-item-icon room-item-link %s" target="_blank" rel="noopener noreferrer" %s %s>
                    <img src="%s" alt="%s" loading="lazy">
                </a>',
                    htmlspecialchars($url),
                    $selectorClass,
                    $coordAttrs,
                    $inlineStyle,
                    htmlspecialchars($imgUrl),
                    htmlspecialchars($label)
                );
            } elseif ($type === 'page') {
                $target = $mapping['content_target'] ?? $mapping['link_url'] ?? '/';
                $label = $mapping['link_label'] ?? 'Page';
                $htmlContent .= sprintf(
                    '<a href="%s" class="room-item room-item-icon room-item-page %s" %s %s>
                    <img src="%s" alt="%s" loading="lazy">
                </a>',
                    htmlspecialchars($target),
                    $selectorClass,
                    $coordAttrs,
                    $inlineStyle,
                    htmlspecialchars($imgUrl),
                    htmlspecialchars($label)
                );
            } elseif ($type === 'modal') {
                $modalId = $mapping['content_target'] ?? '';
                $label = $mapping['link_label'] ?? 'Open Modal';
                $htmlContent .= sprintf(
                    '<div class="room-item room-item-icon room-item-modal %s" data-action="openModal" data-modal-id="%s" data-params=\'{"modal":"%s"}\' %s %s>
                    <img src="%s" alt="%s" loading="lazy">
                </div>',
                    $selectorClass,
                    htmlspecialchars($modalId),
                    htmlspecialchars($modalId),
                    $coordAttrs,
                    $inlineStyle,
                    htmlspecialchars($imgUrl),
                    htmlspecialchars($label)
                );
            } elseif ($type === 'action') {
                $actionId = $mapping['content_target'] ?? '';
                $label = $mapping['link_label'] ?? 'Action';
                $htmlContent .= sprintf(
                    '<div class="room-item room-item-icon room-item-action %s" data-action="%s" data-params=\'{"action":"%s"}\' %s %s>
                    <img src="%s" alt="%s" loading="lazy">
                </div>',
                    $selectorClass,
                    htmlspecialchars($actionId),
                    htmlspecialchars($actionId),
                    $coordAttrs,
                    $inlineStyle,
                    htmlspecialchars($imgUrl),
                    htmlspecialchars($label)
                );
            } elseif ($type === 'content' || $type === 'button') {
                // content/button types - use content_target as a room shortcut
                $target = $mapping['content_target'] ?? '';
                $label = $mapping['link_label'] ?? 'Content';
                // Check if target is a room reference
                if (preg_match('/^room:(\w+)$/', $target, $matches)) {
                    $roomNum = $matches[1];
                    $htmlContent .= sprintf(
                        '<div class="room-item room-item-icon room-item-shortcut %s" data-action="openRoom" data-room="%s" data-params=\'{"room":"%s"}\' %s %s>
                        <img src="%s" alt="%s" loading="lazy">
                    </div>',
                        $selectorClass,
                        htmlspecialchars($roomNum),
                        htmlspecialchars($roomNum),
                        $coordAttrs,
                        $inlineStyle,
                        htmlspecialchars($imgUrl),
                        htmlspecialchars($label)
                    );
                } else {
                    // Fallback to page navigation
                    $htmlContent .= sprintf(
                        '<a href="%s" class="room-item room-item-icon room-item-content %s" %s %s>
                        <img src="%s" alt="%s" loading="lazy">
                    </a>',
                        htmlspecialchars($target ?: '/'),
                        $selectorClass,
                        $coordAttrs,
                        $inlineStyle,
                        htmlspecialchars($imgUrl),
                        htmlspecialchars($label)
                    );
                }
            }
        }
    }
    $htmlContent .= '</div>';

    Response::success([
        'content' => $htmlContent,
        'room_number' => $room_number,
        'metadata' => $metadata,
        'panel_color' => $panelColor,
        'render_context' => $metadata['render_context'] ?? 'modal',
        'target_aspect_ratio' => $metadata['target_aspect_ratio'] ?? null,
        'is_modal' => $isModal,
        'background' => $bgMeta
    ]);
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
