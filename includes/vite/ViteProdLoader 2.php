<?php

/**
 * Handles asset loading in production mode (using manifest and hashed files)
 */
class ViteProdLoader
{
    public static function load(string $entry, string $resolvedKey, array $asset, string $distBase, string $bootScript, ?array $manifest): string
    {
        $file = $asset['file'];
        $entrySrc = $distBase . $file;
        $ver = self::getFileVersion($file);
        $manifestPath = ViteManifest::getPath();
        $manifestMtime = $manifestPath ? filemtime($manifestPath) : 0;

        // Force absolute cache bust with current time if in debug mode or always for now
        // $cacheBust = time();
        // $entrySrc .= (strpos($entrySrc, '?') === false ? '?v=' . $cacheBust : '&v=' . $cacheBust);

        $bootJs = "(function(){try{var src='" . addslashes($entrySrc) . "';try{console.log('[ViteBoot-V4] Emitting entry', src, 'Manifest:', '" . addslashes($manifestPath) . "', 'Mtime:', " . $manifestMtime . ");}catch(_){} var s=document.createElement('script');s.type='module';s.crossOrigin='anonymous';s.src=src;s.onerror=function(){try{console.error('[ViteBoot-V4] Failed to load entry (strict mode):', '" . addslashes($entrySrc) . "');}catch(_){} };var ref=document.currentScript; if(ref&&ref.parentNode){ref.parentNode.insertBefore(s, ref.nextSibling);}else{document.head.appendChild(s);} }catch(_){ try{ console.error('[ViteBoot-V4] Boot emission failed for entry', '" . addslashes($entrySrc) . "'); }catch(__){} }})();";
        $html = $bootScript . "<script>" . $bootJs . "</script>";

        if ($manifest) {
            $cssFiles = ViteManifest::collectCss($resolvedKey, $manifest);
            foreach ($cssFiles as $cssFile) {
                $html .= "<link rel=\"stylesheet\" href=\"{$distBase}{$cssFile}\">";
            }
        }

        return $html;
    }

    private static function findLatest(string $stem): ?string
    {
        $root = ViteConfig::getProjectRoot();
        $patterns = [
            $root . '/dist/' . $stem . '-*.js',
            $root . '/dist/assets/js/main.js-*.js',
            $root . '/dist/assets/js/app.js-*.js',
            $root . '/dist/assets/*main.js-*.js',
            $root . '/dist/assets/*app.js-*.js',
        ];
        $candidates = [];
        foreach ($patterns as $pattern) {
            $list = glob($pattern);
            if (is_array($list)) {
                foreach ($list as $f)
                    if (is_file($f))
                        $candidates[] = $f;
            }
        }
        if (empty($candidates))
            return null;

        usort($candidates, function ($a, $b) {
            return @filemtime($b) <=> @filemtime($a);
        });
        $latest = $candidates[0];
        $prefix = $root . '/dist/assets/';

        if (strpos($latest, $prefix) === 0) {
            return 'assets/' . ltrim(substr($latest, strlen($prefix)), '/');
        }
        return ltrim(str_replace($root . '/dist/', '', $latest), '/');
    }

    private static function getFileVersion(string $file): string
    {
        try {
            $root = ViteConfig::getProjectRoot();
            return (string) @filemtime($root . '/dist/' . $file);
        } catch (Throwable $e) {
            return (string) time();
        }
    }
}
