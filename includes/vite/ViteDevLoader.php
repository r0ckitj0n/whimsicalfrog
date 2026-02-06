<?php

/**
 * Handles asset loading in development mode (with HMR)
 */
class ViteDevLoader
{
    private static $devEntryMap = [
        'js/main.js' => 'src/entries/main.tsx',
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

    private static $cssMap = [
        'js/app.js' => ['src/styles/entries/public-core.css'],
        'js/admin-settings.js' => ['src/styles/entries/admin-core.css'],
        'js/admin-email-settings.js' => ['src/styles/pages/admin/email-settings.css'],
        'css/admin-core.css' => ['src/styles/entries/admin-core.css'],
        'css/public-core.css' => ['src/styles/entries/public-core.css'],
        'css/embed-core.css' => ['src/styles/entries/embed-core.css'],
    ];

    public static function probe(string $origin, float $timeout = 1.0): bool
    {
        // Try IPv4 first to avoid resolution delays
        $u = parse_url($origin);
        if ($u && isset($u['host']) && ($u['host'] === 'localhost' || $u['host'] === '127.0.0.1')) {
            $port = isset($u['port']) ? (':' . $u['port']) : '';
            $scheme = $u['scheme'] ?? 'http';

            // Try 127.0.0.1
            $probeUrl1 = $scheme . '://127.0.0.1' . $port . '/@vite/client';
            $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
            if (@file_get_contents($probeUrl1, false, $ctx) !== false)
                return true;

            // Try localhost
            $probeUrl2 = $scheme . '://localhost' . $port . '/@vite/client';
            if (@file_get_contents($probeUrl2, false, $ctx) !== false)
                return true;
        }

        $probeUrl = rtrim($origin, '/') . '/@vite/client';
        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout, 'ignore_errors' => true],
            'https' => ['timeout' => $timeout, 'ignore_errors' => true],
        ]);
        return @file_get_contents($probeUrl, false, $ctx) !== false;
    }

    public static function load(string $entry, string $origin, string $bootScript): string
    {
        try {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
        } catch (Throwable $e) {
        }

        $devEntry = self::$devEntryMap[$entry] ?? $entry;
        $devMarker = "<script>try{console.log('[ViteBoot] DEV emission active', { origin: '" . addslashes($origin) . "', entry: '" . addslashes($devEntry) . "' });}catch(_){}</script>\n";

        // Use root-relative paths with a virtual prefix to force traffic through router.php proxy
        // This avoids host servers (like Python) serving raw source files from disk
        return $bootScript . $devMarker .
            "<script crossorigin=\"anonymous\" type=\"module\" src=\"/__wf_vite/@vite/client\"></script>\n" .
            "<script crossorigin=\"anonymous\" type=\"module\" src=\"/__wf_vite/" . ltrim($devEntry, '/') . "?v=" . time() . "\"></script>";
    }

    public static function loadCss(string $entry, string $origin): ?string
    {
        $list = self::$cssMap[$entry] ?? [];
        if (empty($list))
            return null;

        $html = '';
        foreach ($list as $p) {
            // Use JS injection (Vite standard) instead of direct link
            // This ensures HMR and proper Tailwind processing
            // Added cachebust to prevent stale cached failures
            $src = '/__wf_vite/' . ltrim($p, '/') . '?import&v=' . time();
            $html .= '<script type="module" src="' . $src . '"></script>';
        }
        return $html;
    }
}
