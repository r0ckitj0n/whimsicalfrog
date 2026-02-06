<?php
// Lightweight sitemap auto-sync: scans known directories for PHP pages/modals and updates sitemap_entries.
// Intended to be required early (e.g., router.php). Safe to run per-request; uses idempotent upserts.

// Guard against double inclusion
if (defined('WF_SITEMAP_AUTOSYNC_LOADED')) {
    return;
}
define('WF_SITEMAP_AUTOSYNC_LOADED', true);

require_once __DIR__ . '/../api/config.php';

function wf_sitemap_ensure_table()
{
    try {
        Database::execute("CREATE TABLE IF NOT EXISTS sitemap_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            label VARCHAR(255) NOT NULL,
            kind ENUM('page','modal') NOT NULL DEFAULT 'page',
            source VARCHAR(255) DEFAULT 'static',
            is_active TINYINT(1) DEFAULT 1,
            lastmod TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        // Fail soft
    }
}

function wf_sitemap_autosync()
{
    wf_sitemap_ensure_table();
    $entries = [];

    // Known static pages
    $static = [
        ['slug' => 'home', 'url' => '/', 'label' => 'Home', 'kind' => 'page'],
        ['slug' => 'landing', 'url' => '/landing.php', 'label' => 'Landing', 'kind' => 'page'],
        ['slug' => 'main-room', 'url' => '/room_main.php', 'label' => 'Main Room', 'kind' => 'page'],
        ['slug' => 'shop', 'url' => '/shop.php', 'label' => 'Shop', 'kind' => 'page'],
        ['slug' => 'cart', 'url' => '/cart.php', 'label' => 'Cart', 'kind' => 'page'],
        ['slug' => 'about', 'url' => '/about.php', 'label' => 'About', 'kind' => 'page'],
        ['slug' => 'contact', 'url' => '/contact.php', 'label' => 'Contact', 'kind' => 'page'],
        ['slug' => 'policy', 'url' => '/policy.php', 'label' => 'Policy', 'kind' => 'page'],
        ['slug' => 'privacy', 'url' => '/privacy.php', 'label' => 'Privacy', 'kind' => 'page'],
        ['slug' => 'terms', 'url' => '/terms.php', 'label' => 'Terms', 'kind' => 'page'],
        ['slug' => 'login', 'url' => '/login.php', 'label' => 'Login', 'kind' => 'page'],
        ['slug' => 'register', 'url' => '/register.php', 'label' => 'Register', 'kind' => 'page'],
        ['slug' => 'pos', 'url' => '/pos.php', 'label' => 'POS', 'kind' => 'page'],
    ];
    foreach ($static as $s) {
        $entries[$s['slug']] = $s + ['source' => 'seed'];
    }

    // Filesystem scans
    $root = realpath(__DIR__ . '/..');
    $scanSets = [
        ['dir' => $root, 'prefix' => '/', 'kind' => 'page', 'exclude' => ['index.php','router.php','vite-proxy.php','api','scripts','sections','components','includes','functions','vendor','node_modules','dist','storage','images','square','dev','setup','documentation','reports','data','config']],
        ['dir' => $root . '/sections', 'prefix' => '/sections', 'kind' => 'page', 'exclude' => []],
        ['dir' => $root . '/sections/tools', 'prefix' => '/sections/tools', 'kind' => 'page', 'exclude' => []],
        ['dir' => $root . '/components/modals', 'prefix' => '/components/modals', 'kind' => 'modal', 'exclude' => []],
    ];

    foreach ($scanSets as $set) {
        $dir = $set['dir'];
        if (!is_dir($dir)) {
            continue;
        }
        $files = glob($dir . '/*.php');
        foreach ($files as $file) {
            $base = basename($file);
            if (in_array($base, $set['exclude'], true)) {
                continue;
            }
            $slug = preg_replace('/\.php$/', '', $base);
            $url = rtrim($set['prefix'], '/') . '/' . $base;
            $label = ucwords(str_replace(['_', '-'], ' ', $slug));
            $key = $slug;
            $entries[$key] = [
                'slug' => $slug,
                'url' => $url,
                'label' => $label,
                'kind' => $set['kind'],
                'source' => 'auto',
            ];
        }
    }

    // Upsert active entries
    try {
        Database::beginTransaction();
        $activeSlugs = [];
        foreach ($entries as $e) {
            $activeSlugs[] = $e['slug'];
            Database::execute(
                "INSERT INTO sitemap_entries (slug, url, label, kind, source, is_active) VALUES (?, ?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE url = VALUES(url), label = VALUES(label), kind = VALUES(kind), source = VALUES(source), is_active = 1, updated_at = CURRENT_TIMESTAMP",
                [$e['slug'], $e['url'], $e['label'], $e['kind'], $e['source']]
            );
        }
        // Deactivate removed auto entries
        if (!empty($activeSlugs)) {
            $placeholders = implode(',', array_fill(0, count($activeSlugs), '?'));
            $params = $activeSlugs;
            Database::execute(
                "UPDATE sitemap_entries SET is_active = 0 WHERE source = 'auto' AND slug NOT IN ($placeholders)",
                $params
            );
        }
        Database::commit();
    } catch (Exception $e) {
        Database::rollBack();
    }
}

// Run sync
wf_sitemap_autosync();
