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
// Always add loopback variants for resilience. Prefer IPv6 first for localhost.
$parsed = parse_url($primaryOrigin);
if ($parsed && isset($parsed['scheme'])) {
    $scheme = $parsed['scheme'];
    $port = isset($parsed['port']) ? (int)$parsed['port'] : ($scheme === 'https' ? 443 : 80);
    $host = isset($parsed['host']) ? $parsed['host'] : '';
    if ($host === 'localhost' || $host === '127.0.0.1') {
        // Prefer IPv6 literal first to avoid IPv4 fallback delays
        $candidates[] = sprintf('%s://[::1]:%d', $scheme, $port);
        // Then try the declared primary origin (localhost) if distinct
        if (!in_array($primaryOrigin, $candidates, true)) {
            $candidates[] = $primaryOrigin;
        }
        // Then localhost and IPv4 literal last
        $candidates[] = sprintf('%s://localhost:%d', $scheme, $port);
        $candidates[] = sprintf('%s://127.0.0.1:%d', $scheme, $port);
    } else {
        // Non-localhost: try the primary origin first
        $candidates[] = $primaryOrigin;
    }
}
if (empty($candidates)) {
    $candidates[] = $primaryOrigin;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'HEAD') {
    $method = 'GET';
}

// Forward the client's Accept header. This allows Vite to decide whether to
// serve CSS as a JS module (for imports) or as plain CSS (for <link> tags).
$acceptHeader = isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] !== ''
    ? $_SERVER['HTTP_ACCEPT']
    : '*/*';

$opts = [
    'http' => [
        'method' => $method,
        'header' => "Accept: {$acceptHeader}\r\nAccept-Encoding: identity\r\nConnection: keep-alive\r\nUser-Agent: PHP-Vite-Proxy/1.0\r\n",
        // Ensure HTTP/1.1 to avoid 426 Upgrade Required from some dev servers
        'protocol_version' => 1.1,
        'ignore_errors' => true,
        'timeout' => 2,
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
        $hh = $u['host'];
        // If IPv6 literal (contains ':'), ensure Host header uses brackets
        if (strpos($hh, ':') !== false && $hh[0] !== '[') {
            $hh = '[' . $hh . ']';
        }
        $hostHeader = $hh . (isset($u['port']) ? ':' . $u['port'] : '');
    }

    $tryStatus = 502;
    $tryType = null;
    $tryBody = false;

    // Prefer cURL first for better IPv6 handling and faster timeouts
    if (function_exists('curl_init')) {
        $ch = curl_init();
        $headers = [
            'Accept: ' . $acceptHeader,
            'Accept-Encoding: identity',
            'Connection: keep-alive',
            'User-Agent: PHP-Vite-Proxy/1.0',
        ];
        if ($hostHeader !== '') {
            $headers[] = 'Host: ' . $hostHeader;
        }
        $curlOpts = [
            CURLOPT_URL => $upstream,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        if ($method === 'HEAD') {
            $curlOpts[CURLOPT_NOBODY] = true;
        }
        curl_setopt_array($ch, $curlOpts);
        $tryBody = curl_exec($ch);
        $tryStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tryType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
    }

    // Fallback to PHP streams if cURL is unavailable or failed
    if (($tryBody === false || $tryStatus < 200 || $tryStatus >= 300)) {
        $hdr = "Accept: {$acceptHeader}\r\nAccept-Encoding: identity\r\nConnection: keep-alive\r\nUser-Agent: PHP-Vite-Proxy/1.0\r\n";
        if ($hostHeader !== '') {
            $hdr .= 'Host: ' . $hostHeader . "\r\n";
        }
        $perOriginOpts = $opts;
        $perOriginOpts['http']['header'] = $hdr;
        $ctx = stream_context_create($perOriginOpts);
        $tryBody = @file_get_contents($upstream, false, $ctx);

        $tryStatus = 502;
        $tryType = $tryType ?: null;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                    $tryStatus = (int)$m[1];
                } elseif (stripos($h, 'Content-Type:') === 0) {
                    $tryType = trim(substr($h, strlen('Content-Type:')));
                }
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
