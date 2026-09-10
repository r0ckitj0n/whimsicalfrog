<?php
/**
 * Compatibility shim for /api/door_sign_destinations.php.
 * Missing endpoints on IONOS return Sedo parking HTML (HTTP 404).
 */
declare(strict_types=1);

if (!isset($_GET['action']) || trim((string) $_GET['action']) === '') {
    $_GET['action'] = 'door_sign_destinations';
}
if (!isset($_GET['room']) || trim((string) $_GET['room']) === '') {
    $_GET['room'] = 'A';
}

require __DIR__ . '/area_mappings.php';
