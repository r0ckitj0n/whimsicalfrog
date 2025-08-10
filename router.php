<?php
// router.php

$requestedPath = $_SERVER['REQUEST_URI'];

// Remove query string from the path
$requestedPath = strtok($requestedPath, '?');

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
