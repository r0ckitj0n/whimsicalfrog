<?php
/**
 * Compatibility shim.
 *
 * Older clients hit /api/room_coordinates.php. That path 404'd on IONOS and
 * returned Sedo parking HTML into ApiClient error handlers.
 * Alias to get_room_coordinates.php.
 */
declare(strict_types=1);

require __DIR__ . '/get_room_coordinates.php';
