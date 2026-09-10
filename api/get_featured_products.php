<?php
/**
 * Compatibility shim for /api/get_featured_products.php.
 * Missing endpoints on IONOS return Sedo parking HTML (HTTP 404).
 */
declare(strict_types=1);

require __DIR__ . '/featured_products.php';
