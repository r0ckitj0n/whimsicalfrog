<?php

// router.php

// Identify which server/workspace is responding
header('X-WF-Server-Workspace: ' . __DIR__);
header('X-WF-Server-CWD: ' . getcwd());
header('X-WF-Random-Token: ' . bin2hex(random_bytes(8)));

// TOP-LEVEL LOGGING REMOVED

$requestedUri = $_SERVER['REQUEST_URI'];
$requestedPath = strtok($requestedUri, '?');

// Auto-sync sitemap entries (pages/modals) on each request; fail-soft if unavailable
try {
    require_once __DIR__ . '/includes/sitemap_autosync.php';
} catch (Throwable $e) {
    // ignore
}

// Map Square webhooks pretty paths to PHP endpoints
if ($requestedPath === '/square/webhooks') {
    require __DIR__ . '/api/square/webhooks.php';
    return true;
}
if ($requestedPath === '/square/webhooksfinal') {
    require __DIR__ . '/api/square/webhooksfinal.php';
    return true;
}

// Map clean policy URLs to their PHP handlers
if ($requestedPath === '/privacy') {
    require __DIR__ . '/privacy.php';
    return true;
}
if ($requestedPath === '/terms') {
    require __DIR__ . '/terms.php';
    return true;
}
if ($requestedPath === '/policy') {
    require __DIR__ . '/policy.php';
    return true;
}

