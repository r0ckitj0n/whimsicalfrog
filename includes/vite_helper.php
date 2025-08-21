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
    // Determine Vite dev server origin. Priority: WF_VITE_ORIGIN env > hot file > default
    $viteOrigin = getenv('WF_VITE_ORIGIN');
    if (!$viteOrigin && file_exists($hotPath)) {
        $hotContents = @file_get_contents($hotPath);
        $viteOrigin = is_string($hotContents) ? trim($hotContents) : '';
    }
    if (empty($viteOrigin)) { $viteOrigin = 'http://localhost:5199'; }

    // If a hot file exists AND there is no production manifest, we are in a typical local
    // development scenario. In this case, emit dev tags without probing to avoid the
    // situation where PHP cannot reach the dev server (e.g., Docker/permissions), which
    // would otherwise prevent any assets from loading.
    if (file_exists($hotPath) && !($manifestPath && file_exists($manifestPath))) {
        $devEntryMap = [
            'js/app.js' => 'src/entries/app.js',
            'js/admin-dashboard.js' => 'src/entries/admin-dashboard.js',
            'js/admin-inventory.js' => 'src/entries/admin-inventory.js',
        ];
        $devEntry = $devEntryMap[$entry] ?? $entry;
        $vite_log('info', 'Vite hot mode (no manifest): emitting dev script tags without probe', [
            'vite_origin' => $viteOrigin,
            'entry' => $entry,
            'dev_entry' => $devEntry,
        ]);
        return "<script crossorigin=\"anonymous\" type=\"module\" src=\"/vite-proxy.php?path=@vite/client\"></script>\n" .
               "<script crossorigin=\"anonymous\" type=\"module\" src=\"/vite-proxy.php?path={$devEntry}\"></script>";
    }

    // If explicitly forcing dev, try dev server first regardless of manifest (do NOT require hot file)
    if ($forceDev) {
        $probeUrl = rtrim($viteOrigin, '/') . '/@vite/client';
        $ctx = stream_context_create([
            'http' => ['timeout' => 0.75],
            'https' => ['timeout' => 0.75],
        ]);
        $probe = @file_get_contents($probeUrl, false, $ctx);

        if ($probe !== false) {
            // In dev, map build entry keys to dev source paths so Vite can resolve
            $devEntryMap = [
                'js/app.js' => 'src/entries/app.js',
                'js/admin-dashboard.js' => 'src/entries/admin-dashboard.js',
                'js/admin-inventory.js' => 'src/entries/admin-inventory.js',
            ];
            $devEntry = $devEntryMap[$entry] ?? $entry;

            return "<script crossorigin=\"anonymous\" type=\"module\" src=\"/vite-proxy.php?path=@vite/client\"></script>\n" .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"/vite-proxy.php?path={$devEntry}\"></script>";
        }
        // else: fall back to production manifest if dev server is not reachable
        $vite_log('warning', 'Vite dev server unreachable while forced; falling back to production assets', [
            'probe_url' => $probeUrl,
            'vite_origin' => $viteOrigin,
        ]);
    }

    // Prefer production manifest by default when available; else try dev server without requiring hot file
    // Additionally, if a 'hot' file exists (our workflow uses it), prefer trying dev server first
    if (file_exists($hotPath) || !($manifestPath && file_exists($manifestPath) && !$forceDev)) {
        $probeUrl = rtrim($viteOrigin, '/') . '/@vite/client';
        $ctx = stream_context_create([
            'http' => ['timeout' => 0.75],
            'https' => ['timeout' => 0.75],
        ]);
        $probe = @file_get_contents($probeUrl, false, $ctx);
        if ($probe !== false) {
            $devEntryMap = [
                'js/app.js' => 'src/entries/app.js',
                'js/admin-dashboard.js' => 'src/entries/admin-dashboard.js',
                'js/admin-inventory.js' => 'src/entries/admin-inventory.js',
            ];
            $devEntry = $devEntryMap[$entry] ?? $entry;
            return "<script crossorigin=\"anonymous\" type=\"module\" src=\"/vite-proxy.php?path=@vite/client\"></script>\n" .
                   "<script crossorigin=\"anonymous\" type=\"module\" src=\"/vite-proxy.php?path={$devEntry}\"></script>";
        }
        $vite_log('warning', 'Vite dev server unreachable; will try manifest', [
            'probe_url' => $probeUrl,
            'vite_origin' => $viteOrigin,
        ]);
        // Fall through to manifest
    }

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
    $html = "<script type=\"module\" src=\"/dist/{$asset['file']}\"></script>";

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
        $html .= "<link rel=\"stylesheet\" href=\"/dist/{$cssFile}\">";
    }

    return $html;
}

