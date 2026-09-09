<?php
/**
 * Activate the UHD landing background for room A.
 * Usage: php scripts/db/activate_landing_uhd_background.php
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/config.php';

$png = 'backgrounds/realistic/realistic-roomA-frogs-uhd.png';
$webp = 'backgrounds/realistic/realistic-roomA-frogs-uhd.webp';
$name = 'Landing - Cabin Porch with Frogs (UHD)';

Database::execute(
    "UPDATE backgrounds SET is_active = 0 WHERE room_number = 'A' AND is_active = 1"
);

$existing = Database::queryOne(
    'SELECT id FROM backgrounds WHERE room_number = ? AND webp_filename = ? LIMIT 1',
    ['A', $webp]
);

if ($existing) {
    Database::execute(
        'UPDATE backgrounds
         SET is_active = 1, name = ?, image_filename = ?, png_filename = ?, webp_filename = ?
         WHERE id = ?',
        [$name, $png, $png, $webp, (int) $existing['id']]
    );
} else {
    Database::execute(
        'INSERT INTO backgrounds (room_number, name, image_filename, png_filename, webp_filename, is_active)
         VALUES (?, ?, ?, ?, ?, 1)',
        ['A', $name, $png, $png, $webp]
    );
}

Database::execute(
    "UPDATE backgrounds
     SET is_active = 0
     WHERE room_number = 'A' AND webp_filename <> ?",
    [$webp]
);

Database::execute(
    "UPDATE room_settings
     SET background_url = ?
     WHERE room_number = 'A'",
    ['/images/' . $webp]
);

$active = Database::queryOne(
    "SELECT id, name, webp_filename, png_filename, is_active
     FROM backgrounds
     WHERE room_number = 'A' AND is_active = 1
     LIMIT 1"
);

fwrite(STDOUT, json_encode(['success' => true, 'background' => $active], JSON_PRETTY_PRINT) . PHP_EOL);
