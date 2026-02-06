<?php

/**
 * Vite Helper (Conductor)
 * Handles Vite asset loading by delegating to modular components.
 */

require_once __DIR__ . '/vite/ViteConfig.php';
require_once __DIR__ . '/vite/ViteManifest.php';
require_once __DIR__ . '/vite/ViteDevLoader.php';
require_once __DIR__ . '/vite/ViteProdLoader.php';

function vite(string $entry): string
{
    static $emitted = [];

    // Normalize entry name to prevent double-emission of same asset under different names
    $normalized = $entry;
    if ($entry === 'src/entries/main.tsx') {
        $normalized = 'js/main.js';
    }

    if (isset($emitted[$normalized])) {
        return "<!-- vite: {$normalized} already emitted -->";
    }
    $emitted[$normalized] = true;

    $isDev = ViteConfig::isDevRequested();
    $origin = ViteConfig::getViteOrigin();
    $backendOrigin = ViteConfig::getBackendOrigin();
    $isHttps = ViteConfig::isHttps();
    $bootScript = "<script>window.__WF_BACKEND_ORIGIN = '" . addslashes($backendOrigin) . "';</script>\n";

    // Mixed content guard
    if ($isDev && $isHttps && stripos($origin, 'http://') === 0) {
        $isDev = false;
    }

    if ($isDev) {
        if (ViteDevLoader::probe($origin)) {
            return ViteDevLoader::load($entry, $origin, $bootScript);
        }
        ViteConfig::log('error', 'Dev server unreachable – check if Vite is running', ['origin' => $origin]);
    }

    $manifest = ViteManifest::getManifest();
    if (!$manifest) {
        return "<!-- Vite build error: manifest not found -->";
    }

    $resolvedKey = ViteManifest::resolve($entry, $manifest);
    if (!$resolvedKey || !isset($manifest[$resolvedKey])) {
        return "<!-- Entry not found in manifest: {$entry} -->";
    }

    // $publicBase = rtrim((string) getenv('WF_PUBLIC_BASE') ?: '', '/');
    $distBase = '/build-assets/';

    return ViteProdLoader::load($entry, $resolvedKey, $manifest[$resolvedKey], $distBase, $bootScript, $manifest);
}

function vite_css(string $entry): string
{
    static $emittedCss = [];
    if (isset($emittedCss[$entry])) {
        return "<!-- vite_css: {$entry} already emitted -->";
    }
    $emittedCss[$entry] = true;

    $isDev = ViteConfig::isDevRequested();
    $origin = ViteConfig::getViteOrigin();
    $isHttps = ViteConfig::isHttps();

    if ($isDev && !($isHttps && stripos($origin, 'http://') === 0)) {
        if (ViteDevLoader::probe($origin)) {
            $html = ViteDevLoader::loadCss($entry, $origin);
            return $html ?? "<!-- vite_css: no mapping for {$entry} in dev -->";
        }
        return "<!-- vite_css: dev requested but unreachable -->";
    }

    $manifest = ViteManifest::getManifest();
    if (!$manifest)
        return "<!-- vite_css: manifest not found -->";

    $resolvedKey = ViteManifest::resolve($entry, $manifest);
    if (!$resolvedKey)
        return "<!-- vite_css: no entry resolved for {$entry} -->";

    $cssFiles = ViteManifest::collectCss($resolvedKey, $manifest);
    if (empty($cssFiles))
        return "<!-- vite_css: no css resolved for {$entry} -->";

    // $publicBase = rtrim((string) getenv('WF_PUBLIC_BASE') ?: '', '/');
    $distBase = '/build-assets/';

    $html = '';
    foreach ($cssFiles as $f) {
        $html .= '<link rel="stylesheet" href="' . $distBase . $f . '">';
    }
    return $html;
}

function vite_entry(string $entry): string
{
    return vite($entry);
}
