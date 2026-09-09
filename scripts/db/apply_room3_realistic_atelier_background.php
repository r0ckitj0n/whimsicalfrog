<?php
/**
 * Apply the Custom Artwork (room 3) realistic atelier background.
 *
 * Idempotent: inserts the library row if missing, then activates it and
 * syncs room_settings.background_url.
 *
 * Usage:
 *   php scripts/db/apply_room3_realistic_atelier_background.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/backgrounds/manager.php';

const ROOM_NUMBER = '3';
const BACKGROUND_NAME = 'Room 3 - Custom Art Atelier (Realistic)';
const PNG_REL = 'backgrounds/realistic-room3-custom-art-atelier-184573.png';
const WEBP_REL = 'backgrounds/realistic-room3-custom-art-atelier-184573.webp';
const THEME = 'realistic';

$imagesRoot = realpath(__DIR__ . '/../../images') ?: (__DIR__ . '/../../images');
$pngAbs = $imagesRoot . '/' . PNG_REL;
$webpAbs = $imagesRoot . '/' . WEBP_REL;

if (!is_file($pngAbs) || !is_file($webpAbs)) {
    fwrite(STDERR, "Missing background files:\n  {$pngAbs}\n  {$webpAbs}\n");
    exit(1);
}

$existing = Database::queryOne(
    'SELECT id FROM backgrounds WHERE room_number = ? AND name = ? LIMIT 1',
    [ROOM_NUMBER, BACKGROUND_NAME]
);

if ($existing) {
    $id = (int) $existing['id'];
    Database::execute(
        'UPDATE backgrounds SET image_filename = ?, png_filename = ?, webp_filename = ?, theme = ? WHERE id = ?',
        [PNG_REL, PNG_REL, WEBP_REL, THEME, $id]
    );
    echo "Updated existing background id={$id}\n";
} else {
    Database::execute(
        'INSERT INTO backgrounds (room_number, name, image_filename, png_filename, webp_filename, is_active, theme) VALUES (?, ?, ?, ?, ?, 0, ?)',
        [ROOM_NUMBER, BACKGROUND_NAME, PNG_REL, PNG_REL, WEBP_REL, THEME]
    );
    $id = (int) Database::lastInsertId();
    echo "Inserted background id={$id}\n";
}

applyBackground(ROOM_NUMBER, (string) $id);

$row = Database::queryOne(
    'SELECT id, name, image_filename, webp_filename, is_active, theme FROM backgrounds WHERE id = ?',
    [$id]
);
$settings = Database::queryOne(
    'SELECT background_url FROM room_settings WHERE room_number = ?',
    [ROOM_NUMBER]
);

echo "Active background:\n";
print_r($row);
echo "room_settings.background_url=" . ($settings['background_url'] ?? '') . "\n";
echo "Done.\n";
