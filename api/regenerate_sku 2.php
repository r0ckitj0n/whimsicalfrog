<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    Response::json(['success' => true]);
}

Response::validateMethod('POST');
AuthHelper::requireAdmin(403, 'Admin access required');

/**
 * Build a next SKU from an existing prefix (e.g. WF-TS- -> WF-TS-002).
 */
function wf_generate_next_sku_for_prefix(string $prefix, int $digits = 3): string
{
    $escapedPrefix = str_replace(['%', '_'], ['\\%', '\\_'], $prefix);
    $rows = Database::queryAll(
        'SELECT sku FROM items WHERE sku LIKE ? ORDER BY sku DESC LIMIT 100',
        [$escapedPrefix . '%']
    );

    $max = 0;
    foreach ($rows as $row) {
        $candidate = (string) ($row['sku'] ?? '');
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\\d+)$/', $candidate, $m)) {
            $n = (int) $m[1];
            if ($n > $max) {
                $max = $n;
            }
        }
    }

    return $prefix . str_pad((string) ($max + 1), max(3, $digits), '0', STR_PAD_LEFT);
}

/**
 * Derive fallback category-based prefix if SKU does not follow prefix-number pattern.
 */
function wf_generate_category_sku(string $category): string
{
    $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 2));
    if (strlen($categoryCode) < 2) {
        $categoryCode = 'GN';
    }

    return wf_generate_next_sku_for_prefix('WF-' . $categoryCode . '-', 3);
}

try {
    $input = Response::getJsonInput() ?? [];

    $currentSku = trim((string) ($input['current_sku'] ?? ''));
    $requestedSku = trim((string) ($input['new_sku'] ?? ''));
    $requestedCategory = trim((string) ($input['category'] ?? ''));

    if ($currentSku === '') {
        Response::error('Current SKU is required', null, 400);
    }

    $item = Database::queryOne('SELECT sku, category FROM items WHERE sku = ? LIMIT 1', [$currentSku]);
    if (!$item) {
        Response::error('Item not found for current SKU', null, 404);
    }

    $resolvedCategory = $requestedCategory !== '' ? $requestedCategory : (string) ($item['category'] ?? '');

    $newSku = $requestedSku;
    if ($newSku === '') {
        if (preg_match('/^(.+-)(\d{3,})$/', $currentSku, $m)) {
            $prefix = (string) $m[1];
            $digits = strlen((string) $m[2]);
            $newSku = wf_generate_next_sku_for_prefix($prefix, $digits);
        } else {
            $newSku = wf_generate_category_sku($resolvedCategory);
        }
    }

    if ($newSku === $currentSku) {
        Response::error('Generated SKU matches current SKU. Try again.', null, 409);
    }

    $existing = Database::queryOne('SELECT sku FROM items WHERE sku = ? LIMIT 1', [$newSku]);
    if ($existing) {
        Response::error('Generated SKU already exists', ['new_sku' => $newSku], 409);
    }

    // Explicit operational allow-list. Historical analytics/log tables are intentionally excluded.
    $updateTargets = [
        ['table' => 'area_mappings', 'column' => 'item_sku'],
        ['table' => 'cost_factors', 'column' => 'sku'],
        ['table' => 'cost_suggestions', 'column' => 'sku'],
        ['table' => 'inventory_energies', 'column' => 'sku'],
        ['table' => 'inventory_equipments', 'column' => 'sku'],
        ['table' => 'inventory_labors', 'column' => 'sku'],
        ['table' => 'inventory_materials', 'column' => 'sku'],
        ['table' => 'item_color_assignments', 'column' => 'item_sku'],
        ['table' => 'item_colors', 'column' => 'item_sku'],
        ['table' => 'item_genders', 'column' => 'item_sku'],
        ['table' => 'item_images', 'column' => 'sku'],
        ['table' => 'item_marketing_preferences', 'column' => 'sku'],
        ['table' => 'item_option_settings', 'column' => 'item_sku'],
        ['table' => 'item_size_assignments', 'column' => 'item_sku'],
        ['table' => 'item_sizes', 'column' => 'item_sku'],
        ['table' => 'marketing_suggestions', 'column' => 'sku'],
        ['table' => 'order_items', 'column' => 'sku'],
        ['table' => 'price_factors', 'column' => 'sku'],
        ['table' => 'price_suggestions', 'column' => 'sku'],
        ['table' => 'items', 'column' => 'sku'],
    ];

    $excludedHistoricalTargets = [
        'inventory_logs.item_sku',
        'item_analytics.item_sku',
        'page_views.item_sku',
        'user_interactions.item_sku',
        'z_legacy_backup_cost_suggestions.sku',
        'z_legacy_backup_item_tiers.sku',
        'z_legacy_backup_price_suggestions.sku',
        'z_legacy_backup_pricing_suggestions.item_sku',
    ];

    $existingColumns = [];
    foreach (Database::queryAll('SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = DATABASE()') as $row) {
        $tableName = (string) ($row['table_name'] ?? $row['TABLE_NAME'] ?? '');
        $columnName = (string) ($row['column_name'] ?? $row['COLUMN_NAME'] ?? '');
        if ($tableName !== '' && $columnName !== '') {
            $existingColumns[$tableName . '.' . $columnName] = true;
        }
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $updatedCounts = [];
    $skippedTargets = [];

    foreach ($updateTargets as $target) {
        $qualified = $target['table'] . '.' . $target['column'];
        if (!isset($existingColumns[$qualified])) {
            $skippedTargets[] = $qualified;
            continue;
        }

        $sql = sprintf(
            'UPDATE `%s` SET `%s` = ? WHERE `%s` = ?',
            $target['table'],
            $target['column'],
            $target['column']
        );
        $affected = Database::execute($sql, [$newSku, $currentSku]);
        $updatedCounts[$qualified] = (int) $affected;
    }

    $pdo->commit();

    Response::json([
        'success' => true,
        'current_sku' => $currentSku,
        'new_sku' => $newSku,
        'updated_counts' => $updatedCounts,
        'skipped_targets' => $skippedTargets,
        'excluded_historical_targets' => $excludedHistoricalTargets,
        'message' => 'SKU regenerated and operational references updated'
    ]);
} catch (Throwable $e) {
    try {
        $pdo = Database::getInstance();
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable $rollbackError) {
        error_log('regenerate_sku rollback failed: ' . $rollbackError->getMessage());
    }

    Response::error('Failed to regenerate SKU: ' . $e->getMessage(), null, 500);
}
