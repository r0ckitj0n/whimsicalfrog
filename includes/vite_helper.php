<?php

// A helper function to handle loading Vite assets in both development and production.
function vite(string $entry): string
{
    // Vite puts manifest at dist/.vite/manifest.json by default (Vite v7)
    $manifestPath = __DIR__ . '/../dist/.vite/manifest.json';
    $hotPath = __DIR__ . '/../hot';

    // Check if we are in development mode (dev server is running)
    if (file_exists($hotPath)) {
        // In development, load scripts from the Vite dev server. The 'hot' file written by
        // Vite contains the full origin (e.g. "http://localhost:5177"). Read it so we
        // automatically match whatever port Vite is using, instead of hard-coding 5176.
        $viteServer = trim(file_get_contents($hotPath));
        // Fallback if the file is empty or unreadable
        if (empty($viteServer)) {
            $viteServer = 'http://localhost:5176';
        }

        return "<script type=\"module\" src=\"{$viteServer}/@vite/client\"></script>\n" .
               "<script type=\"module\" src=\"{$viteServer}/{$entry}\"></script>";
    }

    // In production, we load the bundled assets from the manifest.
    if (!file_exists($manifestPath)) {
        return '<!-- manifest.json not found -->';
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);

    if (!isset($manifest[$entry])) {
        return "<!-- Entry not found in manifest: {$entry} -->";
    }

    $asset = $manifest[$entry];
    $html = "<script type=\"module\" src=\"/dist/{$asset['file']}\"></script>";

    if (!empty($asset['css'])) {
        foreach ($asset['css'] as $cssFile) {
            $html .= "<link rel=\"stylesheet\" href=\"/dist/{$cssFile}\">";
        }
    }

    return $html;
}
