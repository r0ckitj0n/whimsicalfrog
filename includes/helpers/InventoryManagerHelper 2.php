<?php
/**
 * includes/helpers/InventoryManagerHelper.php
 * Helper class for inventory management logic
 */

class InventoryManagerHelper {
    public static function getVariantKey(array $variant): string {
        $kind = (string)($variant['kind'] ?? '');
        $dims = (isset($variant['dims']) && is_array($variant['dims'])) ? $variant['dims'] : [];
        $parts = [$kind];
        foreach (['gender', 'size', 'color'] as $d) {
            if (array_key_exists($d, $dims)) {
                $parts[] = $d . '=' . strtolower(trim((string)$dims[$d]));
            }
        }
        return implode('|', $parts);
    }

    public static function buildImageUrl($imagePath): string {
        if (empty($imagePath)) return '/images/items/placeholder.webp';
        $path = (string)$imagePath;
        if (strpos($path, 'images/items/') === 0) return '/' . $path;
        if (strpos($path, '/images/items/') === 0) return $path;
        return '/images/items/' . ltrim($path, '/');
    }

    public static function dedupeVariants(array $variants): array {
        if (empty($variants)) return [];
        $groups = [];
        foreach ($variants as $v) {
            $key = self::getVariantKey($v);
            if (!isset($groups[$key])) {
                $groups[$key] = ['rep' => $v, 'count' => 0, 'ids' => [], 'stock_sum' => 0];
            }
            $groups[$key]['count'] += 1;
            $groups[$key]['stock_sum'] += (int)($v['stock_level'] ?? 0);
            $id = (int)($v['size_id'] ?? $v['color_id'] ?? 0);
            if ($id > 0) $groups[$key]['ids'][] = $id;
        }

        $deduped = [];
        foreach ($groups as $g) {
            $rep = $g['rep'];
            $rep['stock_level'] = (int)$g['stock_sum'];
            if ((int)$g['count'] > 1) {
                $rep['is_duplicate'] = true;
                $rep['duplicate_count'] = (int)$g['count'];
                $rep['duplicate_ids'] = array_values(array_unique($g['ids']));
            }
            $deduped[] = $rep;
        }
        return $deduped;
    }
}
