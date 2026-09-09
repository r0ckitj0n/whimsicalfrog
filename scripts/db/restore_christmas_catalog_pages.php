#!/usr/bin/env php
<?php
/**
 * Provision 12 Christmas Catalog page rooms (20–31).
 *
 * Each page is its own room with a unique Sears/Amazon-style catalog background
 * and Previous/Next page signs that navigate between adjacent catalog rooms.
 * Room 6 Christmas Catalog hotspot opens page 1 (room 20).
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
require_once __DIR__ . '/christmas_catalog_layouts.php';

const CHRISTMAS_ROOM = '6';
const CATALOG_ENTRY_AREA = '.area-15';
const CATALOG_SIGN_URL = '/images/signs/realistic/realistic-sign-christmas-catalog.webp';
const PREV_SIGN_URL = '/images/signs/realistic/realistic-sign-catalog-previous-page-v2.webp';
const NEXT_SIGN_URL = '/images/signs/realistic/realistic-sign-catalog-next-page-v2.webp';
const PREV_AREA = '.area-1';
const NEXT_AREA = '.area-2';

/** @var list<array{room:string,page:int,title:string,file:string}> */
const CATALOG_PAGES = [
    ['room' => '20', 'page' => 1, 'title' => 'Christmas Catalog — Cover', 'file' => 'realistic-room20-christmas-catalog-p01'],
    ['room' => '21', 'page' => 2, 'title' => 'Christmas Catalog — Ornaments', 'file' => 'realistic-room21-christmas-catalog-p02'],
    ['room' => '22', 'page' => 3, 'title' => 'Christmas Catalog — Tree Trimmings', 'file' => 'realistic-room22-christmas-catalog-p03'],
    ['room' => '23', 'page' => 4, 'title' => 'Christmas Catalog — Lights & Sparkle', 'file' => 'realistic-room23-christmas-catalog-p04'],
    ['room' => '24', 'page' => 5, 'title' => 'Christmas Catalog — Mantel & Stockings', 'file' => 'realistic-room24-christmas-catalog-p05'],
    ['room' => '25', 'page' => 6, 'title' => 'Christmas Catalog — Gifts Under the Tree', 'file' => 'realistic-room25-christmas-catalog-p06'],
    ['room' => '26', 'page' => 7, 'title' => 'Christmas Catalog — Table & Centerpieces', 'file' => 'realistic-room26-christmas-catalog-p07'],
    ['room' => '27', 'page' => 8, 'title' => 'Christmas Catalog — Kitchen Cheer', 'file' => 'realistic-room27-christmas-catalog-p08'],
    ['room' => '28', 'page' => 9, 'title' => 'Christmas Catalog — Kids & Toys', 'file' => 'realistic-room28-christmas-catalog-p09'],
    ['room' => '29', 'page' => 10, 'title' => 'Christmas Catalog — Cozy Apparel', 'file' => 'realistic-room29-christmas-catalog-p10'],
    ['room' => '30', 'page' => 11, 'title' => 'Christmas Catalog — Outdoor Yard', 'file' => 'realistic-room30-christmas-catalog-p11'],
    ['room' => '31', 'page' => 12, 'title' => 'Christmas Catalog — Wishlist Finale', 'file' => 'realistic-room31-christmas-catalog-p12'],
];

function wf_bg_db_ref(string $file, string $ext): string
{
    return ImagePathNormalizer::normalizeBackgroundDbRef("backgrounds/realistic/{$file}.{$ext}");
}

function wf_upsert_room_settings(string $room, string $title, string $bgUrl, int $displayOrder): void
{
    $existing = Database::queryOne(
        'SELECT id FROM room_settings WHERE room_number = ? LIMIT 1',
        [$room]
    );

    $paramsCommon = [
        $title,
        $title,
        'Christmas Catalog page — browse holiday gift pages',
        $bgUrl,
        $displayOrder,
    ];

    if ($existing) {
        Database::execute(
            "UPDATE room_settings
             SET room_name = ?,
                 door_label = ?,
                 description = ?,
                 background_url = ?,
                 target_aspect_ratio = 1.42857,
                 render_context = 'modal',
                 background_display_type = 'fullscreen',
                 show_search_bar = 0,
                 has_icons_white_background = 0,
                 icon_panel_color = 'transparent',
                 icon_vertical_alignment = 'middle',
                 room_role = 'room',
                 display_order = ?,
                 is_active = 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [...$paramsCommon, $existing['id']]
        );
        return;
    }

    Database::execute(
        "INSERT INTO room_settings
            (room_number, room_name, door_label, description, background_url, target_aspect_ratio,
             render_context, background_display_type, show_search_bar, has_icons_white_background,
             icon_panel_color, icon_vertical_alignment, room_role, display_order, is_active)
         VALUES (?, ?, ?, ?, ?, 1.42857, 'modal', 'fullscreen', 0, 0, 'transparent', 'middle', 'room', ?, 1)",
        [$room, ...$paramsCommon]
    );
}

