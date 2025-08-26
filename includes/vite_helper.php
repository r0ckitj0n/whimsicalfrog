<?php

// A helper function to handle loading Vite assets in both development and production.
function vite(string $entry): string
{
    // Local logging helper: uses global Logger functions if present, otherwise error_log
    $vite_log = function (string $level, string $message, array $context = []): void {
        $payload = $context ? ($message . ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES)) : $message;
        switch (strtoupper($level)) {
            case 'ERROR':
                if (function_exists('logError')) { logError($message, $context); return; }
                break;
            case 'WARNING':
                if (function_exists('logWarning')) { logWarning($message, $context); return; }
                break;
            case 'INFO':
                if (function_exists('logInfo')) { logInfo($message, $context); return; }
                break;
        }
        // Fallback
        error_log('[VITE ' . strtoupper($level) . '] ' . $payload);
    };

    // Vite puts manifest at dist/.vite/manifest.json by default (Vite v7)
    // Some setups may use dist/manifest.json. Try both for compatibility.
    $manifestCandidates = [
        __DIR__ . '/../dist/.vite/manifest.json',
        __DIR__ . '/../dist/manifest.json',
    ];
    $manifestPath = null;
    foreach ($manifestCandidates as $candidate) {
        if (file_exists($candidate)) { $manifestPath = $candidate; break; }
    }
    $hotPath = __DIR__ . '/../hot';
    $forceDev = (getenv('WF_VITE_DEV') === '1') || (defined('VITE_FORCE_DEV') && VITE_FORCE_DEV === true);

    // Allow disabling dev/HMR even if a hot file exists, to avoid periodic auto-reloads in flaky dev envs.
    // Priority: explicit query param (?vite=prod|dev) with cookie persistence > env WF_VITE_DISABLE_DEV
    $requestedMode = isset($_GET['vite']) ? strtolower((string)$_GET['vite']) : '';
    if ($requestedMode === 'prod' || $requestedMode === 'dev') {
        // Persist preference for 30 days
        @setcookie('wf_vite_mode', $requestedMode, [ 'expires' => time() + 60*60*24*30, 'path' => '/', 'httponly' => false, 'samesite' => 'Lax' ]);
    }
    $cookieMode = isset($_COOKIE['wf_vite_mode']) ? strtolower((string)$_COOKIE['wf_vite_mode']) : '';
    $disableDevByEnv = getenv('WF_VITE_DISABLE_DEV') === '1';
    $disableDevByFlag = file_exists(__DIR__ . '/../.disable-vite-dev');
    // Prefer dev when hot server is available. Only explicit query param, env, or flag disables dev.
    $devDisabled = ($requestedMode === 'prod') || $disableDevByEnv || $disableDevByFlag;
    // Allow explicit dev request (query or cookie) to force dev resolution path later
    if ($requestedMode === 'dev' || $cookieMode === 'dev') {
        if (!defined('VITE_FORCE_DEV')) { define('VITE_FORCE_DEV', true); }
    }
    // Determine Vite dev server origin. Priority: WF_VITE_ORIGIN env > hot file > default
    $viteOrigin = getenv('WF_VITE_ORIGIN');
    if (!$viteOrigin && file_exists($hotPath)) {
        $hotContents = @file_get_contents($hotPath);
        $viteOrigin = is_string($hotContents) ? trim($hotContents) : '';
    }
    if (empty($viteOrigin)) { $viteOrigin = 'http://localhost:5176'; }
    // Normalize host: force localhost instead of 127.0.0.1 to keep SameSite cookies for backend on localhost
    // This only rewrites host; scheme/port/path are preserved.
    try {
        $parts = @parse_url($viteOrigin);
        if (is_array($parts) && isset($parts['host']) && $parts['host'] === '127.0.0.1') {
            $scheme = $parts['scheme'] ?? 'http';
            $host = 'localhost';
            $port = isset($parts['port']) ? (':' . $parts['port']) : '';
            $path = $parts['path'] ?? '';
            $viteOrigin = $scheme . '://' . $host . $port . $path;
        }
    } catch (Throwable $e) {
        // ignore normalization errors
    }

    // Compute backend origin for JS so cross-origin API calls can include cookies
    $backendOrigin = getenv('WF_BACKEND_ORIGIN');
    if (!$backendOrigin) {
        $proto = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        // Normalize host to not include default ports redundantly
        $port = $_SERVER['SERVER_PORT'] ?? null;
        $needsPort = $port && !strpos($host, ':') && !(($proto === 'http' && (int)$port === 80) || ($proto === 'https' && (int)$port === 443));
        $backendOrigin = $proto . '://' . $host . ($needsPort ? (':' . $port) : '');
    }
    $bootScript = "<script>window.__WF_BACKEND_ORIGIN = '" . addslashes(rtrim($backendOrigin, '/')) . "';</script>\n";

    // If a hot file exists, try dev server. Probe @vite/client to avoid emitting dev tags on live accidentally.
    if (!$devDisabled && file_exists($hotPath)) {
        $probeUrl = rtrim($viteOrigin, '/') . '/@vite/client';
        $ctx = stream_context_create([
            'http' => [ 'timeout' => 0.6, 'ignore_errors' => true ],
            'https' => [ 'timeout' => 0.6, 'ignore_errors' => true ],
        ]);
        $probe = @file_get_contents($probeUrl, false, $ctx);
        if ($probe !== false) {
            $devEntryMap = [
                'js/app.js' => 'src/entries/app.js',
                'js/admin-dashboard.js' => 'src/entries/admin-dashboard.js',
                'js/admin-inventory.js' => 'src/entries/admin-inventory.js',
                'js/admin-settings.js' => 'src/entries/admin-settings.js',
            ];
            $devEntry = $devEntryMap[$entry] ?? $entry;
            $vite_log('info', 'Vite hot mode: emitting dev script tags directly to origin', [
                'vite_origin' => $viteOrigin,
                'entry' => $entry,
                'dev_entry' => $devEntry,
            ]);
            $origin = rtrim($viteOrigin, '/');
            return $bootScript .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/@vite/client\"></script>\n" .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/{$devEntry}\"></script>";
        }
        $vite_log('warning', 'Hot file present but dev server is unreachable; falling back to production assets', [ 'vite_origin' => $viteOrigin ]);
        // Fall through to production manifest below
    }

    // If explicitly forcing dev, try dev server first regardless of manifest (do NOT require hot file)
    if (!$devDisabled && $forceDev) {
        $probeUrl = rtrim($viteOrigin, '/') . '/@vite/client';
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 0.75,
                'protocol_version' => 1.1,
                'ignore_errors' => true,
                'header' => "Accept: text/javascript, */*;q=0.1\r\nConnection: keep-alive\r\nUser-Agent: PHP-Vite-Probe/1.0\r\n",
            ],
            'https' => [
                'timeout' => 0.75,
                'protocol_version' => 1.1,
                'ignore_errors' => true,
                'header' => "Accept: text/javascript, */*;q=0.1\r\nConnection: keep-alive\r\nUser-Agent: PHP-Vite-Probe/1.0\r\n",
            ],
        ]);
        $probe = @file_get_contents($probeUrl, false, $ctx);

        if ($probe !== false) {
            // In dev, map build entry keys to dev source paths so Vite can resolve
            $devEntryMap = [
                'js/app.js' => 'src/entries/app.js',
                'js/admin-dashboard.js' => 'src/entries/admin-dashboard.js',
                'js/admin-inventory.js' => 'src/entries/admin-inventory.js',
                'js/admin-settings.js' => 'src/entries/admin-settings.js',
            ];
            $devEntry = $devEntryMap[$entry] ?? $entry;

            $origin = rtrim($viteOrigin, '/');
            return $bootScript .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/@vite/client\"></script>\n" .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/{$devEntry}\"></script>";
        }
        // else: fall back to production manifest if dev server is not reachable
        $vite_log('warning', 'Vite dev server unreachable while forced; falling back to production assets', [
            'probe_url' => $probeUrl,
            'vite_origin' => $viteOrigin,
        ]);
    }

    // Prefer production manifest by default when not in dev or when forced dev is off and no hot file.
    // In production, we load the bundled assets from the manifest.
    if (!$manifestPath || !file_exists($manifestPath)) {
        $vite_log('warning', 'Vite manifest not found', ['manifest_path' => $manifestPath, 'entry' => $entry]);
        return '<!-- manifest.json not found -->';
    }

    $json = file_get_contents($manifestPath);
    $manifest = json_decode($json, true);
    if (!is_array($manifest)) {
        $vite_log('error', 'Invalid Vite manifest JSON', [
            'manifest_path' => $manifestPath,
            'json_error' => json_last_error_msg(),
        ]);
        return '<!-- invalid manifest.json -->';
    }

    // Resolve manifest entry: Vite v7 keys by source path (e.g., 'src/entries/app.js'),
    // while our code may request by logical name (e.g., 'js/app.js'). Support both.
    $resolvedKey = $entry;
    if (!isset($manifest[$resolvedKey])) {
        // Try resolve by 'name' field
        foreach ($manifest as $k => $meta) {
            if (is_array($meta) && isset($meta['name']) && $meta['name'] === $entry) {
                $resolvedKey = $k;
                break;
            }
        }
    }
    if (!isset($manifest[$resolvedKey])) {
        // Try common mapping: 'js/app.js' -> 'src/entries/app.js'
        if (preg_match('#^js/(.+)$#', $entry, $m)) {
            $candidate = 'src/entries/' . $m[1];
            if (isset($manifest[$candidate])) {
                $resolvedKey = $candidate;
            }
        }
    }
    if (!isset($manifest[$resolvedKey])) {
        $vite_log('warning', 'Entry not found in Vite manifest', [
            'requested_entry' => $entry,
        ]);
        return "<!-- Entry not found in manifest: {$entry} -->";
    }

    if ($resolvedKey !== $entry) {
        $vite_log('info', 'Resolved Vite manifest entry', [
            'requested_entry' => $entry,
            'resolved_key' => $resolvedKey,
        ]);
    }

    $asset = $manifest[$resolvedKey];
    // Public base path for live environments served from a subdirectory, e.g., "/wf".
    // Configure via env WF_PUBLIC_BASE (empty or "/subdir"). Defaults to empty.
    $publicBase = rtrim((string) getenv('WF_PUBLIC_BASE') ?: '', '/');
    $distBase = ($publicBase === '' ? '' : $publicBase) . '/dist/';
    $vite_log('info', 'Vite production base resolved', [
        'WF_PUBLIC_BASE' => $publicBase,
        'dist_base' => $distBase,
    ]);
    $html = $bootScript . "<script type=\"module\" src=\"{$distBase}{$asset['file']}\"></script>";

    // Collect CSS from entry and all imported chunks to ensure complete styles in production
    $visited = [];
    $cssFiles = [];
    $collect = function(string $key) use (&$collect, &$visited, &$cssFiles, $manifest) {
        if (isset($visited[$key])) { return; }
        $visited[$key] = true;
        if (!isset($manifest[$key])) { return; }
        $a = $manifest[$key];
        if (!empty($a['css']) && is_array($a['css'])) {
            foreach ($a['css'] as $f) { $cssFiles[] = $f; }
        }
        if (!empty($a['imports']) && is_array($a['imports'])) {
            foreach ($a['imports'] as $dep) { $collect($dep); }
        }
    };
    $collect($resolvedKey);
    if (!empty($asset['imports']) && is_array($asset['imports'])) {
        foreach ($asset['imports'] as $dep) { $collect($dep); }
    }
    $cssFiles = array_values(array_unique($cssFiles));
    foreach ($cssFiles as $cssFile) {
        $html .= "<link rel=\"stylesheet\" href=\"{$distBase}{$cssFile}\">";
    }

    return $html;
}

