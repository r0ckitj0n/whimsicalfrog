<?php

/**
 * – unified item category system (2025-08-01)
 *
 * Produces global $categories in the structure:
 *   $categories = [
 *       'tshirts' => [
 *           'label'    => 'T-Shirts',
 *           'items' => [ [ 'sku'=>.., 'item_name'=>.., ... ], ... ]
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
            // Filter categories: Keep if not assigned to any room OR assigned to at least one active room
            $catRows = Database::queryAll(
                "SELECT DISTINCT
                      c.id,
                      COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug,
                      c.name,
                      c.display_order
                  FROM categories c
                  LEFT JOIN room_category_assignments rca ON c.id = rca.category_id
                  LEFT JOIN room_settings rs ON rca.room_number = rs.room_number
                  WHERE (rca.room_number IS NULL OR rs.is_active = 1 OR rs.room_number IS NULL)
                  ORDER BY c.display_order ASC"
            );
        } catch (Throwable $eCat1) {
            // Fallback: minimal columns, order by name
            try {
                $tmp = Database::queryAll(
                    "SELECT DISTINCT c.id, c.name 
                     FROM categories c
                     LEFT JOIN room_category_assignments rca ON c.id = rca.category_id
                     LEFT JOIN room_settings rs ON rca.room_number = rs.room_number
                     WHERE (rca.room_number IS NULL OR rs.is_active = 1 OR rs.room_number IS NULL)
                     ORDER BY c.name ASC"
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
            $slug = !empty($row['slug']) ? $row['slug'] : strtolower(str_replace(' ', '-', trim($row['name'])));
            $categories[$slug] = [
                'slug' => $slug,
                'label' => $row['name'],
                'items' => [],
            ];
        }

        // Ensure every item is assigned to a category
        $categories['uncategorized'] = [
            'slug' => 'uncategorized',
            'label' => 'Uncategorized',
            'items' => []
        ];

        /* ------------------------------------------------------------------
         * 2) Fetch items and attach to their category bucket
         *    Prefer items.* if table exists; fallback to legacy items table if present;
         *    otherwise degrade gracefully.
         * ----------------------------------------------------------------*/
        $items_list = [];

        // Detect available item table(s)
        $tables = [];
        try {
            $rows = Database::queryAll("SELECT TABLE_NAME AS t FROM information_schema.tables WHERE table_schema = DATABASE()");
            foreach ($rows as $r) {
                $tables[strtolower($r['t'])] = true;
            }
        } catch (Throwable $eTbl) {
            // non-fatal; proceed with optimistic attempts
        }
        $hasItems = isset($tables['items']);
        $hasLegacyProductsTable = isset($tables['products']);
        $hasSaleItems = isset($tables['sale_items']);

        if ($hasItems) {
            try {
                // Use category_id FK as single source of truth - no fallbacks to legacy text field
                $items_list = Database::queryAll(
                    "SELECT i.sku,
                            i.name                        AS item_name,
                            COALESCE(i.retail_price, 0)    AS price,
                            COALESCE(i.stock_quantity, 0)     AS stock,
                            i.description,
                            COALESCE(img.image_path, i.image_url) AS image_url,
                            COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                     FROM items i
                     JOIN categories c ON i.category_id = c.id
                     LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
                     WHERE i.status = 'live' AND i.is_active = 1 AND i.is_archived = 0"
                );
                error_log('shop_data_loader: items via category_id FK = ' . count($items_list));
            } catch (Throwable $e) {
                // @reason: Log error and fail cleanly - no silent fallback to legacy data sources
                error_log('shop_data_loader CRITICAL: items query failed: ' . $e->getMessage());
                $items_list = [];
            }
        } elseif ($hasLegacyProductsTable) {
            // Fallback to legacy products table with flexible column mapping
            try {
                // Try category_id join if exists
                $items_list = Database::queryAll(
                    "SELECT p.sku,
                            p.name AS item_name,
                            COALESCE(p.retail_price, p.price, p.sale_price, 0) AS price,
                            COALESCE(p.stock_quantity, p.stock, p.quantity, 0) AS stock,
                            COALESCE(p.description, p.details, '') AS description,
                            COALESCE(p.image_url, p.primary_image, p.image, '') AS image_url,
                            COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                     FROM products p
                     JOIN categories c ON p.category_id = c.id
                     WHERE (p.status = 'live' OR p.status IS NULL) AND (p.is_active = 1 OR p.is_active IS NULL) AND (p.is_archived = 0 OR p.is_archived IS NULL)"
                );
                error_log('shop_data_loader: items via legacy table category_id join = ' . count($items_list));
            } catch (Throwable $ep1) {
                try {
                    // Join by name if possible
                    $items_list = Database::queryAll(
                        "SELECT p.sku,
                                p.name AS item_name,
                                COALESCE(p.retail_price, p.price, p.sale_price, 0) AS price,
                                COALESCE(p.stock_quantity, p.stock, p.quantity, 0) AS stock,
                                COALESCE(p.description, p.details, '') AS description,
                                COALESCE(p.image_url, p.primary_image, p.image, '') AS image_url,
                                COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                         FROM products p
                         JOIN categories c ON p.category = c.name
                         WHERE (p.status = 'live' OR p.status IS NULL) AND (p.is_active = 1 OR p.is_active IS NULL) AND (p.is_archived = 0 OR p.is_archived IS NULL)"
                    );
                    error_log('shop_data_loader: items via legacy table category name join = ' . count($items_list));
                } catch (Throwable $ep2) {
                    // No join: map to slug using legacy table category
                    error_log('shop_data_loader legacy table query error: ' . $ep1->getMessage() . ' | fallback: ' . $ep2->getMessage());
                    try {
                        $rows = Database::queryAll(
                            "SELECT p.sku,
                                    p.name AS item_name,
                                    COALESCE(p.retail_price, p.price, p.sale_price, 0) AS price,
                                    COALESCE(p.stock_quantity, p.stock, p.quantity, 0) AS stock,
                                    COALESCE(p.description, p.details, '') AS description,
                                    COALESCE(p.image_url, p.primary_image, p.image, '') AS image_url,
                                    p.category AS cat_name
                             FROM products p
                             WHERE (p.status = 'live' OR p.status IS NULL) AND (p.is_active = 1 OR p.is_active IS NULL) AND (p.is_archived = 0 OR p.is_archived IS NULL)"
                        );
                        foreach ($rows as $r) {
                            $catName = trim((string) ($r['cat_name'] ?? ''));
                            $slug = $catName !== ''
                                ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', str_replace('&', 'and', $catName)))
                                : 'uncategorized';
                            $slug = trim($slug, '-');
                            $rOut = $r;
                            unset($rOut['cat_name']);
                            $rOut['slug'] = $slug;
                            $items_list[] = $rOut;
                        }
                        error_log('shop_data_loader: items via legacy table fallback = ' . count($items_list));
                    } catch (Throwable $ep3) {
                        error_log('shop_data_loader legacy table fallback error: ' . $ep3->getMessage());
                        $items_list = [];
                    }
                }
            }
        } elseif ($hasSaleItems) {
            // Fallback to sale_items table (legacy) with flexible column mapping
            try {
                // Prefer category_id join if present
                $items_list = Database::queryAll(
                    "SELECT s.sku,
                            COALESCE(s.name, s.item_name) AS item_name,
                            COALESCE(s.retail_price, s.price, s.sale_price, 0) AS price,
                            COALESCE(s.stock_quantity, s.stock, s.quantity, 0) AS stock,
                            COALESCE(s.description, s.details, '') AS description,
                            COALESCE(s.image_url, s.primary_image, s.image, '') AS image_url,
                            COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                     FROM sale_items s
                     JOIN categories c ON s.category_id = c.id
                     WHERE (s.status = 'live' OR s.status IS NULL) AND (s.is_active = 1 OR s.is_active IS NULL)"
                );
                error_log('shop_data_loader: items via sale_items.category_id join = ' . count($items_list));
            } catch (Throwable $es1) {
                try {
                    // Join by category name field if available
                    $items_list = Database::queryAll(
                        "SELECT s.sku,
                                COALESCE(s.name, s.item_name) AS item_name,
                                COALESCE(s.retail_price, s.price, s.sale_price, 0) AS price,
                                COALESCE(s.stock_quantity, s.stock, s.quantity, 0) AS stock,
                                COALESCE(s.description, s.details, '') AS description,
                                COALESCE(s.image_url, s.primary_image, s.image, '') AS image_url,
                                COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                         FROM sale_items s
                         JOIN categories c ON s.category = c.name
                         WHERE (s.status = 'live' OR s.status IS NULL) AND (s.is_active = 1 OR s.is_active IS NULL)"
                    );
                    error_log('shop_data_loader: items via sale_items.category name join = ' . count($items_list));
                } catch (Throwable $es2) {
                    // Compute slug from sale_items.category text
                    error_log('shop_data_loader sale_items-table query error: ' . $es1->getMessage() . ' | fallback: ' . $es2->getMessage());
                    try {
                        $rows = Database::queryAll(
                            "SELECT s.sku,
                                    COALESCE(s.name, s.item_name) AS item_name,
                                    COALESCE(s.retail_price, s.price, s.sale_price, 0) AS price,
                                    COALESCE(s.stock_quantity, s.stock, s.quantity, 0) AS stock,
                                    COALESCE(s.description, s.details, '') AS description,
                                    COALESCE(s.image_url, s.primary_image, s.image, '') AS image_url,
                                    s.category AS cat_name
                             FROM sale_items s
                             WHERE (s.status = 'live' OR s.status IS NULL) AND (s.is_active = 1 OR s.is_active IS NULL)"
                        );
                        foreach ($rows as $r) {
                            $catName = trim((string) ($r['cat_name'] ?? ''));
                            $slug = $catName !== ''
                                ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', str_replace('&', 'and', $catName)))
                                : 'uncategorized';
                            $slug = trim($slug, '-');
                            $rOut = $r;
                            unset($rOut['cat_name']);
                            $rOut['slug'] = $slug;
                            $items_list[] = $rOut;
                        }
                        error_log('shop_data_loader: items via sale_items-only fallback = ' . count($items_list));
                    } catch (Throwable $es3) {
                        error_log('shop_data_loader sale_items-only fallback error: ' . $es3->getMessage());
                        $items_list = [];
                    }
                }
            }
        } else {
            // No known item tables — leave items empty; categories will render empty message
            error_log('shop_data_loader: no items/legacy table found in schema');
            $items_list = [];
        }

        // Build canonical keys set from categories loaded above
        $canonicalKeys = array_fill_keys(array_keys($categories), true);

        // Before distributing into categories, fix stock values by aggregating color/size stock when main stock is zero
        try {
            $skuList = [];
            foreach ($items_list as $pTmp) {
                if (!empty($pTmp['sku'])) {
                    $skuList[] = $pTmp['sku'];
                }
            }
            if (!empty($skuList)) {
                // Build placeholder string for IN clause safely
                $placeholders = implode(',', array_fill(0, count($skuList), '?'));
                $colorSums = [];
                $sizeSums = [];
                try {
                    $rowsC = Database::queryAll(
                        "SELECT item_sku, COALESCE(SUM(stock_level), 0) AS sum_color
                         FROM item_colors
                         WHERE is_active = 1 AND item_sku IN ($placeholders)
                         GROUP BY item_sku",
                        $skuList
                    );
                    foreach ($rowsC as $r) {
                        $colorSums[$r['item_sku']] = (int) $r['sum_color'];
                    }
                } catch (Throwable $eAggC) {
                    error_log('[shop_data_loader] color stock aggregation failed: ' . $eAggC->getMessage());
                }
                try {
                    $rowsS = Database::queryAll(
                        "SELECT item_sku, COALESCE(SUM(stock_level), 0) AS sum_size
                         FROM item_sizes
                         WHERE is_active = 1 AND item_sku IN ($placeholders)
                         GROUP BY item_sku",
                        $skuList
                    );
                    foreach ($rowsS as $r) {
                        $sizeSums[$r['item_sku']] = (int) $r['sum_size'];
                    }
                } catch (Throwable $eAggS) {
                    error_log('[shop_data_loader] size stock aggregation failed: ' . $eAggS->getMessage());
                }
                $hasSizes = [];
                try {
                    $rowsV = Database::queryAll(
                        "SELECT DISTINCT item_sku FROM item_sizes WHERE item_sku IN ($placeholders)",
                        $skuList
                    );
                    foreach ($rowsV as $r) {
                        $hasSizes[$r['item_sku']] = true;
                    }
                } catch (Throwable $eAggV) {
                    error_log('[shop_data_loader] variant existence check failed: ' . $eAggV->getMessage());
                }

                // NOTE: Master stock mode.
                // Do NOT override item stock from variant sums. items.stock_quantity is the public-facing selling limit.
            }
        } catch (Throwable $eAgg) {
            error_log('[shop_data_loader] stock aggregation pass failed: ' . $eAgg->getMessage());
        }

        // Identify categories that are assigned to rooms, but ONLY to inactive rooms
        $inactiveSlugs = [];
        try {
            $inactiveRows = Database::queryAll(
                "SELECT DISTINCT COALESCE(c.slug, LOWER(REPLACE(TRIM(c.name), ' ', '-'))) AS slug
                 FROM categories c
                 JOIN room_category_assignments rca ON c.id = rca.category_id
                 LEFT JOIN room_settings rs ON rca.room_number = rs.room_number
                 GROUP BY c.id
                 HAVING COUNT(rca.room_number) > 0 AND SUM(COALESCE(rs.is_active, 0)) = 0"
            );
            foreach ($inactiveRows as $ir) {
                if (!empty($ir['slug']))
                    $inactiveSlugs[$ir['slug']] = true;
            }
        } catch (Throwable $eInact) {
        }

        foreach ($items_list as $p) {
            $origSlug = !empty($p['slug']) ? $p['slug'] : 'uncategorized';

            // Skip items that belong to inactive categories
            if (isset($inactiveSlugs[$origSlug])) {
                continue;
            }

            $catName = isset($p['cat_name']) ? strtolower(trim($p['cat_name'])) : '';
            unset($p['slug']); // not needed for template
            unset($p['cat_name']);

            // Decide target canonical slug
            $target = null;
            if (isset($canonicalKeys[$origSlug])) {
                $target = $origSlug;
            } else {
                // If no matching category found, put in uncategorized
                $target = 'uncategorized';
            }

            // Ensure the target exists in categories
            if (!isset($categories[$target])) {
                $target = 'uncategorized';
            }

            $categories[$target]['items'][] = $p;
        }

        // Remove empty categories from output EXCEPT uncategorized if it has items
        foreach ($categories as $slug => $cat) {
            if (empty($cat['items']) && $slug !== 'uncategorized') {
                unset($categories[$slug]);
            }
        }

    } catch (Throwable $e) {
        error_log('shop_data_loader error: ' . $e->getMessage());
        $categories = [];
    }
}