function wf_upsert_background(string $room, string $file): int
{
    $pngRel = wf_bg_db_ref($file, 'png');
    $webpRel = wf_bg_db_ref($file, 'webp');
    $name = "Room {$room} - Christmas Catalog (Realistic)";

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
             SET name = ?,
                 image_filename = ?,
                 png_filename = ?,
                 webp_filename = ?,
                 theme = 'realistic',
                 updated_at = CURRENT_TIMESTAMP
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

/**
 * @param list<array{id:string,top:int,left:int,width:int,height:int,selector:string}> $rects
 */
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
        "INSERT INTO room_maps (room_number, map_name, coordinates, is_active)
         VALUES (?, ?, ?, 1)",
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
             SET mapping_type = 'content',
                 content_target = ?,
                 link_label = ?,
                 content_image = ?,
                 link_image = ?,
                 item_sku = NULL,
                 category_id = NULL,
                 link_url = NULL,
                 is_active = 1,
                 display_order = ?,
                 updated_at = CURRENT_TIMESTAMP
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
        "UPDATE area_mappings
         SET is_active = 0, updated_at = CURRENT_TIMESTAMP
         WHERE room_number = ? AND area_selector = ? AND mapping_type = 'content'",
        [$room, $area]
    );
}

function wf_ensure_connection(string $source, string $target): void
{
    $conn = Database::queryOne(
        'SELECT id FROM room_connections WHERE source_room = ? AND target_room = ? LIMIT 1',
        [$source, $target]
    );
    if ($conn) {
        return;
    }
    Database::execute(
        "INSERT INTO room_connections (source_room, target_room, connection_type, link_created)
         VALUES (?, ?, 'bidirectional', 0)",
        [$source, $target]
    );
}


function wf_seed_realistic_christmas_catalog_prompt(): void
{
    $prompt = <<<'PROMPT'
{{image_style_declaration}} Christmas Catalog page {{room_number}}.
Room name: {{room_name}}.
Door label: {{door_label}}.
Display order: {{display_order}}.
Room description/context: {{room_description}}.

Create a themed {{scene_type}} with a {{room_theme}} direction {{location_phrase}}.

The page is a photorealistic mid-1950s American Christmas mail-order catalog spread printed on warm cream aged paper with visible paper fiber grain and soft period print screening. Include a subtle center fold crease and thin festive red/green outer border.
The area features prominent {{display_furniture_style}} intended for future product placement.
{{critical_constraint_line}}
{{no_props_line}}
{{decorative_elements_line}}
{{open_display_zones_line}}

Leave a generous clear empty cream margin across the entire bottom 20 percent of the page for previous/next navigation buttons. Do not place frames in that bottom band.

{{character_statement}}

Atmosphere: {{vibe_adjectives}}.
Color palette: {{color_scheme}}.
{{aesthetic_statement}}

{{art_style_line}}
{{surfaces_line}}
{{text_constraint_line}}
{{lighting_line}}
PROMPT;

    $existing = Database::queryOne(
        'SELECT id FROM ai_prompt_templates WHERE template_key = ? LIMIT 1',
        ['realistic_christmas_catalog_page']
    );
    $name = 'Realistic Christmas Catalog Page';
    $desc = 'Photorealistic 1950s Christmas catalog page spreads with empty product frames, no text, and clear bottom nav band.';
    if ($existing) {
        Database::execute(
            'UPDATE ai_prompt_templates
             SET template_name = ?, description = ?, prompt_text = ?, context_type = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?',
            [$name, $desc, $prompt, 'room', $existing['id']]
        );
        return;
    }
    Database::execute(
        'INSERT INTO ai_prompt_templates (template_key, template_name, description, context_type, prompt_text, is_active)
         VALUES (?, ?, ?, ?, ?, 1)',
        ['realistic_christmas_catalog_page', $name, $desc, 'room', $prompt]
    );
}

