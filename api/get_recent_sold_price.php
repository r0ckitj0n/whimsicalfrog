<?php
/**
 * Recent Sold Price API
 *
 * Returns a recent "real-world" price signal based on order history.
 * Used to avoid unnecessary AI calls when we have very fresh sales data.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';

AuthHelper::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed();
}

$sku = trim((string) ($_GET['sku'] ?? ''));
if ($sku === '') {
    Response::error('SKU parameter is required.', null, 400);
}

try {
    Database::getInstance();

    $row = Database::queryOne(
        "
        SELECT
            AVG(oi.unit_price) AS avg_price,
            MIN(oi.unit_price) AS min_price,
            MAX(oi.unit_price) AS max_price,
            COUNT(*) AS line_count,
            MAX(o.created_at) AS last_sold_at
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE oi.sku = ?
          AND oi.unit_price > 0
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ",
        [$sku]
    );

    $lineCount = (int) ($row['line_count'] ?? 0);
    if ($lineCount <= 0) {
        Response::json([
            'success' => false,
            'sku' => $sku,
            'avg_price' => null,
            'line_count' => 0,
            'last_sold_at' => null,
            'error' => 'No recent sold prices found for this SKU (last 7 days).'
        ]);
    }

    Response::json([
        'success' => true,
        'sku' => $sku,
        'avg_price' => isset($row['avg_price']) ? (float) $row['avg_price'] : null,
        'min_price' => isset($row['min_price']) ? (float) $row['min_price'] : null,
        'max_price' => isset($row['max_price']) ? (float) $row['max_price'] : null,
        'line_count' => $lineCount,
        'last_sold_at' => $row['last_sold_at'] ?? null
    ]);
} catch (Throwable $e) {
    error_log('get_recent_sold_price.php error: ' . $e->getMessage());
    Response::serverError('Internal server error', $e->getMessage());
}

