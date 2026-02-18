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
    private const DEFAULT_PRODUCT_IMAGE = '/images/items/placeholder.webp';

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

    public static function renderCatalogDiscoverabilityNav(): string
    {
        $items = self::loadShopSeoItems();
        if (empty($items)) {
            return '';
        }

        $style = 'position:absolute!important;width:1px!important;height:1px!important;margin:-1px!important;padding:0!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;border:0!important;white-space:nowrap!important;';
        $html = '<nav aria-label="Catalog URLs" data-wf-seo-nav="catalog" style="' . $style . '"><ul>';
        $categoryMap = [];
        foreach ($items as $item) {
            $categorySlug = wf_slugify((string) ($item['category_slug'] ?? ''));
            if ($categorySlug === null || $categorySlug === '') {
                continue;
            }
            $categoryMap[$categorySlug] = (string) ($item['category_name'] ?? ucwords(str_replace('-', ' ', $categorySlug)));
        }
        ksort($categoryMap, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($categoryMap as $slug => $label) {
            $categoryPath = '/shop/category/' . rawurlencode($slug);
            $html .= '<li><a href="' . self::escape($categoryPath) . '">' . self::escape($label) . '</a></li>';
        }

        foreach ($items as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $path = self::canonicalProductPath($item);
            $label = trim((string) ($item['name'] ?? $item['title'] ?? $sku));
            $html .= '<li><a href="' . self::escape($path) . '">' . self::escape($label) . '</a></li>';
        }
        $html .= '</ul></nav>';

        return $html;
    }

    public static function renderSeoShellForPath(string $requestedPath): string
    {
        $path = self::normalizePath($requestedPath);
        $baseUrl = self::baseUrl();
        $siteName = wf_site_name();
        if ($siteName === '') {
            $siteName = self::DEFAULT_SITE_NAME;
        }

        $content = self::renderDefaultSeoShellContent($siteName);
        $productIdentifier = self::extractProductIdentifier($path);
        if ($productIdentifier !== null) {
            $product = self::resolveProductForIdentifier($productIdentifier);
            if (is_array($product)) {
                $content = self::renderProductSeoShellContent($product);
            }
        } else {
            $categorySlug = self::extractShopCategorySlug($path);
            if ($categorySlug !== null) {
                $content = self::renderCategorySeoShellContent($categorySlug);
            } else {
                $roomNumber = self::resolveRoomNumberForPath($path);
                if ($roomNumber !== null) {
                    $content = self::renderRoomSeoShellContent($roomNumber);
                } else {
                    $content = match ($path) {
                        '/shop' => self::renderShopSeoShellContent($siteName),
                        '/about' => self::renderAboutSeoShellContent($siteName),
                        '/contact' => self::renderContactSeoShellContent($siteName, $baseUrl),
                        default => self::renderDefaultSeoShellContent($siteName),
                    };
                }
            }
        }

        return '<main id="wf-seo-shell" style="max-width:64rem;margin:0 auto;padding:2.5rem 1.25rem;color:#fff;">' . $content . '</main>';
    }

    private static function buildSeoPayload(string $path): array
    {
        $productIdentifier = self::extractProductIdentifier($path);
        if ($productIdentifier !== null) {
            return self::buildProductSeoPayload($productIdentifier);
        }

        $categorySlug = self::extractShopCategorySlug($path);
        if ($categorySlug !== null) {
            return self::buildShopCategorySeoPayload($categorySlug);
        }

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

        $defaults = self::defaultPageMeta($canonicalPath);
        $title = trim((string) (($defaults['title'] ?? '') !== '' ? $defaults['title'] : ($settings['page_title'] ?? $settings['site_title'] ?? self::DEFAULT_SITE_NAME)));
        $description = trim((string) (($defaults['description'] ?? '') !== '' ? $defaults['description'] : ($settings['meta_description'] ?? $settings['site_description'] ?? 'Whimsical products and custom creations.')));
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
            'image' => $baseUrl . ($defaults['image_path'] ?? '/images/backgrounds/background-roomA.webp'),
            'og_type' => 'website',
            'structured_data' => self::buildOrganizationStructuredData($siteName, $baseUrl, $socialLinks),
        ];
    }

    public static function resolveCanonicalProductPathFromToken(string $identifier): ?string
    {
        $product = self::resolveProductForIdentifier($identifier);
        if ($product === null) {
            return null;
        }

        return self::canonicalProductPath($product);
    }

    public static function canonicalProductPathForNameSku(string $name, string $sku): string
    {
        $item = ['name' => $name, 'title' => $name, 'sku' => $sku];
        return self::canonicalProductPath($item);
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
        $canonicalPath = wf_room_canonical_path($roomNumber);
        if ($canonicalPath === null || $canonicalPath === '') {
            $canonicalPath = $path;
        }

        $titleBase = trim((string) ($room['room_name'] ?? 'Room ' . $roomNumber));
        $titleLead = self::truncateHard($titleBase, 24);
        $title = $titleLead . ' | Custom Gifts & Services | Whimsical Frog';
        $description = self::truncate(
            $titleBase . ' custom gifts and handmade services at Whimsical Frog. Shop personalized products, themed collections, and made-to-order requests with clear shipping and turnaround guidance.',
            160
        );

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
        foreach ([
            'custom gifts',
            'handmade services',
            'personalized products',
            'made-to-order gifts',
            'custom order requests',
        ] as $intentKeyword) {
            if (!in_array($intentKeyword, $keywordSet, true)) {
                $keywordSet[] = $intentKeyword;
            }
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
            'og_type' => 'website',
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
            'og_type' => 'website',
            'structured_data' => self::buildShopStructuredData($items, $baseUrl, $title, $description, $image),
        ];
    }

    private static function buildShopCategorySeoPayload(string $categorySlug): array
    {
        $baseUrl = self::baseUrl();
        $items = self::loadShopSeoItems();
        $matching = array_values(array_filter($items, static function (array $item) use ($categorySlug): bool {
            $slug = wf_slugify((string) ($item['category_slug'] ?? ''));
            return $slug === $categorySlug;
        }));

        $categoryLabel = !empty($matching[0]['category_name']) ? (string) $matching[0]['category_name'] : ucwords(str_replace('-', ' ', $categorySlug));
        $titleFallback = sprintf('%s | Custom Gifts & Personalized Items', $categoryLabel);
        $descriptionFallback = sprintf('Shop %s from WhimsicalFrog, including custom tumblers, personalized t-shirts, and handmade resin gifts.', strtolower($categoryLabel));
        $topImage = !empty($matching[0]['image_url']) ? self::absoluteUrl((string) $matching[0]['image_url'], $baseUrl) : ($baseUrl . '/images/backgrounds/background-roomS.webp');
        $canonicalPath = '/shop/category/' . rawurlencode($categorySlug);

        $keywords = [];
        foreach ($matching as $item) {
            foreach ($item['seo_keywords'] as $keyword) {
                $normalized = strtolower(trim((string) $keyword));
                if ($normalized !== '' && !in_array($normalized, $keywords, true)) {
                    $keywords[] = $normalized;
                }
                if (count($keywords) >= 20) {
                    break 2;
                }
            }
        }
        if (empty($keywords)) {
            $keywords = [
                strtolower($categoryLabel),
                'custom tumblers',
                'personalized t-shirts',
                'handmade resin gifts',
                'custom gift requests',
            ];
        }

        return [
            'title' => self::truncate($titleFallback, 60),
            'description' => self::truncate($descriptionFallback, 160),
            'keywords' => implode(', ', array_values(array_unique($keywords))),
            'canonical' => $baseUrl . $canonicalPath,
            'image' => $topImage,
            'og_type' => 'website',
            'structured_data' => self::buildCategoryStructuredData($matching, $baseUrl, $categoryLabel, $canonicalPath, $descriptionFallback, $topImage),
        ];
    }

    private static function buildProductSeoPayload(string $identifier): array
    {
        $baseUrl = self::baseUrl();
        $product = self::resolveProductForIdentifier($identifier);
        if ($product === null) {
            return [
                'title' => 'Product Not Found | WhimsicalFrog',
                'description' => 'The requested product could not be found.',
                'keywords' => 'product, whimsical frog',
                'canonical' => $baseUrl . '/shop',
                'image' => $baseUrl . '/images/backgrounds/background-roomS.webp',
                'og_type' => 'website',
                'structured_data' => [],
            ];
        }

        $canonicalPath = self::canonicalProductPath($product);
        $name = trim((string) ($product['title'] ?? $product['name'] ?? 'Product'));
        $categoryName = trim((string) ($product['category_name'] ?? 'Custom Gifts'));
        $description = trim((string) ($product['description'] ?? ''));
        if ($description === '') {
            $description = sprintf('Shop %s by WhimsicalFrog. Handmade custom gifts with fast turnaround and transparent shipping details.', $name);
        }
        $image = self::absoluteUrl((string) ($product['image_url'] ?? self::DEFAULT_PRODUCT_IMAGE), $baseUrl);
        $titleFallback = self::truncate($name . ' | ' . $categoryName . ' | WhimsicalFrog', 60);

        return [
            'title' => $titleFallback,
            'description' => self::truncate($description, 160),
            'keywords' => strtolower($name . ', ' . $categoryName . ', custom gifts, personalized gifts'),
            'canonical' => $baseUrl . $canonicalPath,
            'image' => $image,
            'og_type' => 'product',
            'structured_data' => self::buildProductPageStructuredData($product, $baseUrl),
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

    private static function buildCategoryStructuredData(
        array $items,
        string $baseUrl,
        string $categoryLabel,
        string $canonicalPath,
        string $description,
        string $image
    ): array {
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
            'name' => $categoryLabel . ' | WhimsicalFrog',
            'description' => self::truncate($description, 160),
            'url' => $baseUrl . $canonicalPath,
            'image' => $image,
            'mainEntity' => [
                '@type' => 'ItemList',
                'name' => $categoryLabel,
                'numberOfItems' => count($list),
                'itemListElement' => $list,
            ],
        ];
    }

    private static function buildProductPageStructuredData(array $item, string $baseUrl): array
    {
        return self::buildProductStructuredData($item, $baseUrl);
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
                    COALESCE(c.name, 'Uncategorized') AS category_name,
                    COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-')), 'uncategorized') AS category_slug
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
                 LIMIT 1000"
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
                'name' => (string) ($row['name'] ?? ''),
                'title' => $title,
                'description' => $description,
                'retail_price' => (float) ($row['retail_price'] ?? 0),
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'image_url' => (string) ($row['image_url'] ?? 'images/items/placeholder.webp'),
                'seo_keywords' => $keywords,
                'category_name' => (string) ($row['category_name'] ?? 'Uncategorized'),
                'category_slug' => wf_slugify((string) ($row['category_slug'] ?? 'uncategorized')) ?? 'uncategorized',
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
                    COALESCE(c.name, 'Uncategorized') AS category_name,
                    COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-')), 'uncategorized') AS category_slug
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
                'name' => (string) ($row['name'] ?? ''),
                'title' => $title,
                'description' => $description,
                'retail_price' => (float) ($row['retail_price'] ?? 0),
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'image_url' => (string) ($row['image_url'] ?? 'images/items/placeholder.webp'),
                'seo_keywords' => $keywords,
                'category_name' => (string) ($row['category_name'] ?? 'Uncategorized'),
                'category_slug' => wf_slugify((string) ($row['category_slug'] ?? 'uncategorized')) ?? 'uncategorized',
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
        $ogType = self::escape((string) ($seo['og_type'] ?? 'website'));
        $structured = '';
        $hreflang = "\n<link rel=\"alternate\" hreflang=\"en\" href=\"{$canonical}\">\n<link rel=\"alternate\" hreflang=\"x-default\" href=\"{$canonical}\">";

        if (!empty($seo['structured_data']) && is_array($seo['structured_data'])) {
            $structured = "\n<script type=\"application/ld+json\">" . json_encode($seo['structured_data'], JSON_UNESCAPED_SLASHES) . "</script>";
        }

        return "\n<title>{$title}</title>\n<meta name=\"description\" content=\"{$description}\">\n<meta name=\"keywords\" content=\"{$keywords}\">\n<link rel=\"canonical\" href=\"{$canonical}\">{$hreflang}\n<meta property=\"og:title\" content=\"{$title}\">\n<meta property=\"og:description\" content=\"{$description}\">\n<meta property=\"og:image\" content=\"{$image}\">\n<meta property=\"og:url\" content=\"{$canonical}\">\n<meta property=\"og:type\" content=\"{$ogType}\">\n<meta name=\"twitter:card\" content=\"summary_large_image\">\n<meta name=\"twitter:title\" content=\"{$title}\">\n<meta name=\"twitter:description\" content=\"{$description}\">\n<meta name=\"twitter:image\" content=\"{$image}\">{$structured}\n";
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
            '@id' => rtrim($baseUrl, '/') . '/#organization',
            'name' => $siteName,
            'url' => rtrim($baseUrl, '/') . '/',
            'logo' => self::absoluteUrl(wf_brand_logo_path(), $baseUrl),
            'description' => 'Whimsical Frog creates handmade decor, personalized gifts, custom tumblers, apparel, and resin artwork.',
        ];

        $businessEmail = wf_business_email();
        if ($businessEmail !== '') {
            $payload['email'] = $businessEmail;
            $payload['contactPoint'] = [[
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $businessEmail,
                'url' => rtrim($baseUrl, '/') . '/contact',
                'availableLanguage' => ['en'],
            ]];
        }

        $ownerName = '';
        $phone = '';
        try {
            if (class_exists('BusinessSettings')) {
                $ownerName = trim((string) BusinessSettings::get('business_owner', ''));
                $phone = trim((string) BusinessSettings::get('business_phone', ''));
            }
        } catch (Throwable $e) {
            $ownerName = '';
            $phone = '';
        }

        if ($ownerName !== '') {
            $payload['founder'] = [
                '@type' => 'Person',
                'name' => $ownerName,
            ];
        }
        if ($phone !== '') {
            $payload['telephone'] = $phone;
        }

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
            'description' => self::truncate(trim((string) ($item['description'] ?? '')), 300),
            'sku' => $sku,
            'mpn' => $sku,
            'category' => (string) ($item['category_name'] ?? 'Uncategorized'),
            'image' => self::absoluteUrl((string) ($item['image_url'] ?? ''), $baseUrl),
            'url' => $baseUrl . self::canonicalProductPath($item),
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
                'url' => $baseUrl . self::canonicalProductPath($item),
            ],
        ];

        $targetAudience = trim((string) ($item['target_audience'] ?? ''));
        if ($targetAudience !== '') {
            $product['audience'] = [
                '@type' => 'Audience',
                'audienceType' => $targetAudience,
            ];
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

    private static function truncateHard(string $value, int $limit): string
    {
        $trimmed = trim($value);
        if (mb_strlen($trimmed) <= $limit) {
            return $trimmed;
        }
        return rtrim(mb_substr($trimmed, 0, $limit));
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

    private static function defaultPageMeta(string $canonicalPath): array
    {
        return match ($canonicalPath) {
            '/' => [
                'title' => 'Whimsical Frog | Custom Gifts & Handmade Decor',
                'description' => 'Whimsical Frog creates custom tumblers, personalized shirts, handmade resin decor, and one-of-a-kind gifts, with easy custom order requests for every occasion.',
                'image_path' => '/images/backgrounds/background-roomA.webp',
            ],
            '/shop' => [
                'title' => 'Shop Custom Tumblers, Shirts & Resin Gifts',
                'description' => 'Browse handmade custom tumblers, personalized t-shirts, resin gifts, and one-of-a-kind gift ideas.',
                'image_path' => '/images/backgrounds/background-roomS.webp',
            ],
            '/about' => [
                'title' => 'About WhimsicalFrog | Handmade Gift Studio',
                'description' => 'Learn how WhimsicalFrog creates custom gifts, personalized apparel, and handcrafted resin keepsakes.',
                'image_path' => '/images/backgrounds/background-roomA.webp',
            ],
            '/contact' => [
                'title' => 'Contact WhimsicalFrog | Custom Order Requests',
                'description' => 'Contact WhimsicalFrog for custom gift requests, turnaround questions, shipping support, and order help.',
                'image_path' => '/images/backgrounds/background-roomA.webp',
            ],
            default => [],
        };
    }

    private static function renderDefaultSeoShellContent(string $siteName): string
    {
        return '<header>'
            . '<h1 style="font-family:\'Merienda\',cursive;font-size:2rem;line-height:1.1;margin:0 0 1rem;">' . self::escape($siteName . ' Handmade Decor And Gifts') . '</h1>'
            . '<p style="margin:0 0 1rem;opacity:0.9;">Shop handmade decor, personalized gifts, and seasonal treasures. Explore themed rooms, browse new arrivals, and request custom items.</p>'
            . '<nav aria-label="Primary"><a href="/shop" style="color:#fff;text-decoration:underline;margin-right:1rem;">Shop</a><a href="/about" style="color:#fff;text-decoration:underline;margin-right:1rem;">About</a><a href="/contact" style="color:#fff;text-decoration:underline;">Contact</a></nav>'
            . '</header>'
            . '<section style="margin-top:2rem;"><h2 style="font-size:1.25rem;margin:0 0 0.75rem;">Custom Gifts, Handmade Decor, And Easy Ordering</h2>'
            . '<p style="margin:0 0 0.75rem;opacity:0.9;">Whimsical Frog offers personalized tumblers, custom shirts, resin decor, and one-of-a-kind handmade gifts. Need a custom piece? Use our contact page to share your idea, timeline, and event details.</p>'
            . '</section>'
            . '<footer style="margin-top:2rem;"><h2 style="font-size:1.25rem;margin:0 0 0.75rem;">Policies, Shipping, And Support</h2>'
            . '<nav aria-label="Support"><a href="/policy" style="color:#fff;text-decoration:underline;margin-right:1rem;">Policy</a><a href="/privacy" style="color:#fff;text-decoration:underline;margin-right:1rem;">Privacy</a><a href="/contact" style="color:#fff;text-decoration:underline;">Contact</a></nav></footer>';
    }

    private static function renderShopSeoShellContent(string $siteName): string
    {
        return '<header>'
            . '<h1 style="font-family:\'Merienda\',cursive;font-size:2rem;line-height:1.1;margin:0 0 1rem;">Shop Custom Gifts At ' . self::escape($siteName) . '</h1>'
            . '<p style="margin:0 0 1rem;opacity:0.9;">Browse handmade tumblers, personalized apparel, resin art, and seasonal gift collections with custom-order support.</p>'
            . '</header>'
            . '<section style="margin-top:2rem;"><h2 style="font-size:1.25rem;margin:0 0 0.75rem;">What You Can Customize</h2>'
            . '<h3 style="font-size:1.05rem;margin:1rem 0 0.5rem;">Personalized Names, Colors, And Themes</h3>'
            . '<p style="margin:0 0 0.75rem;opacity:0.9;">Many products support names, event themes, and custom design direction. If you have a deadline, include it in your request so we can confirm feasibility up front.</p>'
            . '</section>';
    }

    private static function renderAboutSeoShellContent(string $siteName): string
    {
        $ownerName = '';
        try {
            if (class_exists('BusinessSettings')) {
                $ownerName = trim((string) BusinessSettings::get('business_owner', ''));
            }
        } catch (Throwable $e) {
            $ownerName = '';
        }
        $ownerLine = $ownerName !== ''
            ? '<p style="margin:0 0 0.75rem;opacity:0.9;"><strong>Owner:</strong> ' . self::escape($ownerName) . '</p>'
            : '';

        return '<header>'
            . '<h1 style="font-family:\'Merienda\',cursive;font-size:2rem;line-height:1.1;margin:0 0 1rem;">About ' . self::escape($siteName) . '</h1>'
            . '<p style="margin:0 0 1rem;opacity:0.9;">Learn who runs Whimsical Frog, how products are crafted, and what to expect from custom order requests.</p>'
            . '</header>'
            . '<section style="margin-top:2rem;"><h2 style="font-size:1.25rem;margin:0 0 0.75rem;">Our Craft And Customer Promise</h2>'
            . '<p style="margin:0 0 0.75rem;opacity:0.9;">We focus on small-batch quality, clear communication, and personalized products made for real events and gifts.</p>'
            . $ownerLine
            . '</section>';
    }

    private static function renderContactSeoShellContent(string $siteName, string $baseUrl): string
    {
        $businessEmail = wf_business_email();
        $owner = '';
        $phone = '';
        $hours = '';
        $address = '';
        try {
            if (class_exists('BusinessSettings')) {
                $owner = trim((string) BusinessSettings::get('business_owner', ''));
                $phone = trim((string) BusinessSettings::get('business_phone', ''));
                $hours = trim((string) BusinessSettings::get('business_hours', ''));
                $address = trim((string) BusinessSettings::getBusinessAddressBlock());
            }
        } catch (Throwable $e) {
            $owner = '';
            $phone = '';
            $hours = '';
            $address = '';
        }

        $contactBits = [];
        if ($businessEmail !== '') {
            $safeEmail = self::escape($businessEmail);
            $contactBits[] = '<p style="margin:0 0 0.5rem;opacity:0.95;"><strong>Email:</strong> <a href="mailto:' . $safeEmail . '" style="color:#fff;text-decoration:underline;">' . $safeEmail . '</a></p>';
        }
        if ($phone !== '') {
            $safePhone = self::escape($phone);
            $safePhoneHref = self::escape((string) (preg_replace('/[^0-9+]/', '', $phone) ?? ''));
            $contactBits[] = '<p style="margin:0 0 0.5rem;opacity:0.95;"><strong>Phone:</strong> <a href="tel:' . $safePhoneHref . '" style="color:#fff;text-decoration:underline;">' . $safePhone . '</a></p>';
        }
        if ($owner !== '') {
            $contactBits[] = '<p style="margin:0 0 0.5rem;opacity:0.95;"><strong>Owner:</strong> ' . self::escape($owner) . '</p>';
        }
        if ($hours !== '') {
            $contactBits[] = '<p style="margin:0 0 0.5rem;opacity:0.95;"><strong>Business Hours:</strong> ' . self::escape($hours) . '</p>';
        }
        if ($address !== '') {
            $contactBits[] = '<p style="margin:0 0 0.5rem;opacity:0.95;"><strong>Address:</strong> ' . nl2br(self::escape($address), false) . '</p>';
        }
        if (empty($contactBits)) {
            $contactBits[] = '<p style="margin:0 0 0.5rem;opacity:0.95;">Use our contact form for custom orders, shipping support, and policy questions.</p>';
        }

        return '<header>'
            . '<h1 style="font-family:\'Merienda\',cursive;font-size:2rem;line-height:1.1;margin:0 0 1rem;">Contact ' . self::escape($siteName) . '</h1>'
            . '<p style="margin:0 0 1rem;opacity:0.9;">Reach out for custom order requests, turnaround details, and post-purchase support.</p>'
            . '</header>'
            . '<section style="margin-top:2rem;"><h2 style="font-size:1.25rem;margin:0 0 0.75rem;">Business Contact Details</h2>'
            . implode('', $contactBits)
            . '<p style="margin:0.75rem 0 0;opacity:0.9;"><a href="' . self::escape(rtrim($baseUrl, '/') . '/policy') . '" style="color:#fff;text-decoration:underline;">Store policy</a> and <a href="' . self::escape(rtrim($baseUrl, '/') . '/privacy') . '" style="color:#fff;text-decoration:underline;">privacy details</a> are available before checkout.</p>'
            . '</section>';
    }

    private static function renderRoomSeoShellContent(string $roomNumber): string
    {
        $room = self::loadRoomInfo($roomNumber);
        $items = self::loadRoomSeoItems($roomNumber);
        $roomName = trim((string) ($room['room_name'] ?? 'Room ' . $roomNumber));
        $roomDescription = trim((string) ($room['description'] ?? ''));
        if ($roomDescription === '') {
            $roomDescription = 'Explore this themed collection of handmade gifts, personalized products, and small-batch decor.';
        }

        $count = count($items);
        $sample = array_slice($items, 0, 3);
        $sampleLines = [];
        foreach ($sample as $item) {
            $sampleLines[] = '<li>' . self::escape((string) ($item['title'] ?? $item['name'] ?? 'Featured item')) . '</li>';
        }
        $sampleList = !empty($sampleLines)
            ? '<h3 style="font-size:1.05rem;margin:1rem 0 0.5rem;">Featured In This Collection</h3><ul style="margin:0 0 0.75rem 1.1rem;opacity:0.9;">' . implode('', $sampleLines) . '</ul>'
            : '';

        return '<header>'
            . '<h1 style="font-family:\'Merienda\',cursive;font-size:2rem;line-height:1.1;margin:0 0 1rem;">' . self::escape($roomName . ' | Whimsical Frog') . '</h1>'
            . '<p style="margin:0 0 1rem;opacity:0.9;">' . self::escape(self::truncate($roomDescription, 220)) . '</p>'
            . '</header>'
            . '<section style="margin-top:2rem;"><h2 style="font-size:1.25rem;margin:0 0 0.75rem;">' . self::escape($roomName . ' Handmade Gift Collection') . '</h2>'
            . '<p style="margin:0 0 0.75rem;opacity:0.9;">This room currently includes ' . self::escape((string) $count) . ' live product' . ($count === 1 ? '' : 's') . '. Browse matching items and request customization when available.</p>'
            . $sampleList
            . '<p style="margin:0 0 0.75rem;opacity:0.9;">Need a made-to-order version? Use <a href="/contact" style="color:#fff;text-decoration:underline;">contact</a> to share theme, timeline, and design preferences.</p>'
            . '</section>';
    }

    private static function renderCategorySeoShellContent(string $categorySlug): string
    {
        $items = self::loadShopSeoItems();
        $matching = array_values(array_filter($items, static function (array $item) use ($categorySlug): bool {
            return wf_slugify((string) ($item['category_slug'] ?? '')) === $categorySlug;
        }));
        $categoryLabel = !empty($matching[0]['category_name']) ? (string) $matching[0]['category_name'] : ucwords(str_replace('-', ' ', $categorySlug));
        $count = count($matching);

        return '<header>'
            . '<h1 style="font-family:\'Merienda\',cursive;font-size:2rem;line-height:1.1;margin:0 0 1rem;">' . self::escape($categoryLabel . ' | Whimsical Frog Shop') . '</h1>'
            . '<p style="margin:0 0 1rem;opacity:0.9;">Shop handmade ' . self::escape(strtolower($categoryLabel)) . ' with custom-order options, seasonal drops, and ready-to-ship picks.</p>'
            . '</header>'
            . '<section style="margin-top:2rem;"><h2 style="font-size:1.25rem;margin:0 0 0.75rem;">Browse ' . self::escape($categoryLabel) . '</h2>'
            . '<p style="margin:0 0 0.75rem;opacity:0.9;">This category currently includes ' . self::escape((string) $count) . ' live product' . ($count === 1 ? '' : 's') . '. For sizing, materials, and timeline questions, submit a request through <a href="/contact" style="color:#fff;text-decoration:underline;">contact</a>.</p>'
            . '</section>';
    }

    private static function renderProductSeoShellContent(array $product): string
    {
        $name = trim((string) ($product['title'] ?? $product['name'] ?? 'Product'));
        $category = trim((string) ($product['category_name'] ?? 'Custom Gifts'));
        $description = trim((string) ($product['description'] ?? ''));
        if ($description === '') {
            $description = 'Handmade product with custom-order support.';
        }

        return '<header>'
            . '<h1 style="font-family:\'Merienda\',cursive;font-size:2rem;line-height:1.1;margin:0 0 1rem;">' . self::escape($name) . '</h1>'
            . '<p style="margin:0 0 1rem;opacity:0.9;">Category: ' . self::escape($category) . '</p>'
            . '</header>'
            . '<section style="margin-top:2rem;"><h2 style="font-size:1.25rem;margin:0 0 0.75rem;">Product Details</h2>'
            . '<p style="margin:0 0 0.75rem;opacity:0.9;">' . self::escape(self::truncate($description, 260)) . '</p>'
            . '<p style="margin:0 0 0.75rem;opacity:0.9;">Need personalization or a matching set? Request a custom order through <a href="/contact" style="color:#fff;text-decoration:underline;">contact</a>.</p>'
            . '</section>';
    }

    private static function extractProductIdentifier(string $path): ?string
    {
        if (!preg_match('#^/product/([^/]+)$#', $path, $m)) {
            return null;
        }

        $identifier = urldecode(trim((string) ($m[1] ?? '')));
        return $identifier !== '' ? $identifier : null;
    }

    private static function extractShopCategorySlug(string $path): ?string
    {
        if (!preg_match('#^/shop/category/([^/]+)$#', $path, $m)) {
            return null;
        }
        $slug = wf_slugify(urldecode((string) ($m[1] ?? '')));
        return $slug ?: null;
    }

    private static function resolveProductForIdentifier(string $identifier): ?array
    {
        $token = trim(urldecode($identifier));
        if ($token === '') {
            return null;
        }

        $items = self::loadShopSeoItems();
        if (empty($items)) {
            return null;
        }

        $tokenSlug = wf_slugify($token);
        foreach ($items as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $skuSlug = wf_slugify($sku) ?: strtolower($sku);
            if (strcasecmp($token, $sku) === 0 || ($tokenSlug !== null && $tokenSlug === $skuSlug)) {
                return $item;
            }
        }

        if ($tokenSlug === null) {
            return null;
        }

        foreach ($items as $item) {
            $canonicalSlug = self::canonicalProductSlug($item);
            if ($tokenSlug === wf_slugify($canonicalSlug)) {
                return $item;
            }
        }

        return null;
    }

    private static function canonicalProductPath(array $item): string
    {
        return '/product/' . rawurlencode(self::canonicalProductSlug($item));
    }

    private static function canonicalProductSlug(array $item): string
    {
        $nameSlug = wf_slugify((string) ($item['name'] ?? $item['title'] ?? 'product')) ?: 'product';
        $skuSlug = wf_slugify((string) ($item['sku'] ?? '')) ?: 'sku';
        return $nameSlug . '--' . $skuSlug;
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
