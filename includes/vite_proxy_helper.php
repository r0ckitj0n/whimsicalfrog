<?php
/**
 * Whimsical Frog Vite Proxy Helper
 * Extracted from vite-proxy.php to reduce shell size.
 */

function wf_get_vite_origin()
{
    $hotPath = __DIR__ . '/../hot';
    $primaryOrigin = getenv('WF_VITE_ORIGIN');
    if (!$primaryOrigin && file_exists($hotPath)) {
        $hotContents = @file_get_contents($hotPath);
        if (is_string($hotContents)) {
            $primaryOrigin = trim($hotContents);
        }
    }
    return $primaryOrigin ?: 'http://localhost:5176';
}

function wf_get_vite_candidates($primaryOrigin)
{
    $candidates = [];
    $parsed = parse_url($primaryOrigin);
    if ($parsed && isset($parsed['scheme'])) {
        $scheme = $parsed['scheme'];
        $port = isset($parsed['port']) ? (int) $parsed['port'] : ($scheme === 'https' ? 443 : 80);
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        if ($host === 'localhost' || $host === '127.0.0.1') {
            // Priority 1: 127.0.0.1 (IPv4 is most reliable on macOS for local services)
            $candidates[] = sprintf('%s://127.0.0.1:%d', $scheme, $port);

            // Priority 2: localhost (May be IPv6)
            $candidates[] = sprintf('%s://localhost:%d', $scheme, $port);

            // Priority 3: IPv6 Loopback
            $candidates[] = sprintf('%s://[::1]:%d', $scheme, $port);
        } else {
            $candidates[] = $primaryOrigin;
        }
    }
    return !empty($candidates) ? $candidates : [$primaryOrigin];
}

function wf_vite_proxy_request($upstream, $method, $acceptHeader, $hostHeader)
{
    $tryStatus = 502;
    $tryType = null;
    $tryBody = false;

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
            CURLOPT_TIMEOUT => 1,
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
        // curl_close() is deprecated in PHP 8.5 (no effect since PHP 8.0)
    }

    if (($tryBody === false || $tryStatus < 200 || $tryStatus >= 300)) {
        $hdr = "Accept: {$acceptHeader}\r\nAccept-Encoding: identity\r\nConnection: keep-alive\r\nUser-Agent: PHP-Vite-Proxy/1.0\r\n";
        if ($hostHeader !== '') {
            $hdr .= 'Host: ' . $hostHeader . "\r\n";
        }
        $opts = [
            'http' => [
                'method' => $method,
                'header' => $hdr,
                'protocol_version' => 1.1,
                'ignore_errors' => true,
                'timeout' => 2,
                'follow_location' => 1,
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ];
        $ctx = stream_context_create($opts);
        $tryBody = @file_get_contents($upstream, false, $ctx);
        $tryStatus = 502;
        // PHP 8.5+: Use http_get_last_response_headers() instead of deprecated $http_response_header
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : (isset($http_response_header) ? $http_response_header : []);
        if (is_array($responseHeaders)) {
            foreach ($responseHeaders as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                    $tryStatus = (int) $m[1];
                } elseif (stripos($h, 'Content-Type:') === 0) {
                    $tryType = trim(substr($h, strlen('Content-Type:')));
                }
            }
        }
    }

    return [$tryBody, $tryStatus, $tryType];
}
