<?php

declare(strict_types=1);

/**
 * Generates cart upsell rules based on sales data and rankings
 */
class RuleGenerator
{
    private static $cache = null;

    public static function generate(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            Database::getInstance();
        } catch (Throwable $e) {
            return ['map' => ['_default' => []], 'items' => []];
        }

        $rows = self::fetchRankedRows();
        if (empty($rows)) {
            return ['map' => ['_default' => []], 'items' => []];
        }

        $items_list = [];
        $categoryLeaders = [];
        $categorySecondaries = [];
        $siteTopSku = null;
        $siteSecondarySku = null;

        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku === '')
                continue;

            $items_list[$sku] = [
                'name' => trim((string) ($row['name'] ?? $sku)),
                'price' => round((float) ($row['retail_price'] ?? 0), 2),
                'image' => self::formatImagePath((string) ($row['image_path'] ?? '')),
                'category' => trim((string) ($row['category'] ?? '')),
                'units' => (float) ($row['total_units'] ?? 0),
                'revenue' => round((float) ($row['total_revenue'] ?? 0), 2),
            ];

            $siteRank = (int) ($row['site_rank'] ?? 0);
            if ($siteRank === 1)
                $siteTopSku = $sku;
            elseif ($siteRank === 2)
                $siteSecondarySku = $sku;

            $category = $items_list[$sku]['category'];
            $catRank = (int) ($row['category_rank'] ?? 0);
            if ($category !== '') {
                if ($catRank === 1)
                    $categoryLeaders[$category] = $sku;
                elseif ($catRank === 2)
                    $categorySecondaries[$category] = $sku;
            }
        }

        $map = self::buildRuleMap($items_list, $categoryLeaders, $categorySecondaries, $siteTopSku, $siteSecondarySku);

        self::$cache = [
            'map' => $map,
            'items' => $items_list,
            'metadata' => [
                'site_top' => $siteTopSku,
                'site_second' => $siteSecondarySku,
                'category_leaders' => $categoryLeaders,
                'category_secondaries' => $categorySecondaries,
            ]
        ];

        return self::$cache;
    }

    private static function fetchRankedRows(): array
    {
        $baseSql = "WITH item_sales AS (
                    SELECT i.sku, i.name, i.category, i.retail_price,
                        COALESCE(SUM(oi.quantity), 0) AS total_units,
                        COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS total_revenue
                    FROM items i
                    LEFT JOIN order_items oi ON oi.sku = i.sku
                    GROUP BY i.sku, i.name, i.category, i.retail_price
                ),
                ranked AS (
                    SELECT s.*, 
                        ROW_NUMBER() OVER (ORDER BY s.total_units DESC, s.total_revenue DESC, s.name ASC) AS site_rank,
                        ROW_NUMBER() OVER (PARTITION BY s.category ORDER BY s.total_units DESC, s.total_revenue DESC, s.name ASC) AS category_rank
                    FROM item_sales s
                )
                SELECT r.*, COALESCE(img.image_path, i.image_url) AS image_path
                FROM ranked r
                LEFT JOIN items i ON i.sku = r.sku
                LEFT JOIN item_images img ON img.sku = r.sku AND img.is_primary = 1";

        try {
            $rows = Database::queryAll($baseSql . " WHERE r.total_units > 0 OR r.total_revenue > 0 ORDER BY r.total_units DESC, r.total_revenue DESC");
            if ($rows)
                return $rows;
            return Database::queryAll($baseSql . " ORDER BY r.total_units DESC, r.total_revenue DESC");
        } catch (Throwable $e) {
            return self::fallbackFetch(true) ?: self::fallbackFetch(false);
        }
    }

    private static function fallbackFetch(bool $preferSalesOnly): array
    {
        $where = $preferSalesOnly ? "HAVING total_units > 0 OR total_revenue > 0" : "";
        $sql = "SELECT i.sku, i.name, i.category, i.retail_price,
                    COALESCE(SUM(oi.quantity), 0) AS total_units,
                    COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS total_revenue,
                    COALESCE(img.image_path, i.image_url) AS image_path
                FROM items i
                LEFT JOIN order_items oi ON oi.sku = i.sku
                LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
                GROUP BY i.sku, i.name, i.category, i.retail_price, img.image_path, i.image_url
                $where
                ORDER BY total_units DESC, total_revenue DESC, i.name ASC";

        try {
            $basic = Database::queryAll($sql);
            if (!$basic)
                return [];

            usort($basic, fn($a, $b) => ($b['total_units'] <=> $a['total_units']) ?: ($b['total_revenue'] <=> $a['total_revenue']) ?: strcasecmp($a['name'], $b['name']));

            $siteRank = 0;
            $catRanks = [];
            foreach ($basic as $i => $row) {
                $siteRank++;
                $cat = (string) ($row['category'] ?? '');
                $catRanks[$cat] = ($catRanks[$cat] ?? 0) + 1;
                $basic[$i]['site_rank'] = $siteRank;
                $basic[$i]['category_rank'] = $catRanks[$cat];
            }
            return $basic;
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function buildRuleMap($items_list, $leaders, $secondaries, $top, $second): array
    {
        $map = [];
        foreach ($items_list as $sku => $meta) {
            $cat = $meta['category'];
            $recs = [];
            if ($cat !== '') {
                $l = $leaders[$cat] ?? null;
                $s = $secondaries[$cat] ?? null;
                if ($l && $l !== $sku)
                    $recs[] = $l;
                if ($s && $s !== $sku)
                    $recs[] = $s;
            }
            if ($top && $top !== $sku)
                $recs[] = $top;
            if ($second && $second !== $sku)
                $recs[] = $second;
            $map[$sku] = array_values(array_unique($recs));
        }

        $default = [];
        if ($top)
            $default[] = $top;
        if ($second)
            $default[] = $second;
        foreach ($items_list as $sku => $meta) {
            if ($meta['units'] > 0 && !in_array($sku, $default, true))
                $default[] = $sku;
            if (count($default) >= 6)
                break;
        }
        $map['_default'] = $default;

        return $map;
    }

    private static function formatImagePath(string $path): string
    {
        if ($path === '')
            return '/images/items/placeholder.webp';
        return ($path[0] === '/') ? $path : '/' . ltrim($path, '/');
    }
}
