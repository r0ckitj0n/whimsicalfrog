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

    // Vite manifest location: use dist/.vite/manifest.json (authoritative in production for us)
    $manifestPath = __DIR__ . '/../dist/.vite/manifest.json';
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
    // New default: dev is disabled unless explicitly requested
    $devDisabled = !$explicitDev || $disableDevByEnv || $disableDevByFlag;
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

    // Only try dev server when explicitly requested
    if (!$devDisabled && file_exists($hotPath)) {
        $origin = rtrim($viteOrigin, '/');
        $probeOk = $probe_dev($origin);
        // If initial probe fails, try common Vite ports
        if (!$probeOk) {
            $candidates = ['http://localhost:5173', 'http://localhost:5174', 'http://localhost:5175', 'http://localhost:5176'];
            foreach ($candidates as $cand) {
                if ($probe_dev($cand)) {
                    $origin = rtrim($cand, '/');
                    $probeOk = true;
                    break;
                }
            }
        }
        if ($probeOk) {
            $devEntryMap = [
                'js/app.js' => 'src/entries/app.js',
                'js/admin-dashboard.js' => 'src/entries/admin-dashboard.js',
                'js/admin-inventory.js' => 'src/entries/admin-inventory.js',
                'js/admin-settings.js' => 'src/entries/admin-settings.js',
                'js/admin-db-status.js' => 'src/entries/admin-db-status.js',
                'js/header-bootstrap.js' => 'src/entries/header-bootstrap.js',
            ];
            $devEntry = $devEntryMap[$entry] ?? $entry;
            $vite_log('info', 'Vite hot mode: emitting dev script tags directly to origin', [
                'vite_origin' => $origin,
                'entry' => $entry,
                'dev_entry' => $devEntry,
            ]);
            return $bootScript .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/@vite/client\"></script>\n" .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"{$origin}/{$devEntry}\"></script>";
        }
        $vite_log('warning', 'Hot file present but dev server is unreachable; falling back to production assets', [ 'vite_origin' => $viteOrigin ]);
        // Fall through to production manifest below
    }

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
                'js/header-bootstrap.js' => 'src/entries/header-bootstrap.js',
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
        $glob = glob(__DIR__ . '/../dist/' . $stem . '-*.js');
        if (!empty($glob)) {
            usort($glob, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
            $latest = $glob[0];
            if (is_file($latest)) {
                $rel = 'assets/' . ltrim(str_replace(__DIR__ . '/../dist/assets/', '', $latest), '/');
                $asset['file'] = $rel;
            }
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
    $bootJs = "(function(){try{var src='" . addslashes($entrySrc) . "';try{console.log('[ViteBoot] Emitting entry', src);}catch(_){} var s=document.createElement('script');s.type='module';s.crossOrigin='anonymous';s.src=src;s.onerror=function(){try{var u=new URL(window.location.href);if(!u.searchParams.has('nocache')){u.searchParams.set('nocache', Date.now().toString()); window.location.replace(u.toString());}}catch(_){} };var ref=document.currentScript; if(ref&&ref.parentNode){ref.parentNode.insertBefore(s, ref.nextSibling);}else{document.head.appendChild(s);} }catch(_) { /* swallow */ }})();";
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
    $disableDevByEnv = getenv('WF_VITE_DISABLE_DEV') === '1';
    $disableDevByFlag = file_exists(__DIR__ . '/../.disable-vite-dev');
    $devDisabled = ($requestedMode === 'prod') || $disableDevByEnv || $disableDevByFlag;

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
    } catch (Throwable $e) { /* ignore */
    }
    $probe = function (string $origin, float $timeout = 0.6): bool {
        $url = rtrim($origin, '/') . '/@vite/client';
        $ctx = stream_context_create([
            'http' => [ 'timeout' => $timeout, 'ignore_errors' => true ],
            'https' => [ 'timeout' => $timeout, 'ignore_errors' => true ],
        ]);
        return @file_get_contents($url, false, $ctx) !== false;
    };

    // DEV: if not disabled and dev server is reachable, emit source CSS links that Vite can serve directly
    if (!$devDisabled) {
        $origin = rtrim($viteOrigin, '/');
        $ok = $probe($origin);
        if (!$ok) {
            foreach (['http://localhost:5176','http://localhost:5180','http://localhost:5173','http://localhost:5174','http://localhost:5175'] as $cand) {
                if ($probe($cand)) {
                    $origin = rtrim($cand, '/');
                    $ok = true;
                    break;
                }
            }
        }
        if ($ok) {
            // Map entries to the exact CSS modules imported by the entry in dev
            $cssMap = [
                'js/app.js' => [
                    'src/styles/main.css',
                ],
                'js/admin-settings.js' => [
                    'src/styles/main.css',
                    'src/styles/components/components-base.css',
                    'src/styles/components/admin-nav.css',
                    'src/styles/admin-modals.css',
                    'src/styles/components/modal.css',
                    'src/styles/admin-settings.css',
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
            // Fall through to prod if we didn't know the mapping
        }
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
    }

    // Legacy fallback
    $legacyMap = [
        'js/app.js' => ['/css/styles.css', '/css/global-modals.css', '/css/notification-system.css'],
        'js/admin-settings.js' => ['/css/styles.css', '/css/form-styles.css', '/css/global-modals.css'],
    ];
    $fallback = $legacyMap[$entry] ?? [];
    if (!empty($fallback)) {
        $html = '';
        foreach ($fallback as $href) {
            $html .= '<link rel="stylesheet" href="' . $href . '">';
        }
        return $html;
    }
    return '<!-- vite_css: no css resolved for ' . htmlspecialchars($entry) . ' -->';
}

// Alias for backward compatibility
function vite_entry(string $entry): string
{
    return vite($entry);
}
