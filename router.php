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

// If a request targets the built assets directory but the file does not exist,
// provide a graceful fallback for the app entry bundle, and 404 for others.
if (strpos($requestedPath, '/dist/') === 0) {
    // Graceful fallback: if the request looks like the app entry bundle with a stale hash,
    // redirect to the current hashed file based on the manifest. This mitigates cached HTML.
    if (preg_match('#^/dist/assets/js/app\.js-[A-Za-z0-9_\-]+\.js$#', $requestedPath)) {
        $manifestPaths = [ __DIR__ . '/dist/.vite/manifest.json', __DIR__ . '/dist/manifest.json' ];
        $manifest = null;
        foreach ($manifestPaths as $mp) {
            if (is_file($mp)) {
                $json = @file_get_contents($mp);
                $data = $json ? json_decode($json, true) : null;
                if (is_array($data)) { $manifest = $data; break; }
            }
        }
        if (is_array($manifest)) {
            $resolved = null;
            // Try logical key first
            if (isset($manifest['js/app.js'])) { $resolved = $manifest['js/app.js']; }
            // Try source entry key
            if (!$resolved && isset($manifest['src/entries/app.js'])) { $resolved = $manifest['src/entries/app.js']; }
            // Try common Vite key patterns
            if (!$resolved && isset($manifest['src/js/app.js'])) { $resolved = $manifest['src/js/app.js']; }
            if (!$resolved && isset($manifest['/src/js/app.js'])) { $resolved = $manifest['/src/js/app.js']; }
            // Try scan by name
            if (!$resolved) {
                foreach ($manifest as $k => $meta) {
                    if (is_array($meta) && ($meta['name'] ?? '') === 'js/app.js') { $resolved = $meta; break; }
                }
            }
            if (is_array($resolved) && !empty($resolved['file'])) {
                $target = '/dist/' . ltrim($resolved['file'], '/');
                $targetFs = __DIR__ . $target;
                if (is_file($targetFs)) {
                    // Serve file directly to avoid any redirect and potential loops
                    header('Content-Type: application/javascript; charset=utf-8');
                    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                    readfile($targetFs);
                    exit;
                }
            }
        }
        // If manifest missing or key not found, fallback by scanning filesystem for latest app.js-*.js
        $globPaths = glob(__DIR__ . '/dist/assets/js/app.js-*.js');
        if (!empty($globPaths)) {
            // pick the most recent by modification time
            usort($globPaths, function($a, $b) { return filemtime($b) <=> filemtime($a); });
            $latestFs = $globPaths[0];
            if (is_file($latestFs)) {
                header('Content-Type: application/javascript; charset=utf-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                readfile($latestFs);
                exit;
            }
        }
    }
    // Generic graceful fallback for any missing hashed JS/CSS chunk under /dist/assets
    if (preg_match('#^/dist/assets/(.+)-[A-Za-z0-9_\-]+\.(js|css)$#', $requestedPath, $mm)) {
        $stem = $mm[1];
        $ext = strtolower($mm[2]);
        // Try resolve via manifest first
        $manifestPaths = [ __DIR__ . '/dist/.vite/manifest.json', __DIR__ . '/dist/manifest.json' ];
        $manifest = null;
        foreach ($manifestPaths as $mp) {
            if (is_file($mp)) {
                $json = @file_get_contents($mp);
                $data = $json ? json_decode($json, true) : null;
                if (is_array($data)) { $manifest = $data; break; }
            }
        }
        // Helper to serve a file with correct mime and no-store to break loops
        $serve = function(string $fsPath, string $ext) {
            if (!is_file($fsPath)) { return false; }
            if ($ext === 'css') {
                header('Content-Type: text/css; charset=utf-8');
            } else {
                header('Content-Type: application/javascript; charset=utf-8');
            }
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            readfile($fsPath);
            exit;
        };
        // Try manifest: find any entry whose output file matches the stem regardless of hash
        if (is_array($manifest)) {
            foreach ($manifest as $k => $meta) {
                if (!is_array($meta) || empty($meta['file'])) { continue; }
                $out = (string)$meta['file']; // e.g., assets/js/api-client-<hash>.js
                // Does the output end with the same stem and extension? allow any hash in between
                if (preg_match('#(^|/)'.preg_quote($stem, '#').'-[A-Za-z0-9_\-]+\.'.preg_quote($ext, '#').'$#', $out)) {
                    $target = '/dist/' . ltrim($out, '/');
                    $targetFs = __DIR__ . $target;
                    if (is_file($targetFs)) { $serve($targetFs, $ext); }
                }
            }
        }
        // Fallback: scan filesystem for latest matching stem
        $globPaths = glob(__DIR__ . '/dist/assets/' . $stem . '-*.' . $ext);
        if (!empty($globPaths)) {
            usort($globPaths, function($a, $b) { return filemtime($b) <=> filemtime($a); });
            $latestFs = $globPaths[0];
            if (is_file($latestFs)) { $serve($latestFs, $ext); }
        }
        // If not resolved, continue to default 404 below
    }
    // Default: respond 404 for other missing dist assets to avoid HTML fallthrough
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    exit;
}

// For all other requests, rewrite to index.php to handle the routing.
// This allows our application to handle clean URLs like /shop or /room/2.
require_once __DIR__ . '/index.php';
