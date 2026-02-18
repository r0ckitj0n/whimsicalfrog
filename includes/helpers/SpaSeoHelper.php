<?php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../functions/room_helpers.php';
require_once __DIR__ . '/../site_settings.php';

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

    public static function renderRoomDiscoverabilityNav(): string
    {
        $map = wf_get_room_slug_map();
        $byRoom = is_array($map['by_room'] ?? null) ? $map['by_room'] : [];
        if (empty($byRoom)) {
            return '';
        }

        $paths = [];
        foreach ($byRoom as $roomNumber => $_slug) {
            $path = wf_room_canonical_path((string) $roomNumber);
            if ($path === null || $path === '') {
                continue;
            }
            $paths[$path] = (string) $roomNumber;
        }

        if (empty($paths)) {
            return '';
        }

        ksort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        // Hidden from visual layout while still present in DOM for crawlers and assistive tech.
        $style = 'position:absolute!important;width:1px!important;height:1px!important;margin:-1px!important;padding:0!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;border:0!important;white-space:nowrap!important;';
        $html = '<nav aria-label="Room URLs" data-wf-seo-nav="rooms" style="' . $style . '"><ul>';
        foreach ($paths as $path => $roomNumber) {
            $safePath = self::escape($path);
            $label = self::escape('Room ' . $roomNumber);
            $html .= '<li><a href="' . $safePath . '">' . $label . '</a></li>';
        }
        $html .= '</ul></nav>';

        return $html;
    }

    public static function renderSocialDiscoverabilityNav(): string
    {
        $links = wf_social_links();
        if (!is_array($links) || empty($links)) {
            return '';
        }

        $pairs = [
            'Facebook' => (string) ($links['facebook'] ?? ''),
            'Instagram' => (string) ($links['instagram'] ?? ''),
            'X' => (string) (($links['x'] ?? '') ?: ($links['twitter'] ?? '')),
            'LinkedIn' => (string) ($links['linkedin'] ?? ''),
            'YouTube' => (string) ($links['youtube'] ?? ''),
            'Pinterest' => (string) ($links['pinterest'] ?? ''),
        ];

        $items = [];
        foreach ($pairs as $label => $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            $items[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        if (empty($items)) {
            return '';
        }

        $style = 'position:absolute!important;width:1px!important;height:1px!important;margin:-1px!important;padding:0!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;border:0!important;white-space:nowrap!important;';
        $html = '<nav aria-label="Social links" data-wf-seo-nav="social" style="' . $style . '"><ul>';
        foreach ($items as $item) {
            $safeUrl = self::escape($item['url']);
            $safeLabel = self::escape($item['label']);
            $html .= '<li><a href="' . $safeUrl . '">' . $safeLabel . '</a></li>';
        }
        $html .= '</ul></nav>';

        return $html;
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

        $siteName = wf_site_name();
        if ($siteName === '') {
            $siteName = self::DEFAULT_SITE_NAME;
        }
        $socialLinks = wf_social_links();

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $baseUrl . $canonicalPath,
            'image' => $baseUrl . '/images/backgrounds/background-roomA.webp',
            'structured_data' => self::buildOrganizationStructuredData($siteName, $baseUrl, $socialLinks),
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
        $canonicalPath = wf_room_canonical_path($roomNumber);
        if ($canonicalPath === null || $canonicalPath === '') {
            $canonicalPath = $path;
        }

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
            'canonical' => $baseUrl . $canonicalPath,
            'image' => $image,
            'structured_data' => self::buildRoomStructuredData($items, $baseUrl, $title, $description, $canonicalPath, $image),
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
            $list[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'item' => self::buildProductStructuredData($item, $baseUrl),
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
            $list[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'item' => self::buildProductStructuredData($item, $baseUrl),
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
                    ms.keywords,
                    ms.selling_points,
                    ms.competitive_advantages,
                    ms.customer_benefits,
                    ms.unique_selling_points,
                    ms.value_propositions,
                    ms.target_audience,
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
            $fallbackKeywords = self::decodeJsonList($row['keywords'] ?? null);
            if (!empty($fallbackKeywords)) {
                $keywords = array_values(array_unique(array_merge($keywords, $fallbackKeywords)));
            }

            $sellingPoints = self::decodeJsonList($row['selling_points'] ?? null);
            $competitiveAdvantages = self::decodeJsonList($row['competitive_advantages'] ?? null);
            $customerBenefits = self::decodeJsonList($row['customer_benefits'] ?? null);
            $uniqueSellingPoints = self::decodeJsonList($row['unique_selling_points'] ?? null);
            $valuePropositions = self::decodeJsonList($row['value_propositions'] ?? null);
            $targetAudience = trim((string) ($row['target_audience'] ?? ''));

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
                'target_audience' => $targetAudience,
                'selling_points' => $sellingPoints,
                'competitive_advantages' => $competitiveAdvantages,
                'customer_benefits' => $customerBenefits,
                'unique_selling_points' => $uniqueSellingPoints,
                'value_propositions' => $valuePropositions,
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
                    ms.keywords,
                    ms.selling_points,
                    ms.competitive_advantages,
                    ms.customer_benefits,
                    ms.unique_selling_points,
                    ms.value_propositions,
                    ms.target_audience,
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
            $fallbackKeywords = self::decodeJsonList($row['keywords'] ?? null);
            if (!empty($fallbackKeywords)) {
                $keywords = array_values(array_unique(array_merge($keywords, $fallbackKeywords)));
            }

            $sellingPoints = self::decodeJsonList($row['selling_points'] ?? null);
            $competitiveAdvantages = self::decodeJsonList($row['competitive_advantages'] ?? null);
            $customerBenefits = self::decodeJsonList($row['customer_benefits'] ?? null);
            $uniqueSellingPoints = self::decodeJsonList($row['unique_selling_points'] ?? null);
            $valuePropositions = self::decodeJsonList($row['value_propositions'] ?? null);
            $targetAudience = trim((string) ($row['target_audience'] ?? ''));

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
                'target_audience' => $targetAudience,
                'selling_points' => $sellingPoints,
                'competitive_advantages' => $competitiveAdvantages,
                'customer_benefits' => $customerBenefits,
                'unique_selling_points' => $uniqueSellingPoints,
                'value_propositions' => $valuePropositions,
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
        $hreflang = "\n<link rel=\"alternate\" hreflang=\"en\" href=\"{$canonical}\">\n<link rel=\"alternate\" hreflang=\"x-default\" href=\"{$canonical}\">";

        if (!empty($seo['structured_data']) && is_array($seo['structured_data'])) {
            $structured = "\n<script type=\"application/ld+json\">" . json_encode($seo['structured_data'], JSON_UNESCAPED_SLASHES) . "</script>";
        }

        return "\n<title>{$title}</title>\n<meta name=\"description\" content=\"{$description}\">\n<meta name=\"keywords\" content=\"{$keywords}\">\n<link rel=\"canonical\" href=\"{$canonical}\">{$hreflang}\n<meta property=\"og:title\" content=\"{$title}\">\n<meta property=\"og:description\" content=\"{$description}\">\n<meta property=\"og:image\" content=\"{$image}\">\n<meta property=\"og:url\" content=\"{$canonical}\">\n<meta property=\"og:type\" content=\"website\">\n<meta name=\"twitter:card\" content=\"summary_large_image\">\n<meta name=\"twitter:title\" content=\"{$title}\">\n<meta name=\"twitter:description\" content=\"{$description}\">\n<meta name=\"twitter:image\" content=\"{$image}\">{$structured}\n";
    }

    private static function buildOrganizationStructuredData(string $siteName, string $baseUrl, array $socialLinks): array
    {
        $sameAs = [];
        foreach (['facebook', 'instagram', 'twitter', 'pinterest', 'x', 'linkedin', 'youtube'] as $key) {
            $val = isset($socialLinks[$key]) ? trim((string) $socialLinks[$key]) : '';
            if ($val === '') {
                continue;
            }
            $sameAs[] = $val;
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => rtrim($baseUrl, '/') . '/',
            'logo' => self::absoluteUrl(wf_brand_logo_path(), $baseUrl),
        ];

        if (!empty($sameAs)) {
            $payload['sameAs'] = array_values(array_unique($sameAs));
        }

        return $payload;
    }

    private static function buildProductStructuredData(array $item, string $baseUrl): array
    {
        $price = number_format((float) ($item['retail_price'] ?? 0), 2, '.', '');
        $sku = (string) ($item['sku'] ?? '');
        $siteName = wf_site_name();
        if ($siteName === '') {
            $siteName = self::DEFAULT_SITE_NAME;
        }

        $product = [
            '@type' => 'Product',
            'name' => (string) ($item['title'] ?? ''),
            'description' => trim((string) ($item['description'] ?? '')),
            'sku' => $sku,
            'mpn' => $sku,
            'category' => (string) ($item['category_name'] ?? 'Uncategorized'),
            'image' => self::absoluteUrl((string) ($item['image_url'] ?? ''), $baseUrl),
            'url' => $baseUrl . '/shop?sku=' . rawurlencode($sku),
            'brand' => [
                '@type' => 'Brand',
                'name' => $siteName,
            ],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'USD',
                'price' => $price,
                'availability' => ((int) ($item['stock_quantity'] ?? 0)) > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'url' => $baseUrl . '/shop?sku=' . rawurlencode($sku),
            ],
        ];

        $keywordList = self::decodeJsonList($item['seo_keywords'] ?? null);
        if (!empty($keywordList)) {
            $product['keywords'] = implode(', ', $keywordList);
        }

        $targetAudience = trim((string) ($item['target_audience'] ?? ''));
        if ($targetAudience !== '') {
            $product['audience'] = [
                '@type' => 'Audience',
                'audienceType' => $targetAudience,
            ];
        }

        $additionalProperty = [];
        $marketingFields = [
            'Selling Points' => self::decodeJsonList($item['selling_points'] ?? null),
            'Competitive Advantages' => self::decodeJsonList($item['competitive_advantages'] ?? null),
            'Customer Benefits' => self::decodeJsonList($item['customer_benefits'] ?? null),
            'Unique Selling Points' => self::decodeJsonList($item['unique_selling_points'] ?? null),
            'Value Propositions' => self::decodeJsonList($item['value_propositions'] ?? null),
        ];
        foreach ($marketingFields as $name => $values) {
            if (empty($values)) {
                continue;
            }
            $additionalProperty[] = [
                '@type' => 'PropertyValue',
                'name' => $name,
                'value' => implode(' | ', $values),
            ];
        }
        if (!empty($additionalProperty)) {
            $product['additionalProperty'] = $additionalProperty;
        }

        return $product;
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

    private static function decodeJsonList(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_array($raw)) {
            return self::normalizeTextList($raw);
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return self::normalizeTextList($decoded);
        }

        return self::normalizeTextList([$text]);
    }

    private static function normalizeTextList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            $lower = strtolower($value);
            if ($lower === 'null' || $lower === 'undefined' || $lower === 'none' || $lower === 'n/a') {
                continue;
            }
            $out[] = $value;
        }
        return array_values(array_unique($out));
    }
}
