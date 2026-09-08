<?php
/**
 * GET /api/featured_products.php
 * Lightweight public list of currently featured, saleable shop items
 * for the storefront loading splash.
 *
 * Primary source: items.is_featured = 1
 * Fallback: items currently placed on active room item mappings
 * (the shop's existing "featured on display" mechanism).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/config.php';

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function wf_featured_products_normalize(array $rows): array
{
    $products = [];
    $seen = [];
    foreach ($rows as $row) {
        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku === '' || isset($seen[$sku])) {
            continue;
        }
        $stock = (int) ($row['stock'] ?? 0);
        if ($stock <= 0) {
            continue;
        }
        $imageUrl = trim((string) ($row['image_url'] ?? ''));
        if ($imageUrl !== '' && !preg_match('#^(https?:)?//#i', $imageUrl) && $imageUrl[0] !== '/') {
            $imageUrl = '/' . $imageUrl;
        }
        $seen[$sku] = true;
        $products[] = [
            'sku' => $sku,
            'item_name' => (string) ($row['item_name'] ?? ''),
            'price' => isset($row['price']) ? (float) $row['price'] : 0.0,
            'stock' => $stock,
            'image_url' => $imageUrl,
        ];
    }
    return $products;
}

try {
    Database::getInstance();

    $hasFeaturedColumn = false;
    try {
        $col = Database::queryOne(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'items'
               AND COLUMN_NAME = 'is_featured'"
        );
        $hasFeaturedColumn = ((int) ($col['c'] ?? 0)) > 0;
    } catch (Throwable $e) {
        $hasFeaturedColumn = false;
    }

    $selectSql = "SELECT i.sku,
                         i.name AS item_name,
                         COALESCE(i.retail_price, 0) AS price,
                         COALESCE(i.stock_quantity, 0) AS stock,
                         COALESCE(img.image_path, i.image_url, '') AS image_url
                  FROM items i
                  LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
                  WHERE i.status = 'live'
                    AND i.is_active = 1
                    AND i.is_archived = 0
                    AND COALESCE(i.stock_quantity, 0) > 0";

    $products = [];

    if ($hasFeaturedColumn) {
        $products = wf_featured_products_normalize(
            Database::queryAll($selectSql . ' AND i.is_featured = 1 ORDER BY i.name ASC')
        );
    }

    // Fallback: products currently featured on active room displays
    if (count($products) === 0) {
        $mappedSql = "SELECT i.sku,
                             i.name AS item_name,
                             COALESCE(i.retail_price, 0) AS price,
                             COALESCE(i.stock_quantity, 0) AS stock,
                             COALESCE(img.image_path, i.image_url, '') AS image_url
                      FROM area_mappings am
                      INNER JOIN items i ON i.sku = am.item_sku
                      LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
                      WHERE am.is_active = 1
                        AND am.mapping_type = 'item'
                        AND am.item_sku IS NOT NULL
                        AND am.item_sku <> ''
                        AND i.status = 'live'
                        AND i.is_active = 1
                        AND i.is_archived = 0
                        AND COALESCE(i.stock_quantity, 0) > 0
                      ORDER BY am.display_order ASC, i.name ASC";
        try {
            $products = wf_featured_products_normalize(Database::queryAll($mappedSql));
        } catch (Throwable $e) {
            error_log('[featured_products] room mapping fallback failed: ' . $e->getMessage());
            $products = [];
        }
    }

    echo json_encode([
        'success' => true,
        'products' => $products,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[featured_products] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load featured products',
        'products' => [],
    ]);
}
