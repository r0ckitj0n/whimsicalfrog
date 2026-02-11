<?php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../functions/room_helpers.php';

/**
 * Builds crawlable server-side SEO tags for the React SPA shell.
 */
class SpaSeoHelper
{
    private const DEFAULT_SITE_NAME = 'WhimsicalFrog';

    public static function renderTagsForPath(string $requestedPath): string
    {
        $path = self::normalizePath($requestedPath);
        $seo = self::buildSeoPayload($path);
        return self::renderTags($seo);
    }

    private static function buildSeoPayload(string $path): array
    {
        $roomNumber = self::resolveRoomNumberForPath($path);
        if ($roomNumber !== null) {
            return self::buildRoomSeoPayload($roomNumber, $path);
        }

        if ($path === '/shop') {
            return self::buildShopSeoPayload();
        }

        $pageType = self::pathToPageType($path);
        $settings = self::loadSeoSettings($pageType);
        $baseUrl = self::baseUrl();
        $canonicalPath = $path === '' ? '/' : $path;

        $title = trim((string) ($settings['page_title'] ?? $settings['site_title'] ?? self::DEFAULT_SITE_NAME));
        $description = trim((string) ($settings['meta_description'] ?? $settings['site_description'] ?? 'Whimsical products and custom creations.'));
        $keywords = trim((string) ($settings['site_keywords'] ?? 'whimsical frog, custom gifts, handmade products'));

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $baseUrl . $canonicalPath,
            'image' => $baseUrl . '/images/backgrounds/background-roomA.webp',
            'structured_data' => null,
        ];
    }

    public static function resolveRoomNumberForPath(string $requestedPath): ?string
    {
        $path = self::normalizePath($requestedPath);
        if (!preg_match('#^/rooms/([^/]+)$#', $path, $m)) {
            return null;
        }

        $slug = trim((string) ($m[1] ?? ''));
        if ($slug === '') {
            return null;
        }

        $roomNumber = wf_resolve_room_number_from_slug($slug);
        if ($roomNumber === null || $roomNumber === '') {
            return null;
        }

        return (string) $roomNumber;
    }

    private static function buildRoomSeoPayload(string $roomNumber, string $path): array
    {
        $baseUrl = self::baseUrl();
        $room = self::loadRoomInfo($roomNumber);
        $items = self::loadRoomSeoItems($roomNumber);
        $settings = self::loadSeoSettings('room');

        $titleBase = trim((string) ($room['room_name'] ?? 'Room ' . $roomNumber));
        $title = trim((string) ($settings['page_title'] ?? ($titleBase . ' | ' . self::DEFAULT_SITE_NAME)));

        $firstDescription = $items[0]['description'] ?? '';
        $fallbackDescription = $firstDescription !== '' ? self::truncate($firstDescription, 160) : ('Explore ' . $titleBase . ' at ' . self::DEFAULT_SITE_NAME . '.');
        $description = trim((string) ($room['description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($settings['meta_description'] ?? $settings['site_description'] ?? $fallbackDescription));
        }

        $keywordSet = [];
        foreach ($items as $item) {
            foreach ($item['seo_keywords'] as $keyword) {
                $normalized = strtolower(trim((string) $keyword));
                if ($normalized !== '' && !in_array($normalized, $keywordSet, true)) {
                    $keywordSet[] = $normalized;
                }
                if (count($keywordSet) >= 30) {
                    break 2;
                }
            }
        }
        $roomKeyword = strtolower(trim((string) ($room['room_name'] ?? 'room')));
        if ($roomKeyword !== '' && !in_array($roomKeyword, $keywordSet, true)) {
            array_unshift($keywordSet, $roomKeyword);
        }
        $metaKeywords = implode(', ', $keywordSet);
        if ($metaKeywords === '') {
            $metaKeywords = trim((string) ($settings['site_keywords'] ?? 'whimsical frog, room catalog'));
        }

        $image = !empty($items[0]['image_url']) ? self::absoluteUrl((string) $items[0]['image_url'], $baseUrl) : ($baseUrl . '/images/backgrounds/background-roomA.webp');

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $metaKeywords,
            'canonical' => $baseUrl . $path,
            'image' => $image,
            'structured_data' => self::buildRoomStructuredData($items, $baseUrl, $title, $description, $path, $image),
        ];
    }

    private static function buildShopSeoPayload(): array
    {
        $settings = self::loadSeoSettings('shop');
        $baseUrl = self::baseUrl();
        $items = self::loadShopSeoItems();

        $keywords = [];
        foreach ($items as $item) {
            foreach ($item['seo_keywords'] as $keyword) {
                $normalized = strtolower(trim((string) $keyword));
                if ($normalized !== '' && !in_array($normalized, $keywords, true)) {
                    $keywords[] = $normalized;
                }
                if (count($keywords) >= 30) {
                    break 2;
                }
            }
        }

        $metaKeywords = trim((string) ($settings['site_keywords'] ?? ''));
        if (!empty($keywords)) {
            $metaKeywords = trim($metaKeywords . ', ' . implode(', ', $keywords), ', ');
        }
        if ($metaKeywords === '') {
            $metaKeywords = 'custom products, handcrafted gifts, whimsical frog shop';
        }

        $defaultDescription = 'Browse handcrafted items from WhimsicalFrog.';
        if (!empty($items[0]['description'])) {
            $defaultDescription = self::truncate($items[0]['description'], 160);
        }

        $title = trim((string) ($settings['page_title'] ?? $settings['site_title'] ?? 'Shop | ' . self::DEFAULT_SITE_NAME));
        $description = trim((string) ($settings['meta_description'] ?? $settings['site_description'] ?? $defaultDescription));
        $image = !empty($items[0]['image_url']) ? self::absoluteUrl((string) $items[0]['image_url'], $baseUrl) : ($baseUrl . '/images/backgrounds/background-roomS.webp');

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $metaKeywords,
            'canonical' => $baseUrl . '/shop',
            'image' => $image,
            'structured_data' => self::buildShopStructuredData($items, $baseUrl, $title, $description, $image),
        ];
    }

    private static function buildShopStructuredData(array $items, string $baseUrl, string $title, string $description, string $image): array
    {
        $list = [];
        $position = 1;
        foreach ($items as $item) {
            if ($position > 100) {
                break;
            }
            $price = number_format((float) ($item['retail_price'] ?? 0), 2, '.', '');
            $product = [
                '@type' => 'Product',
                'name' => $item['title'],
                'description' => self::truncate($item['description'], 400),
                'sku' => $item['sku'],
                'category' => $item['category_name'],
                'image' => self::absoluteUrl((string) $item['image_url'], $baseUrl),
                'offers' => [
                    '@type' => 'Offer',
                    'priceCurrency' => 'USD',
                    'price' => $price,
                    'availability' => ((int) ($item['stock_quantity'] ?? 0)) > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => $baseUrl . '/shop?sku=' . rawurlencode((string) $item['sku']),
                ],
            ];
            if (!empty($item['seo_keywords'])) {
                $product['keywords'] = implode(', ', $item['seo_keywords']);
            }

            $list[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'item' => $product,
            ];
            $position++;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $title,
            'description' => $description,
            'url' => $baseUrl . '/shop',
            'image' => $image,
            'mainEntity' => [
                '@type' => 'ItemList',
                'name' => 'Shop Catalog',
                'numberOfItems' => count($list),
                'itemListElement' => $list,
            ],
        ];
    }

    private static function buildRoomStructuredData(array $items, string $baseUrl, string $title, string $description, string $path, string $image): array
    {
        $list = [];
        $position = 1;
        foreach ($items as $item) {
            if ($position > 100) {
                break;
            }
            $price = number_format((float) ($item['retail_price'] ?? 0), 2, '.', '');
            $product = [
                '@type' => 'Product',
                'name' => $item['title'],
                'description' => self::truncate($item['description'], 400),
                'sku' => $item['sku'],
                'category' => $item['category_name'],
                'image' => self::absoluteUrl((string) $item['image_url'], $baseUrl),
                'offers' => [
                    '@type' => 'Offer',
                    'priceCurrency' => 'USD',
                    'price' => $price,
                    'availability' => ((int) ($item['stock_quantity'] ?? 0)) > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => $baseUrl . '/shop?sku=' . rawurlencode((string) $item['sku']),
                ],
            ];
            if (!empty($item['seo_keywords'])) {
                $product['keywords'] = implode(', ', $item['seo_keywords']);
            }

            $list[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'item' => $product,
            ];
            $position++;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $title,
            'description' => $description,
            'url' => $baseUrl . $path,
            'image' => $image,
            'mainEntity' => [
                '@type' => 'ItemList',
                'name' => $title,
                'numberOfItems' => count($list),
                'itemListElement' => $list,
            ],
        ];
    }

    private static function loadShopSeoItems(): array
    {
        try {
            $rows = Database::queryAll(
                "SELECT
                    i.sku,
                    i.name,
                    i.description,
                    i.retail_price,
                    i.stock_quantity,
                    COALESCE(img.image_path, i.image_url, 'images/items/placeholder.webp') AS image_url,
                    COALESCE(ms.suggested_title, i.name) AS seo_title,
                    COALESCE(ms.suggested_description, i.description) AS seo_description,
                    ms.seo_keywords,
                    COALESCE(c.name, 'Uncategorized') AS category_name
                 FROM items i
                 LEFT JOIN categories c ON i.category_id = c.id
                 LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
                 LEFT JOIN marketing_suggestions ms
                    ON ms.sku = i.sku
                    AND ms.created_at = (
                        SELECT MAX(ms2.created_at)
                        FROM marketing_suggestions ms2
                        WHERE ms2.sku = i.sku
                    )
                 WHERE i.status = 'live' AND i.is_active = 1 AND i.is_archived = 0
                 ORDER BY i.name ASC
                 LIMIT 250"
            );
        } catch (Throwable $e) {
            error_log('[SpaSeoHelper] Failed to load shop SEO items: ' . $e->getMessage());
            $rows = [];
        }

        $items = [];
        foreach ($rows as $row) {
            $decodedKeywords = json_decode((string) ($row['seo_keywords'] ?? '[]'), true);
            $keywords = is_array($decodedKeywords) ? array_values(array_filter(array_map('trim', $decodedKeywords), static fn($v) => $v !== '')) : [];

            $title = trim((string) ($row['seo_title'] ?? $row['name'] ?? ''));
            if ($title === '') {
                $title = trim((string) ($row['name'] ?? 'Item'));
            }

            $description = trim((string) ($row['seo_description'] ?? $row['description'] ?? ''));
            if ($description === '') {
                $description = 'Whimsical handcrafted product.';
            }

            $items[] = [
                'sku' => (string) ($row['sku'] ?? ''),
                'title' => $title,
                'description' => $description,
                'retail_price' => (float) ($row['retail_price'] ?? 0),
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'image_url' => (string) ($row['image_url'] ?? 'images/items/placeholder.webp'),
                'seo_keywords' => $keywords,
                'category_name' => (string) ($row['category_name'] ?? 'Uncategorized'),
            ];
        }

        return $items;
    }

    private static function loadSeoSettings(string $pageType): array
    {
        try {
            $rows = Database::queryAll(
                "SELECT setting_name, setting_value
                 FROM seo_settings
                 WHERE page_type = ? OR page_type = 'global'
                 ORDER BY page_type DESC",
                [$pageType]
            );
        } catch (Throwable $e) {
            error_log('[SpaSeoHelper] Failed to load SEO settings for page type ' . $pageType . ': ' . $e->getMessage());
            return [];
        }

        $settings = [];
        foreach ($rows as $row) {
            $name = (string) ($row['setting_name'] ?? '');
            if ($name === '' || array_key_exists($name, $settings)) {
                continue;
            }
            $settings[$name] = (string) ($row['setting_value'] ?? '');
        }

        return $settings;
    }

    private static function loadRoomInfo(string $roomNumber): array
    {
        try {
            $row = Database::queryOne(
                "SELECT room_number, room_name, door_label, description
                 FROM room_settings
                 WHERE room_number = ? AND is_active = 1
                 LIMIT 1",
                [$roomNumber]
            );
        } catch (Throwable $e) {
            error_log('[SpaSeoHelper] Failed to load room info: ' . $e->getMessage());
            $row = null;
        }

        if (!is_array($row)) {
            return [
                'room_number' => $roomNumber,
                'room_name' => 'Room ' . $roomNumber,
                'description' => '',
            ];
        }

        return $row;
    }

    private static function loadRoomSeoItems(string $roomNumber): array
    {
        try {
            $categoryRows = Database::queryAll(
                "SELECT DISTINCT category_id
                 FROM room_category_assignments
                 WHERE room_number = ?",
                [$roomNumber]
            );
            $categoryIds = [];
            foreach ($categoryRows as $row) {
                $id = (int) ($row['category_id'] ?? 0);
                if ($id > 0) {
                    $categoryIds[] = $id;
                }
            }

            $explicitRows = Database::queryAll(
                "SELECT DISTINCT item_sku
                 FROM area_mappings
                 WHERE room_number = ? AND is_active = 1 AND mapping_type = 'item' AND item_sku IS NOT NULL AND item_sku <> ''",
                [$roomNumber]
            );
            $explicitSkus = [];
            foreach ($explicitRows as $row) {
                $sku = trim((string) ($row['item_sku'] ?? ''));
                if ($sku !== '') {
                    $explicitSkus[] = $sku;
                }
            }

            $whereParts = [];
            $params = [];
            if (!empty($categoryIds)) {
                $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
                $whereParts[] = "i.category_id IN ($placeholders)";
                $params = array_merge($params, $categoryIds);
            }
            if (!empty($explicitSkus)) {
                $placeholders = implode(',', array_fill(0, count($explicitSkus), '?'));
                $whereParts[] = "i.sku IN ($placeholders)";
                $params = array_merge($params, $explicitSkus);
            }
            if (empty($whereParts)) {
                return [];
            }

            $rows = Database::queryAll(
                "SELECT DISTINCT
                    i.sku,
                    i.name,
                    i.description,
                    i.retail_price,
                    i.stock_quantity,
                    COALESCE(img.image_path, i.image_url, 'images/items/placeholder.webp') AS image_url,
                    COALESCE(ms.suggested_title, i.name) AS seo_title,
                    COALESCE(ms.suggested_description, i.description) AS seo_description,
                    ms.seo_keywords,
                    COALESCE(c.name, 'Uncategorized') AS category_name
                 FROM items i
                 LEFT JOIN categories c ON i.category_id = c.id
                 LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
                 LEFT JOIN marketing_suggestions ms
                    ON ms.sku = i.sku
                    AND ms.created_at = (
                        SELECT MAX(ms2.created_at)
                        FROM marketing_suggestions ms2
                        WHERE ms2.sku = i.sku
                    )
                 WHERE i.status = 'live' AND i.is_active = 1 AND i.is_archived = 0
                   AND (" . implode(' OR ', $whereParts) . ")
                 ORDER BY i.name ASC
                 LIMIT 250",
                $params
            );
        } catch (Throwable $e) {
            error_log('[SpaSeoHelper] Failed to load room SEO items: ' . $e->getMessage());
            $rows = [];
        }

        $items = [];
        foreach ($rows as $row) {
            $decodedKeywords = json_decode((string) ($row['seo_keywords'] ?? '[]'), true);
            $keywords = is_array($decodedKeywords) ? array_values(array_filter(array_map('trim', $decodedKeywords), static fn($v) => $v !== '')) : [];

            $title = trim((string) ($row['seo_title'] ?? $row['name'] ?? ''));
            if ($title === '') {
                $title = trim((string) ($row['name'] ?? 'Item'));
            }

            $description = trim((string) ($row['seo_description'] ?? $row['description'] ?? ''));
            if ($description === '') {
                $description = 'Whimsical handcrafted product.';
            }

            $items[] = [
                'sku' => (string) ($row['sku'] ?? ''),
                'title' => $title,
                'description' => $description,
                'retail_price' => (float) ($row['retail_price'] ?? 0),
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'image_url' => (string) ($row['image_url'] ?? 'images/items/placeholder.webp'),
                'seo_keywords' => $keywords,
                'category_name' => (string) ($row['category_name'] ?? 'Uncategorized'),
            ];
        }

        return $items;
    }

    private static function renderTags(array $seo): string
    {
        $title = self::escape($seo['title'] ?? self::DEFAULT_SITE_NAME);
        $description = self::escape($seo['description'] ?? '');
        $keywords = self::escape($seo['keywords'] ?? '');
        $canonical = self::escape($seo['canonical'] ?? (self::baseUrl() . '/'));
        $image = self::escape($seo['image'] ?? (self::baseUrl() . '/images/backgrounds/background-roomA.webp'));
        $structured = '';

        if (!empty($seo['structured_data']) && is_array($seo['structured_data'])) {
            $structured = "\n<script type=\"application/ld+json\">" . json_encode($seo['structured_data'], JSON_UNESCAPED_SLASHES) . "</script>";
        }

        return "\n<title>{$title}</title>\n<meta name=\"description\" content=\"{$description}\">\n<meta name=\"keywords\" content=\"{$keywords}\">\n<link rel=\"canonical\" href=\"{$canonical}\">\n<meta property=\"og:title\" content=\"{$title}\">\n<meta property=\"og:description\" content=\"{$description}\">\n<meta property=\"og:image\" content=\"{$image}\">\n<meta property=\"og:url\" content=\"{$canonical}\">\n<meta property=\"og:type\" content=\"website\">\n<meta name=\"twitter:card\" content=\"summary_large_image\">\n<meta name=\"twitter:title\" content=\"{$title}\">\n<meta name=\"twitter:description\" content=\"{$description}\">\n<meta name=\"twitter:image\" content=\"{$image}\">{$structured}\n";
    }

    private static function baseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $https = $_SERVER['HTTPS'] ?? '';
        $isHttps = ($forwardedProto === 'https') || (!empty($https) && strtolower((string) $https) !== 'off');
        $scheme = $isHttps ? 'https' : 'http';
        return $scheme . '://' . $host;
    }

    private static function absoluteUrl(string $pathOrUrl, string $baseUrl): string
    {
        $candidate = trim($pathOrUrl);
        if ($candidate === '') {
            return $baseUrl . '/images/items/placeholder.webp';
        }
        if (preg_match('/^https?:\\/\\//i', $candidate)) {
            return $candidate;
        }
        return rtrim($baseUrl, '/') . '/' . ltrim($candidate, '/');
    }

    private static function truncate(string $value, int $limit): string
    {
        $trimmed = trim($value);
        if (mb_strlen($trimmed) <= $limit) {
            return $trimmed;
        }
        return rtrim(mb_substr($trimmed, 0, $limit - 3)) . '...';
    }

    private static function normalizePath(string $requestedPath): string
    {
        $path = trim($requestedPath);
        if ($path === '') {
            return '/';
        }
        $normalized = '/' . ltrim($path, '/');
        return rtrim($normalized, '/') === '' ? '/' : rtrim($normalized, '/');
    }

    private static function pathToPageType(string $path): string
    {
        return match ($path) {
            '/', '/index', '/index.html' => 'home',
            '/about' => 'about',
            '/contact' => 'contact',
            '/shop' => 'shop',
            default => 'home',
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
