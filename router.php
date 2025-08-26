<?php
// router.php

$requestedUri = $_SERVER['REQUEST_URI'];

// Remove query string from the path
$requestedPath = strtok($requestedUri, '?');

// If Vite hot file exists, proxy Vite dev asset paths via same-origin proxy to avoid CORS/TLS issues
$hotPath = __DIR__ . '/hot';
$disableDevByFlag = file_exists(__DIR__ . '/.disable-vite-dev');
$disableDevByEnv = getenv('WF_VITE_DISABLE_DEV') === '1';
if (file_exists($hotPath) && !$disableDevByFlag && !$disableDevByEnv) {
    $pathsToProxy = [
        '/@vite',         // @vite/client, etc.
        '/@id/',          // Vite id mapped modules
        '/@fs/',          // Filesystem imports
        '/src/',          // Source files in dev
        '/node_modules/', // Dev deps
        '/.vite/',        // Optimized deps
        '/__vite',        // ping/injected helpers
    ];
    foreach ($pathsToProxy as $prefix) {
        if (strpos($requestedPath, $prefix) === 0) {
            // Build path+query for proxy
            $pathWithQuery = ltrim($requestedPath, '/');
            $query = parse_url($requestedUri, PHP_URL_QUERY);
            if ($query) {
                $pathWithQuery .= '?' . $query;
            }
            $_GET['path'] = $pathWithQuery;
            require __DIR__ . '/vite-proxy.php';
            return true;
        }
    }
}

// Construct the full path to the requested file in the public directory
$filePath = __DIR__ . $requestedPath;

// If the requested path is a file and it exists, serve it directly.
// This handles assets like images, CSS, and JavaScript files.
if (is_file($filePath)) {
    return false; // Serve the requested file as-is.
}

// For all other requests, rewrite to index.php to handle the routing.
// This allows our application to handle clean URLs like /shop or /room/2.
require_once __DIR__ . '/index.php';
