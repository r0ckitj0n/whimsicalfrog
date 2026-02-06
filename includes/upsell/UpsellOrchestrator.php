<?php

declare(strict_types=1);

/**
 * Orchestrates cart upsell resolution logic
 */
class UpsellOrchestrator
{
    public static function resolve(array $skus, int $limit = 4): array
    {
        $limit = max(1, (int) $limit);
        $data = RuleGenerator::generate();
        $map = $data['map'] ?? [];
        $items_list = $data['items'] ?? [];
        $metadata = $data['metadata'] ?? [];

        $pdo = Database::getInstance();
        $cartSet = self::getNormalizedCartSet($skus);
        $ordered = self::getOrderedCandidates($cartSet, $map, $metadata);

        $results = [];
        foreach ($ordered as $sku) {
            if (!isset($items_list[$sku]))
                continue;

            $stock = self::getEffectiveStock($pdo, $sku);
            if ($stock <= 0)
                continue;

            $results[] = self::buildUpsellItem($sku, $items_list[$sku], $stock);
            if (count($results) >= $limit)
                break;
        }

        // Fill remaining slots if needed
        if (count($results) < $limit && !empty($items_list)) {
            $results = self::fillRemainingSlots($results, $cartSet, $items_list, $limit, $pdo);
        }

        return [
            'upsells' => $results,
            'metadata' => $metadata,
            'requested_skus' => array_keys($cartSet),
        ];
    }

    private static function getNormalizedCartSet(array $skus): array
    {
        $set = [];
        foreach ($skus as $sku) {
            $norm = strtoupper(trim((string) $sku));
            if ($norm !== '')
                $set[$norm] = true;
        }
        return $set;
    }

    private static function getOrderedCandidates(array $cartSet, array $map, array $metadata): array
    {
        $ordered = [];
        $seen = [];
        $push = function (string $candidate) use (&$ordered, &$seen, $cartSet): void {
            $candidate = strtoupper(trim($candidate));
            if ($candidate === '' || isset($cartSet[$candidate]) || isset($seen[$candidate]))
                return;
            $seen[$candidate] = true;
            $ordered[] = $candidate;
        };

        foreach (array_keys($cartSet) as $sku) {
            $list = $map[$sku] ?? [];
            foreach ($list as $cand)
                $push((string) $cand);
        }

        $default = $map['_default'] ?? [];
        foreach ($default as $cand)
            $push((string) $cand);

        if (empty($ordered)) {
            if (isset($metadata['site_top']))
                $push((string) $metadata['site_top']);
            if (isset($metadata['site_second']))
                $push((string) $metadata['site_second']);
        }

        return $ordered;
    }

    private static function getEffectiveStock($pdo, string $sku): int
    {
        if (!function_exists('getStockLevel'))
            return 999;

        $available = getStockLevel($pdo, $sku);
        if ($available === false)
            return 0;
        $available = (int) $available;

        if ($available <= 0) {
            try {
                $row = Database::queryOne("SELECT stock_quantity FROM items WHERE sku = ?", [$sku]);
                $dbLevel = $row['stock_quantity'] ?? null;
                if ($dbLevel === null)
                    return 999;

                if ((int) $dbLevel === 0) {
                    $hasSizes = Database::queryOne("SELECT 1 FROM item_sizes WHERE item_sku = ? LIMIT 1", [$sku]);
                    $hasColors = Database::queryOne("SELECT 1 FROM item_colors WHERE item_sku = ? LIMIT 1", [$sku]);
                    if (!$hasSizes && !$hasColors)
                        return 999;
                }
                // @reason: Stock level check is supplementary - defaults to available
            } catch (Throwable $e) {
            }
        }

        return $available;
    }

    private static function buildUpsellItem(string $sku, array $meta, int $stock): array
    {
        $img = $meta['image'] ?? '';
        if ($img === '' || stripos($img, 'placeholder') !== false) {
            $img = ItemImageResolver::resolve($sku);
        }

        $hasOptions = false;
        try {
            $row = Database::queryOne("SELECT 1 FROM item_sizes WHERE item_sku = ? AND is_active = 1 LIMIT 1", [$sku]);
            $hasOptions = (bool) $row;
            // @reason: Options check is non-critical - defaults to no options
        } catch (Throwable $e) {
        }

        return [
            'sku' => $sku,
            'name' => $meta['name'] ?? $sku,
            'price' => (float) ($meta['price'] ?? 0.0),
            'image' => $img,
            'category' => (string) ($meta['category'] ?? ''),
            'units' => (float) ($meta['units'] ?? 0.0),
            'revenue' => (float) ($meta['revenue'] ?? 0.0),
            'stock_level' => $stock,
            'has_options' => $hasOptions,
        ];
    }

    private static function fillRemainingSlots(array $results, array $cartSet, array $items_list, int $limit, $pdo): array
    {
        $picked = [];
        foreach ($results as $r)
            $picked[$r['sku']] = true;

        $sortedSkus = array_keys($items_list);
        usort($sortedSkus, function ($a, $b) use ($items_list) {
            $ua = (float) ($items_list[$a]['units'] ?? 0);
            $ub = (float) ($items_list[$b]['units'] ?? 0);
            if ($ua !== $ub)
                return $ub <=> $ua;
            return ($items_list[$b]['revenue'] ?? 0) <=> ($items_list[$a]['revenue'] ?? 0);
        });

        foreach ($sortedSkus as $sku) {
            if (isset($cartSet[$sku]) || isset($picked[$sku]))
                continue;

            $stock = self::getEffectiveStock($pdo, $sku);
            if ($stock <= 0)
                continue;

            $results[] = self::buildUpsellItem($sku, $items_list[$sku], $stock);
            if (count($results) >= $limit)
                break;
        }

        return $results;
    }
}
