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
    $primaryOrigin = 'http://localhost:5176';
}

$path = isset($_GET['path']) ? (string)$_GET['path'] : '';
$path = ltrim($path, '/');
if ($path === '') {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing path';
    exit;
}

// Fast-path: Vite's health check endpoint sometimes differs across versions.
// To avoid hanging or 502s that can confuse @vite/client, answer locally.
if ($path === '__vite_ping') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Access-Control-Allow-Origin: *');
    echo '';
    exit;
}

// Build list of candidate origins to try (helps avoid odd resolver/proxy issues)
$candidates = [];
$candidates[] = $primaryOrigin;
// Always add loopback variants for resilience
$parsed = parse_url($primaryOrigin);
if ($parsed && isset($parsed['scheme'])) {
    $scheme = $parsed['scheme'];
    $port = isset($parsed['port']) ? (int)$parsed['port'] : ($scheme === 'https' ? 443 : 80);
    $variants = [
        sprintf('%s://localhost:%d', $scheme, $port),
        sprintf('%s://127.0.0.1:%d', $scheme, $port),
        sprintf('%s://[::1]:%d', $scheme, $port),
    ];
    foreach ($variants as $v) {
        if (!in_array($v, $candidates, true)) {
            $candidates[] = $v;
        }
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'HEAD') {
    $method = 'GET';
}

$opts = [
    'http' => [
        'method' => $method,
        'header' => "Accept: text/javascript, */*;q=0.1\r\nAccept-Encoding: identity\r\nConnection: keep-alive\r\nUser-Agent: PHP-Vite-Proxy/1.0\r\n",
        // Ensure HTTP/1.1 to avoid 426 Upgrade Required from some dev servers
        'protocol_version' => 1.1,
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
    $u = parse_url($upstream);
    $hostHeader = '';
    if ($u && !empty($u['host'])) {
        $hostHeader = $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    }
    $hdr = "Accept: text/javascript, */*;q=0.1\r\nAccept-Encoding: identity\r\nConnection: keep-alive\r\nUser-Agent: PHP-Vite-Proxy/1.0\r\n";
    if ($hostHeader !== '') {
        $hdr .= 'Host: ' . $hostHeader . "\r\n";
    }
    $perOriginOpts = $opts;
    $perOriginOpts['http']['header'] = $hdr;
    $ctx = stream_context_create($perOriginOpts);
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

    // If stream method failed or returned non-2xx (e.g., 426), try cURL if available forced to HTTP/1.1
    if (function_exists('curl_init')) {
        $ch = curl_init();
        $headers = [
            'Accept: text/javascript, */*;q=0.1',
            'Accept-Encoding: identity',
            'Connection: keep-alive',
            'User-Agent: PHP-Vite-Proxy/1.0',
        ];
        // Preserve Host header for localhost/127.0.0.1 consistency
        if ($hostHeader !== '') {
            $headers[] = 'Host: ' . $hostHeader;
        }
        $curlOpts = [
            CURLOPT_URL => $upstream,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        if ($method === 'HEAD') {
            $curlOpts[CURLOPT_NOBODY] = true;
        }
        curl_setopt_array($ch, $curlOpts);
        $respBody = curl_exec($ch);
        $curlStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $respType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($respBody !== false && $curlStatus >= 200 && $curlStatus < 300) {
            $statusCode = $curlStatus;
            $contentType = $respType ?: $tryType;
            $body = $respBody;
            break;
        }
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
