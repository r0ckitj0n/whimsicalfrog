#!/usr/bin/env php
<?php
/**
 * Provision Christmas Catalog pages (rooms 20–31) for auto category listing.
 *
 * Idempotent. Safe to re-run.
 *
 * Usage:
 *   php scripts/db/restore_christmas_catalog_pages.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/backgrounds/manager.php';
require_once __DIR__ . '/../../includes/helpers/ImagePathNormalizer.php';
require_once __DIR__ . '/../../includes/christmas_catalog.php';
require_once __DIR__ . '/christmas_catalog_layouts.php';

const CATALOG_ENTRY_AREA = '.area-15';
const CATALOG_SIGN_URL = '/images/signs/realistic/realistic-sign-christmas-catalog.webp';
const PREV_SIGN_URL = '/images/signs/realistic/realistic-sign-catalog-previous-page-v2.webp';
const NEXT_SIGN_URL = '/images/signs/realistic/realistic-sign-catalog-next-page-v2.webp';
const PREV_AREA = '.area-1';
const NEXT_AREA = '.area-2';

function wf_catalog_bg_rel(string $file, string $ext): string
{
    // Keep realistic/ subdirectory — matches existing catalog background rows.
    return "backgrounds/realistic/{$file}.{$ext}";
}

function wf_catalog_bg_url(string $file): string
{
    return '/images/' . wf_catalog_bg_rel($file, 'webp');
}

function wf_upsert_room_settings(string $room, string $title, string $bgUrl, int $displayOrder): void
{
    $existing = Database::queryOne('SELECT id FROM room_settings WHERE room_number = ? LIMIT 1', [$room]);
    $desc = 'Christmas Catalog page — auto-lists 3D Christmas Ornaments';
    if ($existing) {
        Database::execute(
            "UPDATE room_settings
             SET room_name = ?, door_label = ?, description = ?, background_url = ?,
                 target_aspect_ratio = 1.42857, render_context = 'modal',
                 background_display_type = 'fullscreen', show_search_bar = 0,
                 has_icons_white_background = 0, icon_panel_color = 'transparent',
                 icon_vertical_alignment = 'middle', room_role = 'room',
                 display_order = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$title, $title, $desc, $bgUrl, $displayOrder, $existing['id']]
        );
        return;
    }

    Database::execute(
        "INSERT INTO room_settings
            (room_number, room_name, door_label, description, background_url, target_aspect_ratio,
             render_context, background_display_type, show_search_bar, has_icons_white_background,
             icon_panel_color, icon_vertical_alignment, room_role, display_order, is_active)
         VALUES (?, ?, ?, ?, ?, 1.42857, 'modal', 'fullscreen', 0, 0, 'transparent', 'middle', 'room', ?, 1)",
        [$room, $title, $title, $desc, $bgUrl, $displayOrder]
    );
}

function wf_upsert_background(string $room, string $file): int
{
    $pngRel = wf_catalog_bg_rel($file, 'png');
    $webpRel = wf_catalog_bg_rel($file, 'webp');
    $name = "Room {$room} - Christmas Catalog (Dense)";

    $existing = Database::queryOne(
        "SELECT id FROM backgrounds
         WHERE room_number = ?
           AND (webp_filename = ? OR image_filename = ? OR name = ?)
         ORDER BY id DESC LIMIT 1",
        [$room, $webpRel, $pngRel, $name]
    );

    if ($existing) {
        Database::execute(
            "UPDATE backgrounds
             SET name = ?, image_filename = ?, png_filename = ?, webp_filename = ?,
                 theme = 'realistic', updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$name, $pngRel, $pngRel, $webpRel, $existing['id']]
        );
        applyBackground($room, (string) $existing['id']);
        return (int) $existing['id'];
    }

    Database::execute(
        "INSERT INTO backgrounds
            (room_number, name, image_filename, png_filename, webp_filename, is_active, theme)
         VALUES (?, ?, ?, ?, ?, 0, 'realistic')",
        [$room, $name, $pngRel, $pngRel, $webpRel]
    );
    $id = (int) Database::lastInsertId();
    applyBackground($room, (string) $id);
    return $id;
}

/** @param list<array{id:string,top:int,left:int,width:int,height:int,selector:string}> $rects */
function wf_upsert_room_map(string $room, string $mapName, array $rects): void
{
    $coordsJson = json_encode(['rectangles' => array_values($rects)], JSON_UNESCAPED_SLASHES);
    $existing = Database::queryOne(
        'SELECT id FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
        [$room]
    );

    if ($existing) {
        Database::execute(
            'UPDATE room_maps SET map_name = ?, coordinates = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$mapName, $coordsJson, $existing['id']]
        );
        Database::execute(
            'UPDATE room_maps SET is_active = 0 WHERE room_number = ? AND id <> ?',
            [$room, $existing['id']]
        );
        return;
    }

    Database::execute('UPDATE room_maps SET is_active = 0 WHERE room_number = ?', [$room]);
    Database::execute(
        'INSERT INTO room_maps (room_number, map_name, coordinates, is_active) VALUES (?, ?, ?, 1)',
        [$room, $mapName, $coordsJson]
    );
}

