<?php
/**
 – unified category system (2025-08-01)
 *
 * Produces global $categories in the structure:
 *   $categories = [
 *       'tshirts' => [
 *           'label'    => 'T-Shirts',
 *           'products' => [ [ 'sku'=>.., 'productName'=>.., ... ], ... ]
 *       ],
 *       ...
 *   ];
 */

// Avoid duplicate work if already populated
if (!isset($categories) || !is_array($categories) || empty($categories)) {
    $categories = [];

    try {
        // DB connection (uses singleton from api/config.php)
        require_once __DIR__ . '/../api/config.php';
        $pdo = Database::getInstance();

        /* ------------------------------------------------------------------
         * 1) Get canonical list of categories – ordered for navigation
         *    Be resilient if categories.slug or display_order are missing.
         * ----------------------------------------------------------------*/
        $catRows = [];
        try {
            // Preferred: use explicit slug and display_order if present
            $catRows = Database::queryAll(
                "SELECT
                     id,
                     COALESCE(slug, LOWER(REPLACE(TRIM(name), ' ', '-'))) AS slug,
                     name,
                     display_order
                 FROM categories
                 ORDER BY display_order ASC"
            );
        } catch (Throwable $eCat1) {
            // Fallback: minimal columns, order by name
            try {
                $tmp = Database::queryAll(
                    "SELECT id, name FROM categories ORDER BY name ASC"
                );
                foreach ($tmp as $row) {
                    $row['slug'] = strtolower(str_replace(' ', '-', trim($row['name'])));
                    $catRows[] = $row;
                }
            } catch (Throwable $eCat2) {
                error_log('shop_data_loader categories query error: ' . $eCat1->getMessage() . ' | fallback: ' . $eCat2->getMessage());
                $catRows = [];
            }
        }

        foreach ($catRows as $row) {
            $slug = $row['slug'];
            $categories[$slug] = [
                'label'    => $row['name'],
                'products' => [],
            ];
        }

        /* ------------------------------------------------------------------
         * 2) Fetch products and attach to their category bucket
         *    Prefer items.* if table exists; fallback to products.* if present;
         *    otherwise degrade gracefully.
         * ----------------------------------------------------------------*/
        $products = [];

        // Detect available product table(s)
        $tables = [];
        try {
            $rows = Database::queryAll("SELECT TABLE_NAME AS t FROM information_schema.tables WHERE table_schema = DATABASE()");
            foreach ($rows as $r) { $tables[strtolower($r['t'])] = true; }
        } catch (Throwable $eTbl) {
            // non-fatal; proceed with optimistic attempts
        }
        $hasItems = isset($tables['items']);
        $hasProducts = isset($tables['products']);

        if ($hasItems) {
            try {
                // Attempt category_id join first
                $products = Database::queryAll(
                    "SELECT i.sku,
                            i.name                        AS productName,
                            COALESCE(i.retailPrice, 0)    AS price,
                            COALESCE(i.stockLevel, 0)     AS stock,
                            i.description,
                            i.imageUrl,
                            COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                     FROM items i
                     JOIN categories c ON i.category_id = c.id"
                );
                error_log('shop_data_loader: products via items.category_id join = ' . count($products));
            } catch (Throwable $e1) {
                // Fallback: join by legacy items.category name
                try {
                    $products = Database::queryAll(
                        "SELECT i.sku,
                                i.name                        AS productName,
                                COALESCE(i.retailPrice, 0)    AS price,
                                COALESCE(i.stockLevel, 0)     AS stock,
                                i.description,
                                i.imageUrl,
                                COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                         FROM items i
                         JOIN categories c ON i.category = c.name"
                    );
                    error_log('shop_data_loader: products via items.category name join = ' . count($products));
                } catch (Throwable $e2) {
                    // Last-resort fallback: no join. Use items.category to compute slug.
                    error_log('shop_data_loader product query error (items): ' . $e1->getMessage() . ' | fallback: ' . $e2->getMessage());
                    try {
                        $rows = Database::queryAll(
                            "SELECT i.sku,
                                    i.name                     AS productName,
                                    COALESCE(i.retailPrice, 0) AS price,
                                    COALESCE(i.stockLevel, 0)  AS stock,
                                    i.description,
                                    i.imageUrl,
                                    i.category                 AS cat_name
                             FROM items i"
                        );
                        foreach ($rows as $r) {
                            $catName = trim((string)($r['cat_name'] ?? ''));
                            $slug = $catName !== ''
                                ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', str_replace('&', 'and', $catName)))
                                : 'uncategorized';
                            $slug = trim($slug, '-');
                            $rOut = $r;
                            unset($rOut['cat_name']);
                            $rOut['slug'] = $slug;
                            $products[] = $rOut;
                        }
                        error_log('shop_data_loader: products via items-only fallback = ' . count($products));
                    } catch (Throwable $e3) {
                        error_log('shop_data_loader items-only fallback error: ' . $e3->getMessage());
                        $products = [];
                    }
                }
            }
        } elseif ($hasProducts) {
            // Fallback to products table with flexible column mapping
            try {
                // Try category_id join if exists
                $products = Database::queryAll(
                    "SELECT p.sku,
                            p.name AS productName,
                            COALESCE(p.retailPrice, p.price, p.sale_price, 0) AS price,
                            COALESCE(p.stockLevel, p.stock, p.quantity, 0) AS stock,
                            COALESCE(p.description, p.details, '') AS description,
                            COALESCE(p.imageUrl, p.primary_image, p.image, '') AS imageUrl,
                            COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                     FROM products p
                     JOIN categories c ON p.category_id = c.id"
                );
                error_log('shop_data_loader: products via products.category_id join = ' . count($products));
            } catch (Throwable $ep1) {
                try {
                    // Join by name if possible
                    $products = Database::queryAll(
                        "SELECT p.sku,
                                p.name AS productName,
                                COALESCE(p.retailPrice, p.price, p.sale_price, 0) AS price,
                                COALESCE(p.stockLevel, p.stock, p.quantity, 0) AS stock,
                                COALESCE(p.description, p.details, '') AS description,
                                COALESCE(p.imageUrl, p.primary_image, p.image, '') AS imageUrl,
                                COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                         FROM products p
                         JOIN categories c ON p.category = c.name"
                    );
                    error_log('shop_data_loader: products via products.category name join = ' . count($products));
                } catch (Throwable $ep2) {
                    // No join: map to slug using products.category
                    error_log('shop_data_loader products-table query error: ' . $ep1->getMessage() . ' | fallback: ' . $ep2->getMessage());
                    try {
                        $rows = Database::queryAll(
                            "SELECT p.sku,
                                    p.name AS productName,
                                    COALESCE(p.retailPrice, p.price, p.sale_price, 0) AS price,
                                    COALESCE(p.stockLevel, p.stock, p.quantity, 0) AS stock,
                                    COALESCE(p.description, p.details, '') AS description,
                                    COALESCE(p.imageUrl, p.primary_image, p.image, '') AS imageUrl,
                                    p.category AS cat_name
                             FROM products p"
                        );
                        foreach ($rows as $r) {
                            $catName = trim((string)($r['cat_name'] ?? ''));
                            $slug = $catName !== ''
                                ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', str_replace('&', 'and', $catName)))
                                : 'uncategorized';
                            $slug = trim($slug, '-');
                            $rOut = $r;
                            unset($rOut['cat_name']);
                            $rOut['slug'] = $slug;
                            $products[] = $rOut;
                        }
                        error_log('shop_data_loader: products via products-only fallback = ' . count($products));
                    } catch (Throwable $ep3) {
                        error_log('shop_data_loader products-only fallback error: ' . $ep3->getMessage());
                        $products = [];
                    }
                }
            }
        } else {
            // Neither items nor products — leave products empty; categories will render empty message
            error_log('shop_data_loader: no items/products table found in schema');
            $products = [];
        }

        // Build canonical keys set from categories loaded above
        $canonicalKeys = array_fill_keys(array_keys($categories), true);
        // Mapping from legacy names to canonical slugs
        $nameToCanonical = [
            'fluid art' => 'artwork',
            'decor' => 'artwork',
            'hats' => 't-shirts',
        ];
        // Mapping from stray slugs to canonical slugs (defensive)
        $slugToCanonical = [
            'fluid-art' => 'artwork',
            'decor' => 'artwork',
            'hats' => 't-shirts',
            'windowwraps' => 'window-wraps',
            'window_wraps' => 'window-wraps',
        ];

        foreach ($products as $p) {
            $origSlug = $p['slug'];
            $catName = isset($p['cat_name']) ? strtolower(trim($p['cat_name'])) : '';
            unset($p['slug']); // not needed for template
            unset($p['cat_name']);

            // Decide target canonical slug
            $target = null;
            if (isset($canonicalKeys[$origSlug])) {
                $target = $origSlug;
            } elseif ($catName && isset($nameToCanonical[$catName])) {
                $target = $nameToCanonical[$catName];
            } elseif (isset($slugToCanonical[$origSlug])) {
                $target = $slugToCanonical[$origSlug];
            }

            // Drop truly uncategorized or unmapped
            if (!$target || !isset($canonicalKeys[$target])) {
                continue;
            }

            $categories[$target]['products'][] = $p;
        }

        // Remove empty categories from output
        foreach ($categories as $slug => $cat) {
            if (empty($cat['products'])) {
                unset($categories[$slug]);
            }
        }

    } catch (Throwable $e) {
        error_log('shop_data_loader error: ' . $e->getMessage());
        $categories = [];
    }
}
?>