if ($requestedPath === '/robots.txt' || $requestedPath === '/sitemap.xml') {
    require_once __DIR__ . '/includes/helpers/SitemapHelper.php';
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $https = $_SERVER['HTTPS'] ?? '';
    $isHttps = ($forwardedProto === 'https') || (!empty($https) && strtolower((string) $https) !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $baseUrl = $scheme . '://' . $host;

    if ($requestedPath === '/robots.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        echo SitemapHelper::renderRobotsTxt($baseUrl);
        exit;
    }

    header('Content-Type: application/xml; charset=utf-8');
    echo SitemapHelper::renderSitemapXml($baseUrl);
    exit;
}

// Check for manual vite mode overrides (?vite=prod or ?vite=dev)
$viteModeQuery = $_GET['vite'] ?? null;
$forceProd = ($viteModeQuery === 'prod');
$forceDev = ($viteModeQuery === 'dev');

// Endpoints that should NOT be handled by React SPA
$legacyExceptions = [
    '/logout.php',
    '/login.php',
    '/register.php',
    '/admin_router.php',
];
if (in_array($requestedPath, $legacyExceptions)) {
    http_response_code(404);
    echo "Legacy endpoint removed in Native React transition.";
    exit;
}


// If Vite hot file exists, proxy Vite dev asset paths via same-origin proxy to avoid CORS/TLS issues
$hotPath = __DIR__ . '/hot';
$disableDevByFlag = file_exists(__DIR__ . '/.disable-vite-dev');
$disableDevByEnv = getenv('WF_VITE_DISABLE_DEV') === '1';

// Determine if we should use dev mode or prod mode
$useDevMode = (file_exists($hotPath) && !$disableDevByFlag && !$disableDevByEnv && !$forceProd) || $forceDev;

if ($useDevMode) {
    $pathsToProxy = [
        '/@vite',         // @vite/client, etc.
        '/@id/',          // Vite id mapped modules
        '/@fs/',          // Filesystem imports
        '/src/',          // Source files in dev
        '/node_modules/', // Dev deps
        '/.vite/',        // Optimized deps
        '/__vite',        // ping/injected helpers
        '/__wf_vite/',    // Virtual prefix to force proxying
        '/favicon.ico',   // Often served by Vite in dev
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

// Security: deny access to sensitive files and directories
require_once __DIR__ . '/includes/router_security.php';
handleRouterSecurity($requestedPath);
// Log request for debugging if needed (commented out to avoid HMR loop)
//$logMsg = "[" . date('Y-m-d H:i:s') . "] Requested: $requestedPath -> FilePath: $filePath (is_file: " . (is_file($filePath) ? 'YES' : 'NO') . ")\n";
//file_put_contents(__DIR__ . '/logs/router_debug.log', $logMsg, FILE_APPEND);

// Strict: do not resolve missing hashed bundles; handled in /dist/ block below

// If the requested path is a file and it exists, serve it directly.
// This handles assets like images, CSS, and JavaScript files.
if (is_file($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // For PHP files, include them
    if ($ext === 'php') {
        require $filePath;
        return true;
    }

    // For static files, serve them with correct MIME types
    $mimes = [
        'js' => 'application/javascript',
        'css' => 'text/css',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'json' => 'application/json',
        'ico' => 'image/x-icon',
        'ts' => 'application/javascript',
        'tsx' => 'application/javascript'
    ];

    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }

    // Avoid stale hashed bundle mismatch in local prod-mode:
    // if JS/CSS under /dist/assets is cached, a refreshed build can remove old chunk names
    // while the browser still executes a cached parent module.
    $isDistAsset = strpos($requestedPath, '/dist/assets/') === 0;
    $isScriptOrStyle = in_array($ext, ['js', 'mjs', 'css'], true);
    if ($isDistAsset && $isScriptOrStyle) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    } else {
        // Cache static files for 1 hour in local dev to reduce repeated disk reads.
        header('Cache-Control: public, max-age=3600');
    }
    readfile($filePath);
    exit;
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

// Python Server Asset Fallback
// Intercepts /build-assets/ to serve production files via PHP, bypassing Python static 404s
if (strpos($requestedPath, '/build-assets/') === 0) {
    $sub = substr($requestedPath, strlen('/build-assets/'));
    $actual = __DIR__ . '/dist/' . $sub;

    if (is_file($actual)) {
        $ext = strtolower(pathinfo($actual, PATHINFO_EXTENSION));
        $mimes = [
            'js' => 'application/javascript',
            'mjs' => 'application/javascript',
            'css' => 'text/css',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'json' => 'application/json',
            'ico' => 'image/x-icon'
        ];

        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }

        // Cache production assets for 1 hour to speed up local dev
        header('Cache-Control: public, max-age=3600');
        readfile($actual);
        exit;
    }
}

// If a request targets the built assets directory but the file does not exist, return 404.
if (strpos($requestedPath, '/dist/') === 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    exit;
}

// For all other requests, serve the app. 
// In development with Vite, we use the root index.html.
// In production, we use dist/index.html.
$appHtmlPath = $useDevMode ? (__DIR__ . '/index.html') : (__DIR__ . '/dist/index.html');
$html = @file_get_contents($appHtmlPath);
if ($html === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unable to load application shell\n";
    exit;
}

// Inject crawlable SEO tags server-side so bots can index marketing metadata
// without relying on client-side JavaScript execution.
try {
    require_once __DIR__ . '/includes/helpers/SpaSeoHelper.php';
    $seoTags = SpaSeoHelper::renderTagsForPath($requestedPath);
    $discoverabilityNav = SpaSeoHelper::renderRoomDiscoverabilityNav();
    $initialRoom = SpaSeoHelper::resolveRoomNumberForPath($requestedPath);
    if (!empty($seoTags) && stripos($html, '</head>') !== false) {
        $patterns = [
            '/<title\b[^>]*>.*?<\/title>\s*/is',
            '/<meta\s+name=["\']description["\'][^>]*>\s*/i',
            '/<meta\s+name=["\']keywords["\'][^>]*>\s*/i',
            '/<link\s+rel=["\']canonical["\'][^>]*>\s*/i',
            '/<meta\s+property=["\']og:title["\'][^>]*>\s*/i',
            '/<meta\s+property=["\']og:description["\'][^>]*>\s*/i',
            '/<meta\s+property=["\']og:image["\'][^>]*>\s*/i',
            '/<meta\s+property=["\']og:url["\'][^>]*>\s*/i',
            '/<meta\s+property=["\']og:type["\'][^>]*>\s*/i',
            '/<meta\s+name=["\']twitter:card["\'][^>]*>\s*/i',
            '/<meta\s+name=["\']twitter:title["\'][^>]*>\s*/i',
            '/<meta\s+name=["\']twitter:description["\'][^>]*>\s*/i',
            '/<meta\s+name=["\']twitter:image["\'][^>]*>\s*/i',
            '/<script\s+type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>\s*/is',
        ];
        $html = preg_replace($patterns, '', $html) ?? $html;
        if ($initialRoom !== null && $initialRoom !== '') {
            $bootScript = '<script>window.__WF_INITIAL_ROOM=' . json_encode((string) $initialRoom) . ';window.__WF_INITIAL_ROOM_CONSUMED=false;</script>';
            $seoTags .= "\n" . $bootScript;
        }
        $html = preg_replace('/<\/head>/i', $seoTags . "\n</head>", $html, 1);
    }
    if (!empty($discoverabilityNav) && stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\/body>/i', $discoverabilityNav . "\n</body>", $html, 1);
    }
} catch (Throwable $e) {
    error_log('[router] SEO injection failed: ' . $e->getMessage());
}

echo $html;
exit;
