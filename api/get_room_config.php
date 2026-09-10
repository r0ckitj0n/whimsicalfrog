<?php
/**
 * Compatibility shim.
 *
 * Older clients hit /api/get_room_config.php. That path 404'd on IONOS and
 * returned Sedo parking HTML into ApiClient error handlers.
 * Alias to room_config.php.
 */
declare(strict_types=1);

require __DIR__ . '/room_config.php';
