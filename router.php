<?php

// router.php

$requestedUri = $_SERVER['REQUEST_URI'];

// Remove query string from the path
$requestedPath = strtok($requestedUri, '?');

// Map Square webhooks pretty paths to PHP endpoints (development/router context)
if ($requestedPath === '/square/webhooks') {
    require __DIR__ . '/square/webhooks.php';
    return true;
}
if ($requestedPath === '/square/webhooksfinal') {
    require __DIR__ . '/square/webhooksfinal.php';
    return true;
}

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

// Security: deny access to sensitive files and directories in dev (PHP built-in server ignores .htaccess)
// Block file extensions like .sql, .env, .log, archives, and source maps
if (preg_match('#\.(sql|sqlite|db|env|ini|log|bak|old|zip|tar|gz|7z|rar|bk|bkp|map)$#i', $requestedPath)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden\n";
    exit;
}
// Block hidden dotfiles except the ACME/.well-known path
if (preg_match('#^/\.(?!well-known/)#', $requestedPath)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden\n";
    exit;
}
// Block sensitive directories from being served directly
$denyPrefixes = ['/backups/', '/scripts/', '/documentation/', '/logs/', '/reports/', '/config/', '/.git/', '/.github/', '/vendor/'];
foreach ($denyPrefixes as $prefix) {
    if (strpos($requestedPath, $prefix) === 0) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit;
    }
}

// Strict: do not resolve missing hashed bundles; handled in /dist/ block below

// If the requested path is a file and it exists, serve it directly.
// This handles assets like images, CSS, and JavaScript files.
if (is_file($filePath)) {
    return false; // Serve the requested file as-is.
}

// If the request points to a directory containing an index.php, serve that controller.
if (is_dir($filePath)) {
    $indexFile = rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
    if (is_file($indexFile)) {
        require $indexFile;
        return true;
    }
}

// If a request targets bare /assets/*.js or /assets/*.css (missing /dist prefix), avoid HTML fallthrough
if (preg_match('#^/assets/(.+)\.(js|css)$#i', $requestedPath, $m)) {
    $stem = $m[1];
    $ext = strtolower($m[2]);
    // Try to serve from /dist/assets if present
    $candidate = __DIR__ . '/dist/assets/' . $stem . '.' . $ext;
    if (is_file($candidate)) {
        if ($ext === 'css') {
            header('Content-Type: text/css; charset=utf-8');
        } else {
            header('Content-Type: application/javascript; charset=utf-8');
        }
        readfile($candidate);
        exit;
    }
    // Not found: return 404 plain text to prevent HTML being treated as JS module
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    exit;
}

// If a request targets the built assets directory but the file does not exist, return 404.
if (strpos($requestedPath, '/dist/') === 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    exit;
}

// For all other requests, rewrite to index.php to handle the routing.
// This allows our application to handle clean URLs like /shop or /room/2.
require_once __DIR__ . '/index.php';