function wf_point_christmas_entry_to_first_page(string $firstRoom): void
{
    $signUrl = ImagePathNormalizer::normalizeSignUrl(CATALOG_SIGN_URL);
    $existing = Database::queryOne(
        'SELECT id FROM area_mappings WHERE room_number = ? AND area_selector = ? LIMIT 1',
        [CHRISTMAS_ROOM, CATALOG_ENTRY_AREA]
    );

    if ($existing) {
        Database::execute(
            "UPDATE area_mappings
             SET mapping_type = 'content',
                 content_target = ?,
                 link_label = 'Christmas Catalog',
                 content_image = ?,
                 link_image = ?,
                 is_active = 1,
                 display_order = 100,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            ['room:' . $firstRoom, $signUrl, $signUrl, $existing['id']]
        );
    } else {
        Database::execute(
            "INSERT INTO area_mappings
                (room_number, area_selector, mapping_type, link_label, content_target, content_image, link_image, display_order, is_active)
             VALUES (?, ?, 'content', 'Christmas Catalog', ?, ?, ?, 100, 1)",
            [CHRISTMAS_ROOM, CATALOG_ENTRY_AREA, 'room:' . $firstRoom, $signUrl, $signUrl]
        );
    }

    $map = Database::queryOne(
        'SELECT id, coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
        [CHRISTMAS_ROOM]
    );
    if ($map) {
        $coords = json_decode((string) $map['coordinates'], true);
        if (!is_array($coords)) {
            $coords = ['rectangles' => []];
        }
        if (!isset($coords['rectangles']) || !is_array($coords['rectangles'])) {
            $coords['rectangles'] = [];
        }

        $wishbookRect = [
            'id' => 'wishbook-trigger',
            'top' => 278,
            'left' => 557,
            'width' => 165,
            'height' => 200,
            'selector' => CATALOG_ENTRY_AREA,
        ];

        $found = false;
        foreach ($coords['rectangles'] as &$rect) {
            if (!is_array($rect)) {
                continue;
            }
            $selector = (string) ($rect['selector'] ?? '');
            $id = (string) ($rect['id'] ?? '');
            if ($selector === CATALOG_ENTRY_AREA || $id === 'wishbook-trigger') {
                $rect = array_merge($rect, $wishbookRect);
                $found = true;
                break;
            }
        }
        unset($rect);

        if (!$found) {
            $coords['rectangles'][] = $wishbookRect;
        }

        Database::execute(
            'UPDATE room_maps SET coordinates = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [json_encode($coords), $map['id']]
        );
    }

    wf_ensure_connection(CHRISTMAS_ROOM, $firstRoom);
}

try {
    wf_seed_realistic_christmas_catalog_prompt();
    $pageCount = count(CATALOG_PAGES);
    $firstRoom = CATALOG_PAGES[0]['room'];

    foreach (CATALOG_PAGES as $index => $page) {
        $room = $page['room'];
        $title = $page['title'];
        $file = $page['file'];
        $bgUrl = ImagePathNormalizer::normalizeBackgroundUrl(wf_bg_db_ref($file, 'webp'));
        $displayOrder = 70 + $page['page'];

        wf_upsert_room_settings($room, $title, $bgUrl, $displayOrder);
        $bgId = wf_upsert_background($room, $file);

        $hasPrev = $index > 0;
        $hasNext = $index < ($pageCount - 1);

        // Unique per-page item frames stay in the upper content band;
        // prev/next plaques sit in the intentional bottom nav band.
        $rects = array_merge(
            wf_christmas_catalog_item_slots((int) $page['page']),
            wf_christmas_catalog_nav_rects($hasPrev, $hasNext)
        );

        wf_upsert_room_map($room, "Christmas Catalog Page {$page['page']}", $rects);

        if ($hasPrev) {
            $prevRoom = CATALOG_PAGES[$index - 1]['room'];
            wf_upsert_content_mapping(
                $room,
                PREV_AREA,
                $prevRoom,
                'Previous Page',
                PREV_SIGN_URL,
                10
            );
            wf_ensure_connection($room, $prevRoom);
        } else {
            wf_clear_nav_mapping($room, PREV_AREA);
        }

        if ($hasNext) {
            $nextRoom = CATALOG_PAGES[$index + 1]['room'];
            wf_upsert_content_mapping(
                $room,
                NEXT_AREA,
                $nextRoom,
                'Next Page',
                NEXT_SIGN_URL,
                20
            );
            wf_ensure_connection($room, $nextRoom);
        } else {
            wf_clear_nav_mapping($room, NEXT_AREA);
        }

        echo "OK room {$room} (page {$page['page']}) bg#{$bgId} — {$title}\n";
    }

    wf_point_christmas_entry_to_first_page($firstRoom);

    $entry = Database::queryOne(
        'SELECT content_target, link_label, is_active FROM area_mappings WHERE room_number = ? AND area_selector = ? LIMIT 1',
        [CHRISTMAS_ROOM, CATALOG_ENTRY_AREA]
    );

    echo "OK Christmas Catalog pages restored ({$pageCount} rooms)\n";
    echo '  entry mapping: ' . json_encode($entry) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
