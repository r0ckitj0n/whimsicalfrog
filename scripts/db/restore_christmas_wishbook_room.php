#!/usr/bin/env php
<?php
/**
 * Restore Christmas room (6) catalog background + Wish Book shortcut to room 18.
 *
 * Idempotent. Safe to re-run.
 *
 * Usage:
 *   php scripts/db/restore_christmas_wishbook_room.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/backgrounds/manager.php';
require_once __DIR__ . '/../../includes/helpers/ImagePathNormalizer.php';

const CHRISTMAS_ROOM = '6';
const WISHBOOK_ROOM = '18';
const CATALOG_BACKGROUND_ID = 116;
const WISHBOOK_SIGN_URL = '/images/signs/realistic/realistic-sign-christmas-wishbook.webp';
const WISHBOOK_AREA = '.area-15';

function wf_restore_wishbook_map_coords(): void
{
    $map = Database::queryOne(
        "SELECT id, coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY id DESC LIMIT 1",
        [CHRISTMAS_ROOM]
    );
    if (!$map) {
        throw new RuntimeException('No active room map found for Christmas room 6');
    }

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
        'selector' => WISHBOOK_AREA,
    ];

    $found = false;
    foreach ($coords['rectangles'] as &$rect) {
        if (!is_array($rect)) {
            continue;
        }
        $selector = (string) ($rect['selector'] ?? '');
        $id = (string) ($rect['id'] ?? '');
        if ($selector === WISHBOOK_AREA || $id === 'wishbook-trigger') {
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

function wf_restore_wishbook_mapping(): void
{
    $existing = Database::queryOne(
        "SELECT id FROM area_mappings WHERE room_number = ? AND area_selector = ? LIMIT 1",
        [CHRISTMAS_ROOM, WISHBOOK_AREA]
    );

    $signUrl = ImagePathNormalizer::normalizeSignUrl(WISHBOOK_SIGN_URL);

    if ($existing) {
        Database::execute(
            "UPDATE area_mappings
             SET mapping_type = 'content',
                 content_target = ?,
                 link_label = 'Christmas Wish Book',
                 content_image = ?,
                 link_image = ?,
                 is_active = 1,
                 display_order = 100,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            ['room:' . WISHBOOK_ROOM, $signUrl, $signUrl, $existing['id']]
        );
        return;
    }

    Database::execute(
        "INSERT INTO area_mappings
            (room_number, area_selector, mapping_type, link_label, content_target, content_image, link_image, display_order, is_active)
         VALUES (?, ?, 'content', 'Christmas Wish Book', ?, ?, ?, 100, 1)",
        [CHRISTMAS_ROOM, WISHBOOK_AREA, 'room:' . WISHBOOK_ROOM, $signUrl, $signUrl]
    );
}

try {
    $bg = Database::queryOne(
        'SELECT id, name FROM backgrounds WHERE id = ? AND room_number = ? LIMIT 1',
        [CATALOG_BACKGROUND_ID, CHRISTMAS_ROOM]
    );
    if (!$bg) {
        // Fallback: locate by filename if IDs differ across environments.
        $bg = Database::queryOne(
            "SELECT id, name FROM backgrounds
             WHERE room_number = ?
               AND (webp_filename LIKE '%realistic-room6-catalog%'
                    OR png_filename LIKE '%realistic-room6-catalog%'
                    OR image_filename LIKE '%realistic-room6-catalog%')
             ORDER BY id DESC LIMIT 1",
            [CHRISTMAS_ROOM]
        );
    }
    if (!$bg) {
        throw new RuntimeException('Christmas catalog background not found in backgrounds table');
    }

    applyBackground(CHRISTMAS_ROOM, (string) $bg['id']);
    wf_restore_wishbook_map_coords();
    wf_restore_wishbook_mapping();

    // Ensure navigation connection exists.
    $conn = Database::queryOne(
        'SELECT id FROM room_connections WHERE source_room = ? AND target_room = ? LIMIT 1',
        [CHRISTMAS_ROOM, WISHBOOK_ROOM]
    );
    if (!$conn) {
        Database::execute(
            "INSERT INTO room_connections (source_room, target_room, connection_type, link_created)
             VALUES (?, ?, 'bidirectional', 0)",
            [CHRISTMAS_ROOM, WISHBOOK_ROOM]
        );
    }

    $settings = Database::queryOne(
        'SELECT background_url FROM room_settings WHERE room_number = ?',
        [CHRISTMAS_ROOM]
    );
    $mapping = Database::queryOne(
        "SELECT id, mapping_type, content_target, content_image, is_active
         FROM area_mappings WHERE room_number = ? AND area_selector = ? LIMIT 1",
        [CHRISTMAS_ROOM, WISHBOOK_AREA]
    );

    echo "OK Christmas room restored\n";
    echo '  background: ' . ($bg['name'] ?? '') . " (id {$bg['id']})\n";
    echo '  background_url: ' . ($settings['background_url'] ?? '') . "\n";
    echo '  mapping: ' . json_encode($mapping) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