function wf_upsert_content_mapping(
    string $room,
    string $area,
    string $targetRoom,
    string $label,
    string $imageUrl,
    int $displayOrder
): void {
    $signUrl = ImagePathNormalizer::normalizeSignUrl($imageUrl);
    $target = 'room:' . $targetRoom;
    $existing = Database::queryOne(
        'SELECT id FROM area_mappings WHERE room_number = ? AND area_selector = ? LIMIT 1',
        [$room, $area]
    );

    if ($existing) {
        Database::execute(
            "UPDATE area_mappings
             SET mapping_type = 'content', content_target = ?, link_label = ?,
                 content_image = ?, link_image = ?, item_sku = NULL, category_id = NULL,
                 link_url = NULL, is_active = 1, display_order = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$target, $label, $signUrl, $signUrl, $displayOrder, $existing['id']]
        );
        return;
    }

    Database::execute(
        "INSERT INTO area_mappings
            (room_number, area_selector, mapping_type, link_label, content_target, content_image, link_image, display_order, is_active)
         VALUES (?, ?, 'content', ?, ?, ?, ?, ?, 1)",
        [$room, $area, $label, $target, $signUrl, $signUrl, $displayOrder]
    );
}

function wf_clear_nav_mapping(string $room, string $area): void
{
    Database::execute(
        "UPDATE area_mappings SET is_active = 0, updated_at = CURRENT_TIMESTAMP
         WHERE room_number = ? AND area_selector = ?",
        [$room, $area]
    );
}

function wf_assign_catalog_category(string $room, string $title): void
{
    Database::execute('DELETE FROM room_category_assignments WHERE room_number = ?', [$room]);
    Database::execute(
        'INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order)
         VALUES (?, ?, ?, 1, 0)',
        [$room, $title, WF_CHRISTMAS_CATALOG_CATEGORY_ID]
    );
}

function wf_point_christmas_entry_to_first_page(string $firstRoom): void
{
    $signUrl = ImagePathNormalizer::normalizeSignUrl(CATALOG_SIGN_URL);
    $existing = Database::queryOne(
        'SELECT id FROM area_mappings WHERE room_number = ? AND area_selector = ? LIMIT 1',
        [WF_CHRISTMAS_ROOM, CATALOG_ENTRY_AREA]
    );

    if ($existing) {
        Database::execute(
            "UPDATE area_mappings
             SET mapping_type = 'content', content_target = ?, link_label = 'Christmas Catalog',
                 content_image = ?, link_image = ?, is_active = 1, display_order = 100,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            ['room:' . $firstRoom, $signUrl, $signUrl, $existing['id']]
        );
        return;
    }

    Database::execute(
        "INSERT INTO area_mappings
            (room_number, area_selector, mapping_type, link_label, content_target, content_image, link_image, display_order, is_active)
         VALUES (?, ?, 'content', 'Christmas Catalog', ?, ?, ?, 100, 1)",
        [WF_CHRISTMAS_ROOM, CATALOG_ENTRY_AREA, 'room:' . $firstRoom, $signUrl, $signUrl]
    );
}

echo "Restoring Christmas Catalog pages (dense auto-list layouts)...\n";

$pages = wf_christmas_catalog_pages();
$firstRoom = $pages[0]['room'];
$lastIdx = count($pages) - 1;

foreach ($pages as $idx => $page) {
    $room = $page['room'];
    $title = $page['title'];
    $file = $page['file'];
    $pageNum = (int) $page['page'];

    $bgUrl = wf_catalog_bg_url($file);
    wf_upsert_room_settings($room, $title, $bgUrl, 200 + $pageNum);
    wf_upsert_background($room, $file);

    $itemSlots = wf_christmas_catalog_item_slots($pageNum);
    $navSlots = wf_christmas_catalog_nav_slots();
    wf_upsert_room_map($room, "Christmas Catalog Page {$pageNum}", array_merge($navSlots, $itemSlots));
    wf_assign_catalog_category($room, $title);

    if ($idx === 0) {
        wf_clear_nav_mapping($room, PREV_AREA);
    } else {
        wf_upsert_content_mapping($room, PREV_AREA, $pages[$idx - 1]['room'], 'Previous Page', PREV_SIGN_URL, 1);
    }
    if ($idx === $lastIdx) {
        wf_clear_nav_mapping($room, NEXT_AREA);
    } else {
        wf_upsert_content_mapping($room, NEXT_AREA, $pages[$idx + 1]['room'], 'Next Page', NEXT_SIGN_URL, 2);
    }

    echo "  room {$room}: {$title} slots=" . count($itemSlots) . "\n";
}

wf_point_christmas_entry_to_first_page($firstRoom);
echo "Room 6 entry → room {$firstRoom}\n";
echo "Done.\n";
