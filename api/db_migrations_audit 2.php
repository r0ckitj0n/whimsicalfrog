<?php
/**
 * DB Migrations / Schema Drift Audit (Admin Only)
 *
 * Reports missing tables/columns expected by the current codebase.
 * This is intentionally read-only. It does not attempt to mutate schema.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

AuthHelper::requireAdmin();

function wf_schema_manifest(): array
{
    // Keep this list small and high-signal: tables that routinely drift between dev/live.
    return [
        'materials' => [
            'id', 'material_name', 'description', 'sort_order', 'is_active', 'created_at', 'updated_at'
        ],
        'inventory_option_links' => [
            'id', 'option_type', 'option_id', 'applies_to_type', 'category_id', 'item_sku', 'created_at', 'updated_at'
        ],
        'item_option_cascade_settings' => [
            'id', 'applies_to_type', 'category_id', 'item_sku', 'cascade_order', 'enabled_dimensions', 'grouping_rules',
            'is_active', 'created_at', 'updated_at'
        ],
        'size_templates' => [
            'id', 'template_name', 'description', 'category', 'is_active', 'created_at', 'updated_at'
        ],
        'size_template_items' => [
            'id', 'template_id', 'size_name', 'size_code', 'price_adjustment', 'display_order', 'is_active', 'created_at', 'updated_at'
        ],
        'color_templates' => [
            'id', 'template_name', 'description', 'category', 'is_active', 'created_at', 'updated_at'
        ],
        'color_template_items' => [
            'id', 'template_id', 'color_name', 'color_code', 'image_path', 'display_order', 'is_active', 'created_at', 'updated_at'
        ],
        // Variant editor dependencies (these usually exist, but when they don't, admin breaks hard).
        'item_colors' => [
            'id', 'item_sku', 'color_name', 'color_code', 'image_path', 'stock_level', 'display_order', 'is_active', 'created_at', 'updated_at'
        ],
        'item_sizes' => [
            'id', 'item_sku', 'color_id', 'size_name', 'size_code', 'stock_level', 'price_adjustment', 'display_order', 'is_active'
        ],
    ];
}

function wf_table_exists(PDO $db, string $table): bool
{
    $row = Database::queryOne(
        "SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1",
        [$table]
    );
    return (bool) $row;
}

function wf_get_table_columns(PDO $db, string $table): array
{
    $rows = Database::queryAll(
        "SELECT column_name AS col
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?
         ORDER BY ordinal_position",
        [$table]
    ) ?: [];
    return array_values(array_filter(array_map(static fn($r) => (string)($r['col'] ?? ''), $rows)));
}

try {
    $dbConn = Database::getInstance();
    $manifest = wf_schema_manifest();

    $missingTables = [];
    $missingColumns = [];

    foreach ($manifest as $table => $expectedCols) {
        if (!wf_table_exists($dbConn, $table)) {
            $missingTables[] = $table;
            continue;
        }

        $actualCols = wf_get_table_columns($dbConn, $table);
        $actualSet = [];
        foreach ($actualCols as $c) $actualSet[strtolower($c)] = true;

        $miss = [];
        foreach ($expectedCols as $c) {
            if (!isset($actualSet[strtolower((string)$c)])) $miss[] = (string)$c;
        }
        if (count($miss) > 0) {
            $missingColumns[] = ['table' => $table, 'missing' => $miss];
        }
    }

    Response::success([
        'generated_at' => gmdate('c'),
        'db_name' => (string) Database::queryOne("SELECT DATABASE() AS db", [])['db'],
        'missing_tables' => $missingTables,
        'missing_columns' => $missingColumns,
        'expected_table_count' => count($manifest),
    ]);
} catch (Throwable $e) {
    Response::serverError('DB migrations audit error', $e->getMessage());
}

