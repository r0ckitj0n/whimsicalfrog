<?php

// Search items API with fuzzy matching
// Include database configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';

/**
 * Generate fuzzy search variations for a search term
 */
function generateSearchVariations($searchTerm)
{
    $variations = [];
    $term = strtolower(trim($searchTerm));

    // Add original term
    $variations[] = $term;

    // Common word substitutions for clothing items
    $substitutions = [
        'tshirt' => ['t-shirt', 't shirt', 'tee', 'shirt'],
        't-shirt' => ['tshirt', 't shirt', 'tee', 'shirt'],
        'tee' => ['t-shirt', 'tshirt', 't shirt', 'shirt'],
        'tank' => ['tank top', 'tanktop'],
        'tanktop' => ['tank top', 'tank'],
        'hoodie' => ['hooded', 'hood', 'sweatshirt'],
        'sweatshirt' => ['hoodie', 'sweater'],
        'sweater' => ['sweatshirt', 'pullover'],
        'polo' => ['polo shirt'],
        'cap' => ['hat', 'baseball cap'],
        'hat' => ['cap', 'beanie'],
        'beanie' => ['hat', 'cap'],
        'jacket' => ['coat'],
        'coat' => ['jacket'],
        'pants' => ['trousers'],
        'trousers' => ['pants'],
        'shorts' => ['short'],
        'dress' => ['gown'],
        'skirt' => ['mini skirt'],
        'jeans' => ['denim'],
        'denim' => ['jeans'],
        'tumbler' => ['cup', 'mug', 'bottle'],
        'cup' => ['tumbler', 'mug'],
        'mug' => ['tumbler', 'cup'],
        'art' => ['artwork', 'print'],
        'artwork' => ['art', 'print'],
        'sub' => ['sublimation'],
        'sublimation' => ['sub'],
        'window' => ['window wrap', 'wrap'],
        'wrap' => ['window wrap', 'window']
    ];

    // Add substitutions if they exist
    if (isset($substitutions[$term])) {
        $variations = array_merge($variations, $substitutions[$term]);
    }

    // Add variations with spaces and hyphens
    if (strpos($term, ' ') === false && strpos($term, '-') === false) {
        // Add spaced version for compound words
        $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $term);
        if ($spaced !== $term) {
            $variations[] = strtolower($spaced);
        }

        // Add hyphenated version
        $hyphenated = str_replace(' ', '-', $spaced);
        if ($hyphenated !== $term && $hyphenated !== $spaced) {
            $variations[] = strtolower($hyphenated);
        }
    }

    // Add version without spaces/hyphens
    $compact = str_replace([' ', '-'], '', $term);
    if ($compact !== $term) {
        $variations[] = $compact;
    }

    // Add partial matches (for terms longer than 3 characters)
    if (strlen($term) > 3) {
        // Add beginning of word matches
        $variations[] = substr($term, 0, -1);
        if (strlen($term) > 4) {
            $variations[] = substr($term, 0, -2);
        }
    }

    // Remove duplicates and empty values
    $variations = array_unique(array_filter($variations));

    return $variations;
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Get search term from query parameter
    $searchTerm = $_GET['q'] ?? '';
    $searchTerm = trim($searchTerm);

    if (empty($searchTerm)) {
        Response::error('Search term is required', ['results' => []], 400);
    }

    // Generate fuzzy search variations
    $variations = generateSearchVariations($searchTerm);

    // Build a simple OR-based search using LIKE for each variation
    $searchConditions = [];
    $params = [];
    $paramIndex = 0;

    foreach ($variations as $variation) {
        // Create unique parameter names for each field and variation
        $nameParam = "name_" . $paramIndex;
        $descParam = "desc_" . $paramIndex;
        $catParam = "cat_" . $paramIndex;
        $skuParam = "sku_" . $paramIndex;

        $searchPattern = '%' . $variation . '%';

        $searchConditions[] = "(i.name LIKE :{$nameParam} OR i.description LIKE :{$descParam} OR i.category LIKE :{$catParam} OR i.sku LIKE :{$skuParam})";

        $params[$nameParam] = $searchPattern;
        $params[$descParam] = $searchPattern;
        $params[$catParam] = $searchPattern;
        $params[$skuParam] = $searchPattern;

        $paramIndex++;
    }

    $whereClause = implode(' OR ', $searchConditions);

    // Build the SQL query with simplified approach
    $sql = "
        SELECT 
            i.sku,
            i.name,
            i.description,
            i.category,
            i.retail_price,
            i.stock_quantity,
            img.image_path
        FROM items i
        LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
        WHERE {$whereClause}
        ORDER BY i.name ASC
        LIMIT 20
    ";

    // Convert param keys to include leading colons for Database helper
    $binds = [];
    foreach ($params as $param => $value) {
        $binds[':' . $param] = $value;
    }
    $results = Database::queryAll($sql, $binds);

    // Process results to include image URLs
    foreach ($results as &$item) {
        if ($item['image_path']) {
            // Check if path already includes 'images/items/'
            if (strpos($item['image_path'], 'images/items/') === 0) {
                $item['image_url'] = '/' . $item['image_path'];
            } else {
                $item['image_url'] = '/images/items/' . $item['image_path'];
            }
        } else {
            $item['image_url'] = '/images/items/placeholder.webp';
        }

        // Format price
        $item['formatted_price'] = '$' . number_format($item['retail_price'], 2);
        $item['price'] = $item['retail_price']; // Keep numeric price for cart

        // Stock status
        // Aggregate variant stock if present; fall back to item stock_quantity if NO variants exist
        $variantStats = Database::queryOne(
            "SELECT COUNT(*) as v_count, COALESCE(SUM(CASE WHEN is_active = 1 THEN stock_level ELSE 0 END), 0) AS total_stock 
             FROM item_sizes 
             WHERE item_sku = ?",
            [$item['sku']]
        );
        $total_stock = 0;
        if ($variantStats && ((int) $variantStats['v_count']) > 0) {
            $total_stock = (int) $variantStats['total_stock'];
        } else {
            $total_stock = (int) $item['stock_quantity'];
        }
        $item['total_stock'] = $total_stock;
        $item['stock_level'] = $total_stock; // alias for JS consumers
        $item['in_stock'] = $total_stock > 0;
        $item['stock_status'] = $total_stock > 0 ? WF_Constants::STOCK_STATUS_IN_STOCK : WF_Constants::STOCK_STATUS_OUT_OF_STOCK;

        // Flag if item has options (sizes/colors) to help upsell flow route to modal
        $hasOptionsRow = Database::queryOne(
            "SELECT 1 FROM item_sizes WHERE item_sku = ? AND is_active = 1 LIMIT 1",
            [$item['sku']]
        );
        $item['has_options'] = $hasOptionsRow ? true : false;

        // Remove relevance score from final output
        unset($item['relevance_score']);
    }

    Response::success([
        'search_term' => $searchTerm,
        'variations_used' => $variations,
        'results' => $results,
        'count' => count($results)
    ]);

} catch (PDOException $e) {
    error_log("Search API Error: " . $e->getMessage());
    Response::serverError('Database error occurred', ['results' => []]);
} catch (Exception $e) {
    error_log("Search API Error: " . $e->getMessage());
    Response::serverError('An error occurred while searching', ['results' => []]);
}
