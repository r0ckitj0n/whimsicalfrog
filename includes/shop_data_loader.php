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
            $catStmt = $pdo->query(
                "SELECT
                     id,
                     COALESCE(slug, LOWER(REPLACE(TRIM(name), ' ', '-'))) AS slug,
                     name,
                     display_order
                 FROM categories
                 ORDER BY display_order ASC"
            );
            $catRows = $catStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $eCat1) {
            // Fallback: minimal columns, order by name
            try {
                $catStmt = $pdo->query(
                    "SELECT id, name FROM categories ORDER BY name ASC"
                );
                $tmp = $catStmt->fetchAll(PDO::FETCH_ASSOC);
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
         *    Prefer items.category_id join; fallback to items.category by name.
         * ----------------------------------------------------------------*/
        $products = [];
        try {
            // Attempt category_id join first
            $prodStmt = $pdo->query(
                "SELECT i.sku,
                        i.name                        AS productName,
                        COALESCE(i.retailPrice, 0)    AS price,
                        COALESCE(i.stockLevel, 0)     AS stock,
                        i.description,
                        i.imageUrl,
                        /* Compute slug if NULL */
                        COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                 FROM items i
                 JOIN categories c ON i.category_id = c.id"
            );
            $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
            error_log('shop_data_loader: products via category_id join = ' . count($products));
        } catch (Throwable $e1) {
            // Fallback: join by legacy items.category name
            try {
                $prodStmt = $pdo->query(
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
                $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log('shop_data_loader: products via name join = ' . count($products));
            } catch (Throwable $e2) {
                // Last-resort fallback: no join. Use items.category to compute slug.
                error_log('shop_data_loader product query error: ' . $e1->getMessage() . ' | fallback: ' . $e2->getMessage());
                try {
                    $stmtItems = $pdo->query(
                        "SELECT i.sku,
                                i.name                     AS productName,
                                COALESCE(i.retailPrice, 0) AS price,
                                COALESCE(i.stockLevel, 0)  AS stock,
                                i.description,
                                i.imageUrl,
                                i.category                 AS cat_name
                         FROM items i"
                    );
                    $rows = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                    // Normalize slug from cat_name
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
