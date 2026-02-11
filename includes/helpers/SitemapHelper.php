<?php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../functions/room_helpers.php';

class SitemapHelper
{
    public static function renderRobotsTxt(string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');
        return "User-agent: *\nAllow: /\nSitemap: {$base}/sitemap.xml\n";
    }

    public static function renderSitemapXml(string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');
        $urls = self::loadUrls($base);
        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $entry) {
            $loc = htmlspecialchars($entry['loc'], ENT_QUOTES, 'UTF-8');
            $lastmod = htmlspecialchars($entry['lastmod'], ENT_QUOTES, 'UTF-8');
            $changefreq = htmlspecialchars($entry['changefreq'], ENT_QUOTES, 'UTF-8');
            $priority = htmlspecialchars($entry['priority'], ENT_QUOTES, 'UTF-8');
            $lines[] = '  <url>';
            $lines[] = "    <loc>{$loc}</loc>";
            $lines[] = "    <lastmod>{$lastmod}</lastmod>";
            $lines[] = "    <changefreq>{$changefreq}</changefreq>";
            $lines[] = "    <priority>{$priority}</priority>";
            $lines[] = '  </url>';
        }
        $lines[] = '</urlset>';
        return implode("\n", $lines) . "\n";
    }

    private static function loadUrls(string $base): array
    {
        $today = gmdate('Y-m-d');
        $urls = [
            ['loc' => $base . '/', 'lastmod' => $today, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $base . '/shop', 'lastmod' => $today, 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => $base . '/about', 'lastmod' => $today, 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['loc' => $base . '/contact', 'lastmod' => $today, 'changefreq' => 'weekly', 'priority' => '0.7'],
        ];

        try {
            $roomRows = Database::queryAll(
                "SELECT room_number, updated_at
                 FROM room_settings
                 WHERE is_active = 1
                 ORDER BY display_order, room_number"
            );
            foreach ($roomRows as $row) {
                $roomNumber = trim((string) ($row['room_number'] ?? ''));
                if ($roomNumber === '') {
                    continue;
                }
                $slugPath = wf_room_canonical_path($roomNumber);
                if ($slugPath === null || $slugPath === '') {
                    continue;
                }
                $lastmodRaw = trim((string) ($row['updated_at'] ?? ''));
                $lastmod = $lastmodRaw !== '' ? substr($lastmodRaw, 0, 10) : $today;
                $urls[] = [
                    'loc' => $base . $slugPath,
                    'lastmod' => $lastmod,
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            }
        } catch (Throwable $e) {
            error_log('[SitemapHelper] Failed to load rooms for sitemap: ' . $e->getMessage());
        }

        try {
            $rows = Database::queryAll(
                "SELECT sku, updated_at
                 FROM items
                 WHERE status = 'live' AND is_active = 1 AND is_archived = 0
                 ORDER BY updated_at DESC
                 LIMIT 1000"
            );
            foreach ($rows as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $lastmodRaw = trim((string) ($row['updated_at'] ?? ''));
                $lastmod = $lastmodRaw !== '' ? substr($lastmodRaw, 0, 10) : $today;
                $urls[] = [
                    'loc' => $base . '/shop?sku=' . rawurlencode($sku),
                    'lastmod' => $lastmod,
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            }
        } catch (Throwable $e) {
            error_log('[SitemapHelper] Failed to load items for sitemap: ' . $e->getMessage());
        }

        return $urls;
    }
}
