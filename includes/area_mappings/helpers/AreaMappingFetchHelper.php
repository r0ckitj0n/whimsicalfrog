<?php
// includes/area_mappings/helpers/AreaMappingFetchHelper.php
require_once __DIR__ . '/AreaMappingSchemaHelper.php';

class AreaMappingFetchHelper
{
    /**
     * Fetch area mappings for a room, with enrichment
     */
    public static function fetchMappings($room_number)
    {
        $canonical = self::normalizeRoomNumber($room_number);
        if ($canonical === null || $canonical === '') {
            return [];
        }

        $aliases = self::getRoomAliases($canonical);
        $hasRoomType = AreaMappingSchemaHelper::hasColumn('area_mappings', 'room_type');
        $selectFields = "am.id, am.room_number, am.area_selector, am.mapping_type, am.id, am.item_sku, am.category_id,
am.link_url, am.link_label, am.link_icon, am.link_image, am.content_target, am.content_image, am.display_order,
am.is_active,
i.name, i.retail_price, i.stock_quantity,
COALESCE(img.image_path, i.image_url) AS image_url";
        $roomCollate = 'utf8mb4_unicode_ci';

        try {
            // 1) Canonical room
            $primary = Database::queryAll(
                "SELECT {$selectFields} FROM area_mappings am
LEFT JOIN items i ON am.item_sku = i.sku
LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
WHERE am.is_active = 1 
  AND (i.sku IS NULL OR (i.status = 'live' AND i.is_active = 1 AND i.is_archived = 0))
  AND am.room_number COLLATE {$roomCollate} = ? COLLATE {$roomCollate}
ORDER BY am.display_order, am.id",
                [$canonical]
            );
            if (!empty($primary)) {
                self::enrichItemData($primary);
                return $primary;
            }

            // 2) Aliases
            if (!empty($aliases)) {
                $placeholders = implode(',', array_fill(0, count($aliases), '?'));
                $aliasRows = Database::queryAll(
                    "SELECT {$selectFields} FROM area_mappings am
LEFT JOIN items i ON am.item_sku = i.sku
LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
WHERE am.is_active = 1 
  AND (i.sku IS NULL OR (i.status = 'live' AND i.is_active = 1 AND i.is_archived = 0))
  AND am.room_number COLLATE {$roomCollate} IN ($placeholders)
ORDER BY am.display_order, am.id",
                    $aliases
                );
                if (!empty($aliasRows)) {
                    self::enrichItemData($aliasRows);
                    return $aliasRows;
                }
            }

            // 3) room_type fallback
            if ($hasRoomType && !empty($aliases)) {
                $placeholders = implode(',', array_fill(0, count($aliases), '?'));
                $rows = Database::queryAll(
                    "SELECT {$selectFields} FROM area_mappings am
LEFT JOIN items i ON am.item_sku = i.sku
LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
WHERE am.is_active = 1 
  AND (i.sku IS NULL OR (i.status = 'live' AND i.is_active = 1 AND i.is_archived = 0))
  AND am.room_type COLLATE {$roomCollate} IN ($placeholders)
ORDER BY am.display_order, am.id",
                    $aliases
                );
                if (!empty($rows)) {
                    self::enrichItemData($rows);
                    return $rows;
                }
            }

            return [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Normalize room identifiers
     */
    public static function normalizeRoomNumber($value)
    {
        $v = trim((string) $value);
        if ($v === '')
            return null;
        $lv = strtolower($v);
        if (in_array($lv, ['main', 'room_main', 'room-main', 'roommain'], true))
            return '0';
        if (in_array($lv, ['landing', 'room_landing', 'room-landing'], true))
            return 'A';
        if (preg_match('/^room(\d+)$/i', $v, $m))
            return (string) ((int) $m[1]);
        if (preg_match('/^room([A-Za-z])$/', $v, $m))
            return strtoupper($m[1]);
        if (is_numeric($v))
            return (string) ((int) $v);
        return $v;
    }

    /**
     * Get legacy aliases for a room
     */
    public static function getRoomAliases($room_number)
    {
        if ($room_number === null || $room_number === '')
            return [];
        $aliases = [$room_number, strtolower($room_number), strtoupper($room_number)];
        if (preg_match('/^\d+$/', $room_number)) {
            $aliases[] = 'room' . $room_number;
            $aliases[] = 'Room' . $room_number;
            $aliases[] = 'ROOM' . $room_number;
        } else {
            $numeric = null;
            if (preg_match('/^(?:room)?(\d+)$/i', $room_number, $m))
                $numeric = (string) ((int) $m[1]);
            if ($numeric !== null) {
                $aliases[] = $numeric;
                $aliases[] = 'room' . $numeric;
                $aliases[] = 'Room' . $numeric;
                $aliases[] = 'ROOM' . $numeric;
            } else {
                $aliases[] = 'room' . $room_number;
                $aliases[] = 'Room' . $room_number;
                $aliases[] = 'ROOM' . $room_number;
            }
        }
        if ($room_number === '0') {
            $aliases = array_merge($aliases, ['main', 'Main', 'MAIN', 'room_main', 'room-main', 'roomMain']);
        }
        if (strcasecmp($room_number, 'A') === 0) {
            $aliases = array_merge($aliases, ['landing', 'Landing', 'LANDING']);
        }
        return array_values(array_unique(array_filter($aliases)));
    }

    /**
     * Enrich item mappings with primary image URLs and current stock levels
     */
    private static function enrichItemData(array &$rows)
    {
        if (empty($rows))
            return;
        $skus = [];
        foreach ($rows as $r) {
            $sku = $r['item_sku'] ?? $r['sku'] ?? null;
            if ($sku)
                $skus[] = $sku;
        }
        $skus = array_values(array_unique(array_filter($skus)));
        if (empty($skus))
            return;

        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        try {
            // Fetch Images and basic data from items table
            $items = Database::queryAll(
                "SELECT i.sku, COALESCE(img.image_path, i.image_url) AS image_path, i.retail_price, i.name, i.stock_quantity
FROM items i
LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
WHERE i.sku IN ($placeholders)",
                $skus
            );
            $imgMap = [];
            $priceMap = [];
            $nameMap = [];
            foreach ($items as $it) {
                $skuKey = strtoupper(trim($it['sku']));
                $path = self::normalizeItemImageUrl($it['image_path'] ?? '');
                if ($path)
                    $imgMap[$skuKey] = $path;
                $priceMap[$skuKey] = $it['retail_price'];
                $nameMap[$skuKey] = $it['name'];
                $rowStockMap[$skuKey] = $it['stock_quantity'];
            }

            // Fetch Aggregated Stock from item_sizes (Source of Truth) IF they exist
            $variantStats = Database::queryAll(
                "SELECT item_sku, COUNT(*) as v_count, SUM(CASE WHEN is_active = 1 THEN stock_level ELSE 0 END) AS total_stock
FROM item_sizes
WHERE item_sku IN ($placeholders)
GROUP BY item_sku",
                $skus
            );
            $stockMap = [];
            $hasVariantsMap = [];
            foreach ($variantStats as $vs) {
                $skuK = strtoupper(trim($vs['item_sku']));
                $stockMap[$skuK] = (int) $vs['total_stock'];
                $hasVariantsMap[$skuK] = ((int) $vs['v_count']) > 0;
            }

            foreach ($rows as &$row) {
                $sku = $row['item_sku'] ?? $row['sku'] ?? null;
                if (!$sku)
                    continue;

                $skuKey = strtoupper(trim($sku));

                // Always try to enrich image if missing or generic
                if (isset($imgMap[$skuKey])) {
                    $row['image_url'] = $imgMap[$skuKey];
                }

                // Update stock: Priority to variants if they exist, otherwise use items table stock
                if (isset($hasVariantsMap[$skuKey]) && $hasVariantsMap[$skuKey]) {
                    $row['stock_quantity'] = $stockMap[$skuKey] ?? 0;
                } else if (isset($rowStockMap[$skuKey])) {
                    $row['stock_quantity'] = (int) $rowStockMap[$skuKey];
                }

                // Map retail_price to price if needed by consumer
                if (isset($priceMap[$skuKey])) {
                    $row['price'] = $priceMap[$skuKey];
                    $row['retail_price'] = $priceMap[$skuKey];
                }

                // Set name if missing
                if (isset($nameMap[$skuKey]) && (empty($row['name']) || $row['name'] === 'N/A')) {
                    $row['name'] = $nameMap[$skuKey];
                }
            }
        } catch (Throwable $e) {
            error_log("EnrichItemData Error: " . $e->getMessage());
        }
    }

    private static function normalizeItemImageUrl($path)
    {
        if (!$path)
            return '';
        if (preg_match('#^https?://#i', $path))
            return $path;
        $p = ltrim($path, '/');
        return (strpos($p, 'images/items/') === 0) ? '/' . $p : '/images/items/' . $p;
    }

    /**
     * Get live view mappings (derived from category assignments + explicit mappings)
     */
    public static function getLiveView($room_number, $debugMode = false, $overrideMapId = null)
    {
        $canonical = self::normalizeRoomNumber($room_number);
        $dbg = ['room' => $room_number];

        $coords = [];
        $coordsRow = null;

        if ($overrideMapId) {
            $coordsRow = Database::queryOne("SELECT coordinates FROM room_maps WHERE id = ?", [$overrideMapId]);
        }

        if (!$coordsRow) {
            $rmWhere = 'room_number = ?';
            if (AreaMappingSchemaHelper::hasColumn('room_maps', 'is_active'))
                $rmWhere .= ' AND is_active = 1';
            $coordsRow = Database::queryOne(
                "SELECT coordinates FROM room_maps WHERE $rmWhere ORDER BY updated_at DESC LIMIT 1",
                [$canonical]
            );
        }

        if ($coordsRow && !empty($coordsRow['coordinates'])) {
            $rawCoords = $coordsRow['coordinates'];

            // Handle double-encoded JSON
            $decoded = json_decode($rawCoords, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            if (is_array($decoded)) {
                // Handle various structures: {rectangles: [...]}, {polygons: [...]}, or top-level array
                if (isset($decoded['rectangles']) && is_array($decoded['rectangles'])) {
                    $coords = $decoded['rectangles'];
                } elseif (isset($decoded['polygons']) && is_array($decoded['polygons'])) {
                    $coords = $decoded['polygons'];
                } else {
                    $coords = $decoded;
                }
            }
        }

        if (!is_array($coords))
            $coords = [];

        // Build a lookup for coordinates by selector
        $coordsMap = [];
        foreach ($coords as $c) {
            if (isset($c['selector'])) {
                $coordsMap[$c['selector']] = $c;
            }
        }

        $catRow = Database::queryOne("SELECT c.id AS category_id, c.name AS category_name FROM room_category_assignments rca
JOIN categories c ON rca.category_id = c.id WHERE rca.room_number = ? AND rca.is_primary = 1 LIMIT 1", [$canonical]);
        $category_id = $catRow['category_id'] ?? null;
        $categoryName = $catRow['category_name'] ?? '';

        $items = [];
        $itemsHasActive = AreaMappingSchemaHelper::hasColumn('items', 'is_active');
        $itemsHasDisplayOrder = AreaMappingSchemaHelper::hasColumn('items', 'display_order');
        $orderExpr = $itemsHasDisplayOrder ? 'display_order, sku ASC' : 'sku ASC';

        if ($category_id) {
            $where = "i.category_id = ? AND i.status = 'live' AND i.is_archived = 0";
            if ($itemsHasActive)
                $where .= ' AND i.is_active = 1';
            $items = Database::queryAll("SELECT i.sku, i.name, i.category, i.retail_price, i.stock_quantity,
COALESCE(img.image_path, i.image_url) AS image_path FROM items i LEFT JOIN item_images img ON i.sku = img.sku AND
img.is_primary = 1 WHERE $where ORDER BY $orderExpr", [$category_id]);
        }
        // Note: Removed legacy category text field fallback. Use category_id FK as SSoT.

        $explicit = self::fetchMappings($canonical);
        $explicitSelectors = [];
        foreach ($explicit as $em) {
            $sel = $em['area_selector'] ?? '';
            if ($sel) {
                $explicitSelectors[] = $sel;
            }
        }

        $combined = [];

        // 1. Add explicit mappings first (with coordinates)
        foreach ($explicit as $em) {
            $selector = $em['area_selector'] ?? '';
            $em['coords'] = $coordsMap[$selector] ?? null;
            $combined[] = $em;
        }

        // 2. Add derived mappings for slots not taken by explicit ones
        foreach ($items as $i => $it) {
            $selector = '.area-' . ($i + 1);
            if (in_array($selector, $explicitSelectors)) {
                continue;
            }

            $sku = $it['sku'] ?? null;
            $imgPath = $it['image_path'] ?? '';
            $imgUrl = $imgPath ? self::normalizeItemImageUrl($imgPath) : '/images/items/placeholder.webp';

            $combined[] = [
                'id' => null,
                'room_number' => $canonical,
                'area_selector' => $selector,
                'mapping_type' => 'item',
                'sku' => $sku,
                'name' => $it['name'] ?? '',
                'price' => $it['retail_price'] ?? 0,
                'stock_quantity' => $it['stock_quantity'] ?? 0,
                'category_id' => null,
                'display_order' => $i + 1,
                'derived' => true,
                'image_url' => $imgUrl,
                'coords' => $coordsMap[$selector] ?? null
            ];
        }

        self::enrichItemData($combined);

        return [
            'success' => true,
            'mappings' => $combined,
            'category' => $categoryName,
            'coordinates_count' => count($coords),
            'debug' => $debugMode ? $dbg : null
        ];
    }
}
