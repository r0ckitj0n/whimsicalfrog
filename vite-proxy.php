<?php
// Simple same-origin proxy for Vite dev assets to avoid CORS and 426 issues.
// Usage: /vite-proxy.php?path=@vite/client or /vite-proxy.php?path=src/entries/app.js

$hotPath = __DIR__ . '/hot';
$primaryOrigin = getenv('WF_VITE_ORIGIN');
if (!$primaryOrigin && file_exists($hotPath)) {
    $hotContents = @file_get_contents($hotPath);
    if (is_string($hotContents)) {
        $primaryOrigin = trim($hotContents);
    }
}
if (!$primaryOrigin) {
    // Conservative fallback; the hot file should exist in dev
    $primaryOrigin = 'http://localhost:5199';
}

$path = isset($_GET['path']) ? (string)$_GET['path'] : '';
$path = ltrim($path, '/');
if ($path === '') {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing path';
    exit;
}

// Build list of candidate origins to try (helps avoid odd resolver/proxy issues)
$candidates = [];
$candidates[] = $primaryOrigin;
// If primary uses localhost, also try 127.0.0.1 and ::1
$parsed = parse_url($primaryOrigin);
if ($parsed && isset($parsed['host'], $parsed['scheme'])) {
    $scheme = $parsed['scheme'];
    $host = $parsed['host'];
    $port = isset($parsed['port']) ? (int)$parsed['port'] : ($scheme === 'https' ? 443 : 80);
    if ($host === 'localhost') {
        $candidates[] = sprintf('%s://127.0.0.1:%d', $scheme, $port);
        $candidates[] = sprintf('%s://[::1]:%d', $scheme, $port);
    }
}

$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "Accept: */*\r\n",
        'ignore_errors' => true,
        'timeout' => 8,
        'follow_location' => 1,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
];
$statusCode = 502;
$contentType = null;
$body = '';
foreach ($candidates as $origin) {
    $upstream = rtrim($origin, '/') . '/' . $path;
    $ctx = stream_context_create($opts);
    $tryBody = @file_get_contents($upstream, false, $ctx);

    $tryStatus = 502;
    $tryType = null;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                $tryStatus = (int)$m[1];
            } elseif (stripos($h, 'Content-Type:') === 0) {
                $tryType = trim(substr($h, strlen('Content-Type:')));
            }
        }
    }

    if ($tryBody !== false && $tryStatus >= 200 && $tryStatus < 300) {
        $statusCode = $tryStatus;
        $contentType = $tryType;
        $body = $tryBody;
        break;
    }
}

if (!$contentType) {
    // Best-effort guess by extension
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
    ];
    $contentType = $map[$ext] ?? 'text/plain; charset=utf-8';
}

http_response_code($statusCode);
header('Content-Type: ' . $contentType);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
// Even though this is same-origin, keep permissive CORS for safety in tooling
header('Access-Control-Allow-Origin: *');

echo ($body !== false) ? $body : '';
