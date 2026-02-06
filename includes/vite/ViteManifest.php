<?php

/**
 * Handles Vite manifest loading and entry resolution
 */
class ViteManifest
{
    private static $manifest = null;

    public static function getPath(): ?string
    {
        $baseDir = ViteConfig::getProjectRoot();
        $candidates = [
            $baseDir . '/dist/manifest.json',
            $baseDir . '/dist/.vite/manifest.json',
        ];
        foreach ($candidates as $cand) {
            if (file_exists($cand))
                return $cand;
        }
        error_log("[ViteManifest] Manifest not found in: " . implode(', ', $candidates));
        return null;
    }

    public static function getManifest(): ?array
    {
        if (isset($_GET['wf_debug_manifest'])) {
            $path = self::getPath();
            header('Content-Type: text/plain');
            echo "PATH: $path\n";
            echo "MTIME: " . date('Y-m-d H:i:s', filemtime($path)) . "\n";
            echo "RAW CONTENT:\n";
            readfile($path);
            die();
        }
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $baseDir = ViteConfig::getProjectRoot();
        $paths = [
            $baseDir . '/dist/.vite/manifest.json',
            $baseDir . '/dist/manifest.json',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $content = @file_get_contents($path);
                if ($content) {
                    self::$manifest = json_decode($content, true);
                    return self::$manifest;
                }
            }
        }

        return null;
    }

    public static function resolve(string $entry, ?array $manifest): ?string
    {
        if (!$manifest)
            return null;
        if (isset($manifest[$entry]))
            return $entry;

        // Try resolve by 'name' field
        foreach ($manifest as $k => $meta) {
            if (is_array($meta) && isset($meta['name']) && $meta['name'] === $entry) {
                return $k;
            }
        }

        // Try common mapping: 'js/app.js' -> 'src/entries/app.js'
        if (preg_match('#^js/(.+)\.js$#', $entry, $m)) {
            $base = $m[1];
            $candidates = [
                "src/entries/{$base}.tsx",
                "src/entries/{$base}.ts",
                "src/entries/{$base}.jsx",
                "src/entries/{$base}.js",
            ];
            foreach ($candidates as $cand) {
                if (isset($manifest[$cand]))
                    return $cand;
            }
        }

        // Try css mapping
        if (preg_match('#^css/(.+\.css)$#', $entry, $m)) {
            $candidate = 'src/styles/entries/' . $m[1];
            if (isset($manifest[$candidate]))
                return $candidate;
        }

        return null;
    }

    public static function collectCss(string $resolvedKey, array $manifest): array
    {
        $visited = [];
        $cssFiles = [];
        $collect = function (string $key) use (&$collect, &$visited, &$cssFiles, $manifest) {
            if (isset($visited[$key]) || !isset($manifest[$key]))
                return;
            $visited[$key] = true;
            $a = $manifest[$key];

            if (!empty($a['file']) && is_string($a['file']) && preg_match('/\.css$/', $a['file'])) {
                $cssFiles[] = $a['file'];
            }
            if (!empty($a['css']) && is_array($a['css'])) {
                foreach ($a['css'] as $f)
                    $cssFiles[] = $f;
            }
            if (!empty($a['imports']) && is_array($a['imports'])) {
                foreach ($a['imports'] as $dep)
                    $collect($dep);
            }
        };

        $collect($resolvedKey);
        // Also check direct imports of the asset
        if (!empty($manifest[$resolvedKey]['imports'])) {
            foreach ($manifest[$resolvedKey]['imports'] as $dep)
                $collect($dep);
        }

        return array_values(array_unique($cssFiles));
    }
}
