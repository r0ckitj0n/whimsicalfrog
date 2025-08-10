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
         * ----------------------------------------------------------------*/
        $catStmt = $pdo->query(
            "SELECT id, slug, name, display_order
             FROM categories
             ORDER BY display_order ASC"
        );
        $catRows = $catStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($catRows as $row) {
            $slug = $row['slug'];
            $categories[$slug] = [
                'label'    => $row['name'],
                'products' => [],
            ];
        }

        /* ------------------------------------------------------------------
         * 2) Fetch products and attach to their category bucket
         *    Assumes items.category_id is populated (migration step)
         * ----------------------------------------------------------------*/
        $prodStmt = $pdo->query(
            "SELECT i.sku,
                    i.name               AS productName,
                    COALESCE(i.retailPrice, 0) AS price,
                    COALESCE(i.stockLevel, 0) AS stock,
                    i.description,
                    i.imageUrl,
                    c.slug
             FROM items i
             JOIN categories c ON i.category_id = c.id
             "
        );
        $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $p) {
            $slug = $p['slug'];
            unset($p['slug']); // not needed for template
            if (!isset($categories[$slug])) {
                // Fallback bucket for any mismatched data
                $categories[$slug] = [
                    'label'    => ucfirst($slug),
                    'products' => []
                ];
            }
            $categories[$slug]['products'][] = $p;
        }

    } catch (Throwable $e) {
        error_log('shop_data_loader error: ' . $e->getMessage());
        $categories = [];
    }
}
?>
