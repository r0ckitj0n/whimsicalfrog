<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/stock_manager.php';

function wf_generate_cart_upsell_rules(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        Database::getInstance();
    } catch (Throwable $e) {
        $cache = ['map' => ['_default' => []], 'products' => []];
        return $cache;
    }

    $baseSql = "WITH item_sales AS (
                SELECT
                    i.sku,
                    i.name,
                    i.category,
                    i.retailPrice,
                    COALESCE(SUM(oi.quantity), 0) AS total_units,
                    COALESCE(SUM(oi.quantity * oi.price), 0) AS total_revenue
                FROM items i
                LEFT JOIN order_items oi ON oi.sku = i.sku
                GROUP BY i.sku, i.name, i.category, i.retailPrice
            ),
            ranked AS (
                SELECT
                    s.*, 
                    ROW_NUMBER() OVER (ORDER BY s.total_units DESC, s.total_revenue DESC, s.name ASC) AS site_rank,
                    ROW_NUMBER() OVER (PARTITION BY s.category ORDER BY s.total_units DESC, s.total_revenue DESC, s.name ASC) AS category_rank
                FROM item_sales s
            )
            SELECT
                r.sku,
                r.name,
                r.category,
                r.retailPrice,
                r.total_units,
                r.total_revenue,
                r.site_rank,
                r.category_rank,
                COALESCE(img.image_path, i.imageUrl) AS image_path
            FROM ranked r
            LEFT JOIN items i ON i.sku = r.sku
            LEFT JOIN item_images img ON img.sku = r.sku AND img.is_primary = 1";

    $rows = [];

    try {
        $rows = Database::queryAll($baseSql . "
            WHERE r.total_units > 0 OR r.total_revenue > 0
            ORDER BY r.total_units DESC, r.total_revenue DESC");
        if (!$rows) {
            $rows = Database::queryAll($baseSql . "
            ORDER BY r.total_units DESC, r.total_revenue DESC");
        }
    } catch (Throwable $e) {
        $cache = ['map' => ['_default' => []], 'products' => []];
        return $cache;
    }

    if (!is_array($rows) || !$rows) {
        $cache = ['map' => ['_default' => []], 'products' => []];
        return $cache;
    }

    $products = [];
    $map = [];
    $categoryLeaders = [];
    $categorySecondaries = [];
    $siteTopSku = null;
    $siteSecondarySku = null;

    foreach ($rows as $row) {
        $sku = strtoupper(trim((string)($row['sku'] ?? '')));
        if ($sku === '') {
            continue;
        }

        $name = trim((string)($row['name'] ?? $sku));
        $category = trim((string)($row['category'] ?? ''));
        $price = (float)($row['retailPrice'] ?? 0);
        $units = (float)($row['total_units'] ?? 0);
        $revenue = (float)($row['total_revenue'] ?? 0);
        $siteRank = (int)($row['site_rank'] ?? 0);
        $categoryRank = (int)($row['category_rank'] ?? 0);
        $rawImage = (string)($row['image_path'] ?? '');
        $image = $rawImage === '' ? '/images/items/placeholder.webp' : ($rawImage[0] === '/' ? $rawImage : '/' . ltrim($rawImage, '/'));

        $products[$sku] = [
            'name' => $name !== '' ? $name : $sku,
            'price' => round($price, 2),
            'image' => $image,
            'category' => $category,
            'units' => $units,
            'revenue' => round($revenue, 2),
        ];

        if ($siteRank === 1) {
            $siteTopSku = $sku;
        } elseif ($siteRank === 2) {
            $siteSecondarySku = $sku;
        }

        if ($category !== '') {
            if ($categoryRank === 1) {
                $categoryLeaders[$category] = $sku;
            } elseif ($categoryRank === 2) {
                $categorySecondaries[$category] = $sku;
            }
        }
    }

    if (!$products) {
        $cache = ['map' => ['_default' => []], 'products' => []];
        return $cache;
    }

    foreach ($products as $sku => $meta) {
        $category = $meta['category'] ?? '';
        $recommendations = [];

        $similar = null;
        if ($category !== '') {
            $leaderSku = $categoryLeaders[$category] ?? null;
            if ($leaderSku && $leaderSku !== $sku) {
                $recommendations[] = $leaderSku;
            }

            if ((!$leaderSku || $leaderSku === $sku) && isset($categorySecondaries[$category]) && $categorySecondaries[$category] !== $sku) {
                $recommendations[] = $categorySecondaries[$category];
            }

            if ($leaderSku === $sku && isset($categorySecondaries[$category]) && $categorySecondaries[$category] !== $sku) {
                $similar = $categorySecondaries[$category];
            } elseif ($leaderSku && $leaderSku !== $sku) {
                $similar = $leaderSku;
            }
        }

        if ($similar && !in_array($similar, $recommendations, true)) {
            $recommendations[] = $similar;
        }

        if ($siteTopSku && $siteTopSku !== $sku) {
            $recommendations[] = $siteTopSku;
        }

        if ($siteSecondarySku && $siteSecondarySku !== $sku) {
            $recommendations[] = $siteSecondarySku;
        }

        $map[$sku] = array_values(array_unique($recommendations));
    }

    $defaultList = [];
    if ($siteTopSku) {
        $defaultList[] = $siteTopSku;
    }
    if ($siteSecondarySku && $siteSecondarySku !== $siteTopSku) {
        $defaultList[] = $siteSecondarySku;
    }

    foreach ($products as $sku => $meta) {
        if ($meta['units'] > 0 && !in_array($sku, $defaultList, true)) {
            $defaultList[] = $sku;
        }
        if (count($defaultList) >= 6) {
            break;
        }
    }

    $map['_default'] = $defaultList;

    $metadata = [
        'site_top' => $siteTopSku,
        'site_second' => $siteSecondarySku,
        'category_leaders' => $categoryLeaders,
        'category_secondaries' => $categorySecondaries,
    ];

    $cache = ['map' => $map, 'products' => $products, 'metadata' => $metadata];
    return $cache;
}

function wf_resolve_cart_upsells(array $skus, int $limit = 4): array
{
    $limit = max(1, (int)$limit);
    $data = wf_generate_cart_upsell_rules();
    $map = isset($data['map']) && is_array($data['map']) ? $data['map'] : [];
    $products = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];
    $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
    // Obtain a DB handle for stock checks (stock manager uses Database internally)
    $pdo = Database::getInstance();

    $cartSet = [];
    foreach ($skus as $sku) {
        $normalized = strtoupper(trim((string)$sku));
        if ($normalized !== '') {
            $cartSet[$normalized] = true;
        }
    }

    $ordered = [];
    $seen = [];
    $push = static function (string $candidate) use (&$ordered, &$seen, $cartSet): void {
        if ($candidate === '') {
            return;
        }
        if (isset($cartSet[$candidate]) || isset($seen[$candidate])) {
            return;
        }
        $seen[$candidate] = true;
        $ordered[] = $candidate;
    };

    if (!empty($cartSet)) {
        foreach (array_keys($cartSet) as $sku) {
            $list = isset($map[$sku]) && is_array($map[$sku]) ? $map[$sku] : [];
            foreach ($list as $candidateSku) {
                $push(strtoupper(trim((string)$candidateSku)));
            }
        }
    }

    $defaultList = isset($map['_default']) && is_array($map['_default']) ? $map['_default'] : [];
    foreach ($defaultList as $candidateSku) {
        $push(strtoupper(trim((string)$candidateSku)));
    }

    if (empty($ordered)) {
        if (isset($metadata['site_top'])) {
            $push(strtoupper(trim((string)$metadata['site_top'])));
        }
        if (isset($metadata['site_second'])) {
            $push(strtoupper(trim((string)$metadata['site_second'])));
        }
    }

    $results = [];
    foreach ($ordered as $candidateSku) {
        if (!isset($products[$candidateSku])) {
            continue;
        }
        // Filter out items that are not in stock
        $available = (int) getStockLevel($pdo, $candidateSku);
        if ($available <= 0) {
            continue;
        }
        $meta = $products[$candidateSku];
        $results[] = [
            'sku' => $candidateSku,
            'name' => isset($meta['name']) && $meta['name'] !== '' ? $meta['name'] : $candidateSku,
            'price' => isset($meta['price']) ? (float)$meta['price'] : 0.0,
            'image' => isset($meta['image']) && $meta['image'] !== '' ? $meta['image'] : '/images/items/placeholder.webp',
            'category' => isset($meta['category']) ? (string)$meta['category'] : '',
            'units' => isset($meta['units']) ? (float)$meta['units'] : 0.0,
            'revenue' => isset($meta['revenue']) ? (float)$meta['revenue'] : 0.0,
        ];
        if (count($results) >= $limit) {
            break;
        }
    }

    if (count($results) < $limit && !empty($products)) {
        $picked = [];
        foreach ($results as $r) { $picked[$r['sku']] = true; }
        $sortedSkus = array_keys($products);
        usort($sortedSkus, function($a, $b) use ($products) {
            $ua = (float)($products[$a]['units'] ?? 0);
            $ub = (float)($products[$b]['units'] ?? 0);
            if ($ua !== $ub) return $ub <=> $ua;
            $ra = (float)($products[$a]['revenue'] ?? 0);
            $rb = (float)($products[$b]['revenue'] ?? 0);
            if ($ra !== $rb) return $rb <=> $ra;
            $na = (string)($products[$a]['name'] ?? $a);
            $nb = (string)($products[$b]['name'] ?? $b);
            return strcasecmp($na, $nb);
        });
        foreach ($sortedSkus as $sku) {
            if (isset($cartSet[$sku]) || isset($picked[$sku])) {
                continue;
            }
            $available = (int) getStockLevel($pdo, $sku);
            if ($available <= 0) {
                continue;
            }
            $meta = $products[$sku];
            $results[] = [
                'sku' => $sku,
                'name' => isset($meta['name']) && $meta['name'] !== '' ? $meta['name'] : $sku,
                'price' => isset($meta['price']) ? (float)$meta['price'] : 0.0,
                'image' => isset($meta['image']) && $meta['image'] !== '' ? $meta['image'] : '/images/items/placeholder.webp',
                'category' => isset($meta['category']) ? (string)$meta['category'] : '',
                'units' => isset($meta['units']) ? (float)$meta['units'] : 0.0,
                'revenue' => isset($meta['revenue']) ? (float)$meta['revenue'] : 0.0,
            ];
            if (count($results) >= $limit) {
                break;
            }
        }
    }

    return [
        'upsells' => $results,
        'metadata' => $metadata,
        'requested_skus' => array_keys($cartSet),
    ];
}
