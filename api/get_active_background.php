<?php
/**
 * Compatibility shim.
 *
 * Older clients/bookmarks hit /api/get_active_background.php. That path 404'd on
 * IONOS and returned Sedo parking HTML into ApiClient error handlers.
 * Keep this endpoint as a thin alias of get_background.php.
 */
declare(strict_types=1);

if (!isset($_GET['room']) && isset($_GET['room_number'])) {
    $_GET['room'] = $_GET['room_number'];
}
if (!isset($_GET['room']) || trim((string) $_GET['room']) === '') {
    $_GET['room'] = 'A';
}

require __DIR__ . '/get_background.php';
