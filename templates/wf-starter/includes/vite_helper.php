<?php

/**
 * Minimal Vite helper for dev/prod asset injection.
 */
function vite(string $entry = 'src/js/app.js'): string
{
    $dev = getenv('WF_VITE_DEV') === '1' || defined('VITE_FORCE_DEV');
    $origin = getenv('WF_VITE_ORIGIN') ?: 'http://127.0.0.1:5176';

    if ($dev) {
        $entryUrl = rtrim($origin, '/') . '/' . ltrim($entry, '/');
        return implode("\n", [
          '<script type="module" src="' . htmlspecialchars(rtrim($origin, '/') . '/@vite/client') . '"></script>',
          '<script type="module" src="' . htmlspecialchars($entryUrl) . '"></script>',
        ]);
    }

    $manifestPath = __DIR__ . '/../dist/.vite/manifest.json';
    if (!is_file($manifestPath)) {
        // Fallback to dev injection if manifest missing
        $entryUrl = rtrim($origin, '/') . '/' . ltrim($entry, '/');
        return implode("\n", [
          '<script type="module" src="' . htmlspecialchars(rtrim($origin, '/') . '/@vite/client') . '"></script>',
          '<script type="module" src="' . htmlspecialchars($entryUrl) . '"></script>',
        ]);
    }
    $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];

    // Find by exact key or by matching the entry basename
    $chunk = $manifest[$entry] ?? null;
    if (!$chunk) {
        $basename = basename($entry);
        foreach ($manifest as $k => $v) {
            if (basename($k) === $basename) {
                $chunk = $v;
                break;
            }
        }
    }
    if (!$chunk || empty($chunk['file'])) {
        return '<!-- vite entry not found in manifest: ' . htmlspecialchars($entry) . ' -->';
    }
    $tags = [];
    // CSS imports
    if (!empty($chunk['css']) && is_array($chunk['css'])) {
        foreach ($chunk['css'] as $css) {
            $tags[] = '<link rel="stylesheet" href="/dist/' . htmlspecialchars($css) . '">';
        }
    }
    // JS module
    $tags[] = '<script type="module" src="/dist/' . htmlspecialchars($chunk['file']) . '"></script>';
    return implode("\n", $tags);
}
