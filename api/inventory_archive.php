<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

AuthHelper::requireAdmin(403, 'Admin access required');
header('Content-Type: application/json');

try {
    $pdo = Database::getInstance();

    $summary = Database::queryOne(
        "SELECT 
            COUNT(*) AS total_archived,
            SUM(CASE WHEN archived_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS archived_last_7,
            SUM(CASE WHEN archived_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS archived_last_30,
            SUM(CASE WHEN archived_at < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS archived_over_90,
            AVG(TIMESTAMPDIFF(DAY, archived_at, NOW())) AS avg_days_archived,
            SUM(stock_quantity) AS total_stock
        FROM items 
        WHERE is_archived = 1"
    ) ?: [];

    $categoryBreakdown = Database::queryAll(
        "SELECT COALESCE(NULLIF(category, ''), 'Uncategorized') AS category, COUNT(*) AS item_count
         FROM items
         WHERE is_archived = 1
         GROUP BY category
         ORDER BY item_count DESC, category ASC"
    ) ?: [];

    $items = Database::queryAll(
        "SELECT sku, name, category, stock_quantity, cost_price, retail_price, archived_at, archived_by,
                TIMESTAMPDIFF(DAY, archived_at, NOW()) AS days_archived
         FROM items
         WHERE is_archived = 1
         ORDER BY archived_at DESC
         LIMIT 250"
    ) ?: [];

    // --- PROACTIVE AUDIT QUERIES ---

    // Items missing images
    $missingImages = Database::queryAll(
        "SELECT sku, name, category FROM items WHERE is_archived = 0 AND (image_url IS NULL OR image_url = '') LIMIT 100"
    ) ?: [];

    // Pricing Alerts (Cost > Retail)
    $pricingAlerts = Database::queryAll(
        "SELECT sku, name, category, cost_price, retail_price FROM items WHERE is_archived = 0 AND cost_price > retail_price AND cost_price > 0"
    ) ?: [];

    // Stock Issues (Zero or Negative)
    $stockIssues = Database::queryAll(
        "SELECT sku, name, category, stock_quantity FROM items WHERE is_archived = 0 AND stock_quantity <= 0 LIMIT 100"
    ) ?: [];

    // Missing Content (No description or category)
    $contentIssues = Database::queryAll(
        "SELECT sku, name, category FROM items WHERE is_archived = 0 AND (description IS NULL OR description = '' OR category IS NULL OR category = '') LIMIT 100"
    ) ?: [];

    $payload = [
        'success' => true,
        'metrics' => [
            'total_archived' => (int) ($summary['total_archived'] ?? 0),
            'archived_last_7' => (int) ($summary['archived_last_7'] ?? 0),
            'archived_last_30' => (int) ($summary['archived_last_30'] ?? 0),
            'archived_over_90' => (int) ($summary['archived_over_90'] ?? 0),
            'avg_days_archived' => isset($summary['avg_days_archived']) ? round((float) $summary['avg_days_archived'], 1) : null,
            'total_stock' => (int) ($summary['total_stock'] ?? 0),
            // Proactive metrics
            'missing_images_count' => count($missingImages),
            'pricing_alerts_count' => count($pricingAlerts),
            'stock_issues_count' => count($stockIssues),
            'content_issues_count' => count($contentIssues)
        ],
        'categories' => array_map(static function ($row) {
            return [
                'category' => $row['category'] ?? 'Uncategorized',
                'count' => (int) ($row['item_count'] ?? 0)
            ];
        }, $categoryBreakdown),
        'items' => array_map(static function ($row) {
            return [
                'sku' => $row['sku'] ?? '',
                'name' => $row['name'] ?? '',
                'category' => $row['category'] ?? '',
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'cost_price' => isset($row['cost_price']) ? (float) $row['cost_price'] : null,
                'retail_price' => isset($row['retail_price']) ? (float) $row['retail_price'] : null,
                'archived_at' => $row['archived_at'] ?? null,
                'archived_by' => $row['archived_by'] ?? null,
                'days_archived' => isset($row['days_archived']) ? (int) $row['days_archived'] : null
            ];
        }, $items),
        'audit' => [
            'missing_images' => $missingImages,
            'pricing_alerts' => $pricingAlerts,
            'stock_issues' => $stockIssues,
            'content_issues' => $contentIssues
        ],
        'generated_at' => gmdate('c')
    ];

    echo json_encode($payload);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error', 'details' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unexpected error', 'details' => $e->getMessage()]);
}
