<?php
require_once __DIR__ . '/includes/vite_proxy_helper.php';

$primaryOrigin = wf_get_vite_origin();
$path = ltrim(isset($_GET['path']) ? (string) $_GET['path'] : '', '/');
$path = preg_replace('#^__wf_vite/#', '', $path);

if ($path === '') {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing path';
    exit;
}

if ($path === '__vite_ping') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Access-Control-Allow-Origin: *');
    echo '';
    exit;
}

$candidates = wf_get_vite_candidates($primaryOrigin);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'HEAD') {
    $method = 'GET';
}

$acceptHeader = isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] !== '' ? $_SERVER['HTTP_ACCEPT'] : '*/*';
$statusCode = 502;
$contentType = null;
$body = '';

foreach ($candidates as $origin) {
    $upstream = rtrim($origin, '/') . '/' . $path;
    $u = parse_url($upstream);
    $hostHeader = '';
    if ($u && !empty($u['host'])) {
        $hh = $u['host'];
        if (strpos($hh, ':') !== false && $hh[0] !== '[') {
            $hh = '[' . $hh . ']';
        }
        $hostHeader = $hh . (isset($u['port']) ? ':' . $u['port'] : '');
    }

    list($tryBody, $tryStatus, $tryType) = wf_vite_proxy_request($upstream, $method, $acceptHeader, $hostHeader);

    // Any response from upstream that isn't a 502/timeout counts as a result
    if ($tryBody !== false && $tryStatus > 0 && $tryStatus < 500) {
        $statusCode = $tryStatus;
        $contentType = $tryType;
        $body = $tryBody;
        break;
    }
}

if (!$contentType) {
    $ext = strtolower(pathinfo(parse_url($upstream, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    $map = [
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ts' => 'text/javascript; charset=utf-8',
        'tsx' => 'text/javascript; charset=utf-8',
    ];
    $contentType = $map[$ext] ?? 'text/plain; charset=utf-8';
}

// Output headers
http_response_code($statusCode);
header('Content-Type: ' . $contentType);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');

// CSS REWRITE HACK: Fix relative imports breaking in proxy mode
// Vite serves imports like `../foundation/tokens.css`. In JS-injected styles, 
// these resolve relative to the PAGE path (e.g. /login) -> /foundation/tokens.css (404).
// We simply rewrite them to absolute paths assuming standard structure.
if ($contentType && (strpos($contentType, 'javascript') !== false || strpos($contentType, 'css') !== false)) {
    // Rewrites:
    // "../" -> "/src/styles/" (for files in src/styles/entries/)
    $body = str_replace('../', '/src/styles/', $body);

    // Also handle direct entry-level imports if any remain (paranoid)
    $body = str_replace('@import "tailwindcss";', '/* tailwind processed */', $body);
}

echo ($body !== false) ? $body : '';
