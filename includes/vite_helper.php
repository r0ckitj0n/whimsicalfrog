<?php

// WF_GUARD_TEMPLATES_CSS_IGNORE
// This helper intentionally generates <link rel="stylesheet"> tags for Vite-managed CSS in both
// development and production modes. The templates CSS guard operates on static templates and should
// not flag this dynamic emission helper.

// A helper function to handle loading Vite assets in both development and production.
function vite(string $entry): string
{
    // Local logging helper: uses global Logger functions if present, otherwise error_log
    $vite_log = function (string $level, string $message, array $context = []): void {
        $payload = $context ? ($message . ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES)) : $message;
        switch (strtoupper($level)) {
            case 'ERROR':
                if (function_exists('logError')) {
                    logError($message, $context);
                    return;
                }
                break;
            case 'WARNING':
                if (function_exists('logWarning')) {
                    logWarning($message, $context);
                    return;
                }
                break;
            case 'INFO':
                if (function_exists('logInfo')) {
                    logInfo($message, $context);
                    return;
                }
                break;
        }
        // Fallback
        error_log('[VITE ' . strtoupper($level) . '] ' . $payload);
    };

    // Vite manifest location: prefer dist/manifest.json (Vite 7 default), fallback to dist/.vite/manifest.json
    $manifestCandidates = [
        __DIR__ . '/../dist/manifest.json',
        __DIR__ . '/../dist/.vite/manifest.json',
    ];
    $manifestPath = null;
    foreach ($manifestCandidates as $cand) {
        if (file_exists($cand)) { $manifestPath = $cand; break; }
    }
    $hotPath = __DIR__ . '/../hot';
    // Default to production unless explicitly requested via ?vite=dev or cookie
    $forceDev = false;

    // Allow disabling dev/HMR even if a hot file exists, to avoid periodic auto-reloads in flaky dev envs.
    // Priority: explicit query param (?vite=prod|dev) with cookie persistence > env WF_VITE_DISABLE_DEV
    $requestedMode = isset($_GET['vite']) ? strtolower((string)$_GET['vite']) : '';
    if ($requestedMode === 'prod' || $requestedMode === 'dev') {
        // Persist preference for 30 days
        @setcookie('wf_vite_mode', $requestedMode, [ 'expires' => time() + 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => false, 'samesite' => 'Lax' ]);
    }
    $cookieMode = isset($_COOKIE['wf_vite_mode']) ? strtolower((string)$_COOKIE['wf_vite_mode']) : '';
    $explicitDev = ($requestedMode === 'dev') || ($cookieMode === 'dev');
    $disableDevByEnv = getenv('WF_VITE_DISABLE_DEV') === '1';
    $disableDevByFlag = file_exists(__DIR__ . '/../.disable-vite-dev');
    // New default: dev is disabled unless explicitly requested.
    // IMPORTANT: explicit ?vite=dev overrides disable flags.
    if ($explicitDev) {
        $devDisabled = false;
    } else {
        $devDisabled = $disableDevByEnv || $disableDevByFlag || true; // true = disabled by default
    }
    if ($explicitDev && !defined('VITE_FORCE_DEV')) {
        define('VITE_FORCE_DEV', true);
    }
    // Determine Vite dev server origin. Priority: WF_VITE_ORIGIN env > hot file > default
    $viteOrigin = getenv('WF_VITE_ORIGIN');
    if (!$viteOrigin && file_exists($hotPath)) {
        $hotContents = @file_get_contents($hotPath);
        $viteOrigin = is_string($hotContents) ? trim($hotContents) : '';
    }
    if (empty($viteOrigin)) {
        $viteOrigin = 'http://localhost:5176';
    }
    // Normalize host: force localhost instead of 127.0.0.1 to keep SameSite cookies for backend on localhost
    // This only rewrites host; scheme/port/path are preserved.
    try {
        $parts = @parse_url($viteOrigin);
        if (is_array($parts) && isset($parts['host'])) {
            $hostIn = $parts['host'];
            if ($hostIn === '127.0.0.1' || $hostIn === '0.0.0.0') {
                $scheme = $parts['scheme'] ?? 'http';
                $host = 'localhost';
                $port = isset($parts['port']) ? (':' . $parts['port']) : '';
                $path = $parts['path'] ?? '';
                $viteOrigin = $scheme . '://' . $host . $port . $path;
            }
        }
    } catch (Throwable $e) {
        // ignore normalization errors
    }

    // Precompute backend origin early so we can include $bootScript in dev path too
    $backendOriginEarly = getenv('WF_BACKEND_ORIGIN');
    if (!$backendOriginEarly) {
        $protoEarly = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $hostEarly = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $portEarly = $_SERVER['SERVER_PORT'] ?? null;
        $needsPortEarly = $portEarly && !strpos($hostEarly, ':') && !(($protoEarly === 'http' && (int)$portEarly === 80) || ($protoEarly === 'https' && (int)$portEarly === 443));
        $backendOriginEarly = $protoEarly . '://' . $hostEarly . ($needsPortEarly ? (':' . $portEarly) : '');
    }
    $bootScriptEarly = "<script>window.__WF_BACKEND_ORIGIN = '" . addslashes(rtrim($backendOriginEarly, '/')) . "';</script>\n";

    // STRICT MODE: explicit dev only, no silent fallbacks to prod.
    // If explicitly requested dev, attempt to use the dev server; if unreachable, DO NOT fall back.
    // Also attempt to reset OPcache to avoid stale helper code paths.
    if ($explicitDev) {
        try { if (function_exists('opcache_reset')) { @opcache_reset(); } } catch (Throwable $e) { /* ignore */ }
        $origin = rtrim(empty(getenv('WF_VITE_ORIGIN')) ? $viteOrigin : getenv('WF_VITE_ORIGIN'), '/');
        if (empty($origin)) { $origin = 'http://localhost:5176'; }
        // Probe the dev server quickly; if it responds, emit dev scripts, else fall back to production below
        $probeUrl = $origin . '/@vite/client';
        $ctx = stream_context_create([
            'http' => [ 'timeout' => 0.6, 'ignore_errors' => true ],
            'https' => [ 'timeout' => 0.6, 'ignore_errors' => true ],
        ]);
        $devAlive = @file_get_contents($probeUrl, false, $ctx) !== false;
        if ($devAlive) {
            $devEntryMap = [
                'js/app.js' => 'src/entries/app.js',
                'js/admin-dashboard.js' => 'src/entries/admin-dashboard.js',
                'js/admin-inventory.js' => 'src/entries/admin-inventory.js',
                'js/admin-settings.js' => 'src/entries/admin-settings.js',
                'js/admin-db-status.js' => 'src/entries/admin-db-status.js',
                'js/admin-reports.js' => 'src/entries/admin-reports.js',
                'js/header-bootstrap.js' => 'src/entries/header-bootstrap.js',
                'js/admin-room-map-editor.js' => 'src/entries/admin-room-map-editor.js',
                'js/area-item-mapper.js' => 'src/entries/area-item-mapper.js',
                'js/email-settings.js' => 'src/entries/email-settings.js',
                'js/admin-email-settings.js' => 'src/entries/admin-email-settings.js',
            ];
            $devEntry = $devEntryMap[$entry] ?? $entry;
            $devMarker = "<script>try{console.log('[ViteBoot] DEV emission active', { origin: '" . addslashes($origin) . "', entry: '" . addslashes($devEntry) . "' });}catch(_){}</script>\n";
            return $bootScriptEarly . $devMarker .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/@vite/client\"></script>\n" .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/{$devEntry}\"></script>";
        } else {
            $vite_log('error', 'Explicit dev requested but dev server is unreachable - strict mode blocks fallback', [ 'vite_origin' => $origin ]);
            // Strict: do not emit any assets here to avoid masking issues.
            return "<!-- Vite dev server unreachable (strict mode) -->";
        }
    }

    // Helper to probe a candidate Vite origin
    $probe_dev = function (string $origin, float $timeout = 0.6) {
        $probeUrl = rtrim($origin, '/') . '/@vite/client';
        $ctx = stream_context_create([
            'http' => [ 'timeout' => $timeout, 'ignore_errors' => true ],
            'https' => [ 'timeout' => $timeout, 'ignore_errors' => true ],
        ]);
        return @file_get_contents($probeUrl, false, $ctx) !== false;
    };

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

    // Strict: do not auto-enable dev via hot file; only explicit dev allowed above.

    // If explicitly forcing dev, try dev server first regardless of manifest (do NOT require hot file)
    if (!$devDisabled && (defined('VITE_FORCE_DEV') && VITE_FORCE_DEV === true)) {
        $origin = rtrim($viteOrigin, '/');
        $probeOk = $probe_dev($origin, 0.75);
        if (!$probeOk) {
            $candidates = ['http://localhost:5173', 'http://localhost:5174', 'http://localhost:5175', 'http://localhost:5176'];
            foreach ($candidates as $cand) {
                if ($probe_dev($cand, 0.75)) {
                    $origin = rtrim($cand, '/');
                    $probeOk = true;
                    break;
                }
            }
        }
        if ($probeOk) {
            // In dev, map build entry keys to dev source paths so Vite can resolve
            $devEntryMap = [
                'js/app.js' => 'src/entries/app.js',
                'js/admin-dashboard.js' => 'src/entries/admin-dashboard.js',
                'js/admin-inventory.js' => 'src/entries/admin-inventory.js',
                'js/admin-settings.js' => 'src/entries/admin-settings.js',
                'js/admin-db-status.js' => 'src/entries/admin-db-status.js',
                'js/admin-reports.js' => 'src/entries/admin-reports.js',
                'js/header-bootstrap.js' => 'src/entries/header-bootstrap.js',
                'js/admin-room-map-editor.js' => 'src/entries/admin-room-map-editor.js',
                'js/area-item-mapper.js' => 'src/entries/area-item-mapper.js',
                'js/email-settings.js' => 'src/entries/email-settings.js',
                'js/admin-email-settings.js' => 'src/entries/admin-email-settings.js',
            ];
            $devEntry = $devEntryMap[$entry] ?? $entry;
            return $bootScript .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/@vite/client\"></script>\n" .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/{$devEntry}\"></script>";
        }
        // else: fall back to production manifest if dev server is not reachable
        $vite_log('warning', 'Vite dev server unreachable while forced; falling back to production assets', [
            'vite_origin_tried' => $viteOrigin,
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
    // For critical entries, always select latest by mtime to defeat stale manifest or cached HTML
    $forceLatest = false;
    $stem = '';
    if ($entry === 'js/app.js' || $resolvedKey === 'src/entries/app.js') {
        $forceLatest = true;
        $stem = 'assets/js/app.js';
    }
    if ($entry === 'js/header-bootstrap.js' || $resolvedKey === 'src/entries/header-bootstrap.js') {
        $forceLatest = true;
        $stem = 'assets/js/header-bootstrap.js';
    }
    if ($forceLatest) {
        // Prefer the most recently modified hashed bundle regardless of manifest staleness
        $candidates = [];
        // Primary expected location
        $patterns = [
            __DIR__ . '/../dist/' . $stem . '-*.js',                 // e.g., dist/assets/js/app.js-*.js
            __DIR__ . '/../dist/assets/js/app.js-*.js',              // explicit app.js fallback
            __DIR__ . '/../dist/assets/*app.js-*.js',                // catch-all in assets root
        ];
        foreach ($patterns as $pattern) {
            $list = glob($pattern);
            if (is_array($list) && !empty($list)) {
                foreach ($list as $f) { if (is_file($f)) { $candidates[] = $f; } }
            }
        }
        if (!empty($candidates)) {
            usort($candidates, function ($a, $b) { return @filemtime($b) <=> @filemtime($a); });
            $latest = $candidates[0];
            $prefix = __DIR__ . '/../dist/assets/';
            if (strpos($latest, $prefix) === 0) {
                $rel = 'assets/' . ltrim(substr($latest, strlen($prefix)), '/');
            } else {
                // Fallback: compute relative to dist/
                $rel = ltrim(str_replace(__DIR__ . '/../dist/', '', $latest), '/');
            }
            if ($rel) {
                $asset['file'] = $rel;
                $vite_log('info', 'ForceLatest override applied', [ 'stem' => $stem, 'picked' => $asset['file'] ]);
            }
        } else {
            $vite_log('warning', 'ForceLatest could not find candidates', [ 'stem' => $stem ]);
        }
    }
    // Public base path for live environments served from a subdirectory, e.g., "/wf".
    // Configure via env WF_PUBLIC_BASE (empty or "/subdir"). Defaults to empty.
    $publicBase = rtrim((string) getenv('WF_PUBLIC_BASE') ?: '', '/');
    $distBase = ($publicBase === '' ? '' : $publicBase) . '/dist/';
    $vite_log('info', 'Vite production base resolved', [
        'WF_PUBLIC_BASE' => $publicBase,
        'dist_base' => $distBase,
    ]);
    // Emit a small boot loader that injects the module script with an onerror handler.
    // If the module fails to load (e.g., stale hash referenced by a proxy/tab), force a 1-time nocache reload.
    $entrySrc = $distBase . $asset['file'];
    $ver = '';
    try {
        $ver = (string) @filemtime(__DIR__ . '/../dist/' . $asset['file']);
    } catch (Throwable $e) {
        $ver = (string) time();
    }
    if ($ver !== '') {
        $entrySrc .= (strpos($entrySrc, '?') === false ? '?v=' . $ver : '&v=' . $ver);
    }
    // Strict: do not auto-toggle URL params on error; simply log and leave failure visible
    $bootJs = "(function(){try{var src='" . addslashes($entrySrc) . "';try{console.log('[ViteBoot] Emitting entry', src);}catch(_){} var s=document.createElement('script');s.type='module';s.crossOrigin='anonymous';s.src=src;s.onerror=function(){try{console.error('[ViteBoot] Failed to load entry (strict mode):', '" . addslashes($entrySrc) . "');}catch(_){} };var ref=document.currentScript; if(ref&&ref.parentNode){ref.parentNode.insertBefore(s, ref.nextSibling);}else{document.head.appendChild(s);} }catch(_) { /* swallow */ }})();";
    $html = $bootScript . "<script>" . $bootJs . "</script>";

    // Collect CSS from entry and all imported chunks to ensure complete styles in production
    $visited = [];
    $cssFiles = [];
    $collect = function (string $key) use (&$collect, &$visited, &$cssFiles, $manifest) {
        if (isset($visited[$key])) {
            return;
        }
        $visited[$key] = true;
        if (!isset($manifest[$key])) {
            return;
        }
        $a = $manifest[$key];
        if (!empty($a['css']) && is_array($a['css'])) {
            foreach ($a['css'] as $f) {
                $cssFiles[] = $f;
            }
        }
        if (!empty($a['imports']) && is_array($a['imports'])) {
            foreach ($a['imports'] as $dep) {
                $collect($dep);
            }
        }
    };
    $collect($resolvedKey);
    if (!empty($asset['imports']) && is_array($asset['imports'])) {
        foreach ($asset['imports'] as $dep) {
            $collect($dep);
        }
    }
    $cssFiles = array_values(array_unique($cssFiles));
    foreach ($cssFiles as $cssFile) {
        $html .= "<link rel=\"stylesheet\" href=\"{$distBase}{$cssFile}\">";
    }

    return $html;
}


// Emit CSS-only links for a given entry (dev or prod), with legacy fallback.
// This is intentionally defined at global scope so templates can call it before any JS bundles.
function vite_css(string $entry): string
{
    // Mode controls
    $requestedMode = isset($_GET['vite']) ? strtolower((string)$_GET['vite']) : '';
    $cookieMode = isset($_COOKIE['wf_vite_mode']) ? strtolower((string)$_COOKIE['wf_vite_mode']) : '';
    $isDevRequested = ($requestedMode === 'dev') || ($cookieMode === 'dev');

    // Resolve a dev origin and probe without requiring a hot file
    $viteOrigin = getenv('WF_VITE_ORIGIN');
    if (empty($viteOrigin)) {
        $viteOrigin = 'http://localhost:5176';
    }
    try {
        $parts = @parse_url($viteOrigin);
        if (is_array($parts) && ($parts['host'] ?? '') === '127.0.0.1') {
            $viteOrigin = ($parts['scheme'] ?? 'http') . '://localhost' . (isset($parts['port']) ? (':' . $parts['port']) : '') . ($parts['path'] ?? '');
        }
    } catch (Throwable $e) { /* ignore */ }
    $probe = function (string $origin, float $timeout = 0.6): bool {
        $url = rtrim($origin, '/') . '/@vite/client';
        $ctx = stream_context_create([
            'http' => [ 'timeout' => $timeout, 'ignore_errors' => true ],
            'https' => [ 'timeout' => $timeout, 'ignore_errors' => true ],
        ]);
        return @file_get_contents($url, false, $ctx) !== false;
    };

    // STRICT DEV: if dev is requested and Vite is reachable, emit dev CSS. Otherwise do NOT fall back.
    if ($isDevRequested) {
        $origin = rtrim($viteOrigin, '/');
        if ($probe($origin)) {
            $cssMap = [
                'js/app.js' => [ 'src/styles/main.css' ],
                'js/admin-settings.js' => [
                    'src/styles/main.css',
                    'src/styles/components/components-base.css',
                    'src/styles/components/admin-nav.css',
                    'src/styles/admin-modals.css',
                    'src/styles/components/modal.css',
                    'src/styles/admin-settings.css',
                ],
                'js/admin-email-settings.js' => [
                    'src/styles/admin/email-settings.css',
                ],
            ];
            $list = $cssMap[$entry] ?? [];
            if (!empty($list)) {
                $html = '';
                foreach ($list as $p) {
                    $href = $origin . '/' . ltrim($p, '/');
                    $href = str_replace(' ', '%20', $href);
                    $html .= '<link rel="stylesheet" href="' . $href . '">';
                }
                if ($html !== '') {
                    return $html;
                }
            }
            // Unknown mapping in strict dev; return comment
            return '<!-- vite_css: no mapping for entry in strict dev: ' . htmlspecialchars($entry) . ' -->';
        }
        // Strict: Vite not reachable; do not fall back
        return '<!-- vite_css: dev requested but Vite unreachable (strict) -->';
    }

    // PROD: read manifest and recursively collect CSS
    $manifestCandidates = [
        __DIR__ . '/../dist/.vite/manifest.json',
        __DIR__ . '/../dist/manifest.json',
    ];
    $manifestPath = null;
    foreach ($manifestCandidates as $candidate) {
        if (file_exists($candidate)) {
            $manifestPath = $candidate;
            break;
        }
    }
    if ($manifestPath && file_exists($manifestPath)) {
        $json = @file_get_contents($manifestPath);
        $manifest = is_string($json) ? json_decode($json, true) : null;
        if (is_array($manifest)) {
            $resolved = $entry;
            if (!isset($manifest[$resolved])) {
                foreach ($manifest as $k => $meta) {
                    if (is_array($meta) && isset($meta['name']) && $meta['name'] === $entry) {
                        $resolved = $k;
                        break;
                    }
                }
            }
            if (!isset($manifest[$resolved]) && preg_match('#^js/(.+)$#', $entry, $m)) {
                $cand = 'src/entries/' . $m[1];
                if (isset($manifest[$cand])) {
                    $resolved = $cand;
                }
            }
            if (isset($manifest[$resolved])) {
                $visited = [];
                $cssFiles = [];
                $collect = function (string $key) use (&$collect, &$visited, &$cssFiles, $manifest) {
                    if (isset($visited[$key])) {
                        return;
                    }
                    $visited[$key] = true;
                    if (!isset($manifest[$key])) {
                        return;
                    }
                    $a = $manifest[$key];
                    if (!empty($a['css']) && is_array($a['css'])) {
                        foreach ($a['css'] as $f) {
                            $cssFiles[] = $f;
                        }
                    }
                    if (!empty($a['imports']) && is_array($a['imports'])) {
                        foreach ($a['imports'] as $dep) {
                            $collect($dep);
                        }
                    }
                };
                $collect($resolved);
                if (!empty($manifest[$resolved]['imports'])) {
                    foreach ($manifest[$resolved]['imports'] as $dep) {
                        $collect($dep);
                    }
                }
                $cssFiles = array_values(array_unique($cssFiles));
                $publicBase = rtrim((string) getenv('WF_PUBLIC_BASE') ?: '', '/');
                $distBase = ($publicBase === '' ? '' : $publicBase) . '/dist/';
                $html = '';
                foreach ($cssFiles as $f) {
                    $html .= '<link rel="stylesheet" href="' . $distBase . $f . '">';
                }
                if ($html !== '') {
                    return $html;
                }
            }
        }
    } else {
        // Emit a clear error if manifest not found
        return '<!-- vite_css error: manifest not found at any candidate path for entry ' . htmlspecialchars($entry) . ' -->';
    }

    return '<!-- vite_css: no css resolved for ' . htmlspecialchars($entry) . ' -->';
}

// Alias for backward compatibility
function vite_entry(string $entry): string
{
    return vite($entry);
}
