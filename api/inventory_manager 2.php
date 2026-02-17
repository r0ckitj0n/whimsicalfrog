<?php
/**
 * api/inventory_manager.php
 * Inventory Management API
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/helpers/InventoryManagerHelper.php';

AuthHelper::requireAdmin();

try {
    Database::getInstance();
    try {
        Database::execute("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Exception $e) {
        Logger::warning('Failed/redundant SET NAMES in inventory_manager', ['error' => $e->getMessage()]);
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $category = trim((string) ($_GET['category'] ?? ''));
    $mode = trim((string) ($_GET['mode'] ?? 'list'));
    $focus = trim((string) ($_GET['focus'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(250, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(i.name LIKE ? OR i.sku LIKE ? OR i.category LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like]);
    }
    if ($category !== '') {
        $where[] = 'i.category = ?';
        $params[] = $category;
    }
    if ($focus === 'tshirts') {
        $where[] = '(LOWER(i.name) LIKE ? OR LOWER(i.category) LIKE ?)';
        $params = array_merge($params, ['%shirt%', '%shirt%']);
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = (int) (Database::queryOne("SELECT COUNT(*) AS c FROM items i $whereSql", $params)['c'] ?? 0);

    $rows = Database::queryAll("
        SELECT i.sku, i.name, COALESCE(cat.name, i.category) AS category, i.retail_price, i.stock_quantity, i.reorder_point, img.image_path,
               (SELECT COUNT(*) FROM item_sizes s WHERE s.item_sku COLLATE utf8mb4_unicode_ci = i.sku COLLATE utf8mb4_unicode_ci AND s.is_active = 1) AS size_count,
               (SELECT COUNT(*) FROM item_colors c WHERE c.item_sku COLLATE utf8mb4_unicode_ci = i.sku COLLATE utf8mb4_unicode_ci AND c.is_active = 1) AS color_count,
               (SELECT COUNT(*) FROM item_sizes sc WHERE sc.item_sku COLLATE utf8mb4_unicode_ci = i.sku COLLATE utf8mb4_unicode_ci AND sc.is_active = 1 AND sc.color_id IS NOT NULL) AS color_size_count
        FROM items i
        LEFT JOIN categories cat ON i.category_id = cat.id
        LEFT JOIN item_images img ON i.sku COLLATE utf8mb4_unicode_ci = img.sku COLLATE utf8mb4_unicode_ci AND img.is_primary = 1
        $whereSql ORDER BY i.name ASC LIMIT $limit OFFSET $offset", $params);

    $skus = array_filter(array_column($rows, 'sku'));
    $sizesBySku = [];
    $colorsBySku = [];
    $variantsBySku = [];

    if ($skus) {
        $ph = implode(',', array_fill(0, count($skus), '?'));
        $sizeRows = Database::queryAll("SELECT s.id, s.item_sku, s.color_id, s.size_name, s.size_code, s.stock_level, s.gender, c.color_name FROM item_sizes s LEFT JOIN item_colors c ON s.color_id = c.id WHERE s.is_active = 1 AND s.item_sku IN ($ph) AND (s.color_id IS NULL OR c.is_active = 1) ORDER BY s.item_sku, s.gender, s.display_order, s.size_name", $skus);
        foreach ($sizeRows as $sr) {
            $s = $sr['item_sku'];
            $sizesBySku[$s][] = ['id' => (int) $sr['id'], 'size_name' => $sr['size_name'], 'size_code' => $sr['size_code'], 'stock_level' => (int) $sr['stock_level']];
            $variantsBySku[$s][] = ['kind' => 'size', 'size_id' => (int) $sr['id'], 'gender' => $sr['gender'], 'size_name' => $sr['size_name'], 'size_code' => $sr['size_code'], 'color_id' => $sr['color_id'] ? (int) $sr['color_id'] : null, 'color_name' => $sr['color_name'], 'stock_level' => (int) $sr['stock_level'], 'dims' => ['gender' => $sr['gender'], 'size' => $sr['size_code'] ?: $sr['size_name'], 'color' => $sr['color_name']]];
        }
        $colorRows = Database::queryAll("SELECT id, item_sku, color_name, stock_level FROM item_colors WHERE is_active = 1 AND item_sku IN ($ph) ORDER BY item_sku, display_order, color_name", $skus);
        foreach ($colorRows as $cr) {
            $s = $cr['item_sku'];
            $colorsBySku[$s][] = ['id' => (int) $cr['id'], 'color_name' => $cr['color_name'], 'stock_level' => (int) $cr['stock_level']];
            $variantsBySku[$s][] = ['kind' => 'color', 'color_id' => (int) $cr['id'], 'color_name' => $cr['color_name'], 'stock_level' => (int) $cr['stock_level'], 'dims' => ['color' => $cr['color_name']]];
        }
    }

    $items = [];
    foreach ($rows as $r) {
        $s = $r['sku'];
        $sc = (int) $r['size_count'];
        $cc = (int) $r['color_count'];
        $csc = (int) $r['color_size_count'];
        $dims = [];
        if ($sc > 0) {
            $dims = ['gender', 'size'];
            if ($csc > 0)
                $dims[] = 'color';
        } elseif ($cc > 0)
            $dims = ['color'];

        $items[] = [
            'sku' => $s,
            'name' => $r['name'],
            'category' => $r['category'],
            'retail_price' => $r['retail_price'],
            'stock_quantity' => (int) $r['stock_quantity'],
            'reorder_point' => $r['reorder_point'],
            'image_url' => InventoryManagerHelper::buildImageUrl($r['image_path']),
            'has_sizes' => $sc > 0,
            'has_colors' => $cc > 0,
            'has_color_sizes' => $csc > 0,
            'sizes' => $sizesBySku[$s] ?? [],
            'colors' => $colorsBySku[$s] ?? [],
            'variant_dimensions' => $dims,
            'variants' => InventoryManagerHelper::dedupeVariants($variantsBySku[$s] ?? [])
        ];
    }

    Response::success(['page' => $page, 'limit' => $limit, 'total' => $total, 'items' => $items, 'mode' => $mode, 'focus' => $focus]);
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
