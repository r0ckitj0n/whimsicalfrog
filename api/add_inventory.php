<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
header('Content-Type: application/json');
requireAdmin(true);

function wf_generate_sku_for_category(string $category): string
{
    $categoryCode = wf_resolve_sku_prefix_for_category($category);

    $lastSku = Database::queryOne(
        "SELECT sku FROM items WHERE sku LIKE ? ORDER BY sku DESC LIMIT 1",
        ["WF-{$categoryCode}-%"]
    );

    $nextNum = 1;
    if ($lastSku && !empty($lastSku['sku'])) {
        $parts = explode('-', (string)$lastSku['sku']);
        if (count($parts) >= 3 && is_numeric($parts[2])) {
            $nextNum = intval($parts[2]) + 1;
        }
    }

    $candidate = sprintf('WF-%s-%03d', $categoryCode, $nextNum);
    while (Database::queryOne("SELECT sku FROM items WHERE sku = ? LIMIT 1", [$candidate])) {
        $nextNum++;
        $candidate = sprintf('WF-%s-%03d', $categoryCode, $nextNum);
    }
    return $candidate;
}

function wf_items_has_column(string $column): bool
{
    static $columns = null;
    if (is_array($columns)) {
        return isset($columns[strtolower($column)]);
    }

    $columns = [];
    try {
        $rows = Database::queryAll('SHOW COLUMNS FROM items');
        foreach ($rows as $row) {
            $name = strtolower((string)($row['Field'] ?? ''));
            if ($name !== '') {
                $columns[$name] = true;
            }
        }
    } catch (Throwable $e) {
        return false;
    }

    return isset($columns[strtolower($column)]);
}

function wf_resolve_category_id(string $categoryName): ?int
{
    $name = trim($categoryName);
    if ($name === '') {
        return null;
    }

    try {
        $row = Database::queryOne(
            'SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1',
            [$name]
        );
        if ($row && isset($row['id'])) {
            return (int) $row['id'];
        }
    } catch (Throwable $e) {
        return null;
    }

    return null;
}

function wf_has_categories_column(string $column): bool
{
    static $columns = null;
    if (is_array($columns)) {
        return isset($columns[strtolower($column)]);
    }

    $columns = [];
    try {
        $rows = Database::queryAll('SHOW COLUMNS FROM categories');
        foreach ($rows as $row) {
            $name = strtolower((string)($row['Field'] ?? ''));
            if ($name !== '') {
                $columns[$name] = true;
            }
        }
    } catch (Throwable $e) {
        return false;
    }

    return isset($columns[strtolower($column)]);
}

function wf_default_sku_prefix(string $category): string
{
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 2));
    return strlen($prefix) >= 2 ? $prefix : 'GN';
}

function wf_normalize_sku_prefix(string $raw): string
{
    $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
    if ($clean === '') {
        return '';
    }
    return substr($clean, 0, 8);
}

function wf_resolve_sku_prefix_for_category(string $category): string
{
    $fallback = wf_default_sku_prefix($category);
    if (trim($category) === '' || !wf_has_categories_column('sku_rules')) {
        return $fallback;
    }

    try {
        $row = Database::queryOne(
            'SELECT sku_rules FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1',
            [$category]
        );
        $prefix = wf_normalize_sku_prefix((string)($row['sku_rules'] ?? ''));
        if (strlen($prefix) >= 2) {
            return $prefix;
        }
    } catch (Throwable $e) {
        // Fallback to legacy category-derived prefix.
    }

    return $fallback;
}

function wf_table_exists(string $tableName): bool
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $tableName)) {
        return false;
    }
    $row = Database::queryOne(
        "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
        [$tableName]
    );
    return ((int)($row['c'] ?? 0)) > 0;
}

function wf_migrate_temp_sku_records(string $sourceSku, string $targetSku): void
{
    if ($sourceSku === '' || $targetSku === '' || $sourceSku === $targetSku) {
        return;
    }

    $updates = [
        ['table' => 'item_images', 'column' => 'sku'],
        ['table' => 'marketing_suggestions', 'column' => 'sku'],
        ['table' => 'item_marketing_preferences', 'column' => 'sku'],
        ['table' => 'cost_factors', 'column' => 'sku'],
        ['table' => 'price_factors', 'column' => 'sku'],
        ['table' => 'cost_suggestions', 'column' => 'sku'],
        ['table' => 'price_suggestions', 'column' => 'sku'],
        ['table' => 'item_colors', 'column' => 'item_sku'],
        ['table' => 'item_sizes', 'column' => 'item_sku']
    ];

    foreach ($updates as $map) {
        $table = $map['table'];
        $column = $map['column'];
        if (!wf_table_exists($table)) {
            continue;
        }
        try {
            Database::execute(
                "UPDATE `$table` SET `$column` = ? WHERE `$column` = ?",
                [$targetSku, $sourceSku]
            );
        } catch (PDOException $e) {
            // Handle collision when destination SKU row already exists (e.g., previous failed create attempt).
            $isDuplicate = ((string) $e->getCode() === '23000')
                || (strpos((string) $e->getMessage(), '1062') !== false)
                || (stripos((string) $e->getMessage(), 'Duplicate entry') !== false);
            if (!$isDuplicate) {
                throw $e;
            }

            // Prefer latest temp-SKU data by removing conflicting destination row, then retry migration.
            Database::execute(
                "DELETE FROM `$table` WHERE `$column` = ?",
                [$targetSku]
            );
            Database::execute(
                "UPDATE `$table` SET `$column` = ? WHERE `$column` = ?",
                [$targetSku, $sourceSku]
            );
        }
    }

    if (wf_table_exists('items')) {
        Database::execute("DELETE FROM items WHERE sku = ?", [$sourceSku]);
    }

    // Normalize image metadata for target SKU after migration.
    if (wf_table_exists('item_images')) {
        $primaryRows = Database::queryAll(
            "SELECT id, image_path FROM item_images WHERE sku = ? AND is_primary = 1 ORDER BY sort_order ASC, id ASC",
            [$targetSku]
        );

        if (!empty($primaryRows)) {
            // Keep only the first primary as canonical.
            $keepPrimaryId = (int) ($primaryRows[0]['id'] ?? 0);
            if ($keepPrimaryId > 0) {
                Database::execute(
                    "UPDATE item_images SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE sku = ?",
                    [$keepPrimaryId, $targetSku]
                );

                if (wf_table_exists('items')) {
                    $primaryPath = (string) ($primaryRows[0]['image_path'] ?? '');
                    if ($primaryPath !== '') {
                        Database::execute("UPDATE items SET image_url = ? WHERE sku = ?", [$primaryPath, $targetSku]);
                    }
                }
            }
        } else {
            $firstRow = Database::queryOne(
                "SELECT id, image_path FROM item_images WHERE sku = ? ORDER BY sort_order ASC, id ASC LIMIT 1",
                [$targetSku]
            );
            if (!empty($firstRow['id'])) {
                $firstId = (int) $firstRow['id'];
                Database::execute("UPDATE item_images SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE sku = ?", [$firstId, $targetSku]);
                if (wf_table_exists('items')) {
                    $primaryPath = (string) ($firstRow['image_path'] ?? '');
                    if ($primaryPath !== '') {
                        Database::execute("UPDATE items SET image_url = ? WHERE sku = ?", [$primaryPath, $targetSku]);
                    }
                }
            }
        }
    }
}

try {
    // Get POST data
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        Response::error('Invalid JSON', null, 400);
    }

    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Handle different data formats - check if it's the new format with sku, name, etc.
    if (isset($data['sku']) && isset($data['name'])) {
        // New format from admin interface
        $skuInput = trim((string)$data['sku']);
        $sourceTempSku = trim((string)($data['source_temp_sku'] ?? ''));
        $name = trim((string)$data['name']);
        if ($name === '') {
            Response::error('Both sku and name are required', null, 422);
        }
        if (strlen($name) > 255) {
            Response::error('Name is too long', null, 422);
        }
        $category = $data['category'] ?? '';
        $resolvedCategory = trim((string)$category) !== '' ? trim((string)$category) : 'General';
        if (strlen($resolvedCategory) > 100) {
            Response::error('Category is too long', null, 422);
        }
        $stock_quantity = intval($data['stock_quantity'] ?? 0);
        $reorder_point = intval($data['reorder_point'] ?? 5);
        $cost_price = floatval($data['cost_price'] ?? 0);
        $retail_price = floatval($data['retail_price'] ?? 0);
        $description = trim((string)($data['description'] ?? ''));
        $status = $data['status'] ?? WF_Constants::ITEM_STATUS_DRAFT;
        $weight_oz = floatval($data['weight_oz'] ?? 0);
        $package_length_in = floatval($data['package_length_in'] ?? 0);
        $package_width_in = floatval($data['package_width_in'] ?? 0);
        $package_height_in = floatval($data['package_height_in'] ?? 0);
        $allowedStatuses = [
            WF_Constants::ITEM_STATUS_ACTIVE,
            WF_Constants::ITEM_STATUS_DRAFT,
            WF_Constants::ITEM_STATUS_ARCHIVED
        ];
        if (!in_array($status, $allowedStatuses, true)) {
            Response::error('Invalid status value', null, 422);
        }
        if (
            $stock_quantity < 0 || $reorder_point < 0 ||
            $cost_price < 0 || $retail_price < 0 ||
            $weight_oz < 0 || $package_length_in < 0 ||
            $package_width_in < 0 || $package_height_in < 0
        ) {
            Response::error('Numeric fields must be non-negative', null, 422);
        }
        if ($skuInput !== '' && stripos($skuInput, 'WF-TMP-') !== 0 && preg_match('/^[A-Za-z0-9-]{3,64}$/', $skuInput) !== 1) {
            Response::error('Invalid SKU format', null, 422);
        }
        if ($sourceTempSku !== '' && preg_match('/^[A-Za-z0-9-]{3,64}$/', $sourceTempSku) !== 1) {
            Response::error('Invalid source_temp_sku format', null, 422);
        }
        $resolvedCategoryId = wf_resolve_category_id($resolvedCategory);

        $isTemporarySku = ($skuInput === '') || (stripos($skuInput, 'WF-TMP-') === 0);
        $sku = $isTemporarySku ? wf_generate_sku_for_category($resolvedCategory) : $skuInput;

        $existing = Database::queryOne('SELECT sku FROM items WHERE sku = ? LIMIT 1', [$sku]);
        $alreadyExists = !empty($existing);

        // Upsert allows image-first flows where an item shell may already exist.
        if (wf_items_has_column('category_id')) {
            $affected = Database::execute(
                'INSERT INTO items (sku, name, category, category_id, stock_quantity, reorder_point, cost_price, retail_price, description, status, weight_oz, package_length_in, package_width_in, package_height_in)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    category = VALUES(category),
                    category_id = VALUES(category_id),
                    stock_quantity = VALUES(stock_quantity),
                    reorder_point = VALUES(reorder_point),
                    cost_price = VALUES(cost_price),
                    retail_price = VALUES(retail_price),
                    description = VALUES(description),
                    status = VALUES(status),
                    weight_oz = VALUES(weight_oz),
                    package_length_in = VALUES(package_length_in),
                    package_width_in = VALUES(package_width_in),
                    package_height_in = VALUES(package_height_in)',
                [$sku, $name, $resolvedCategory, $resolvedCategoryId, $stock_quantity, $reorder_point, $cost_price, $retail_price, $description, $status, $weight_oz, $package_length_in, $package_width_in, $package_height_in]
            );
        } else {
            $affected = Database::execute(
                'INSERT INTO items (sku, name, category, stock_quantity, reorder_point, cost_price, retail_price, description, status, weight_oz, package_length_in, package_width_in, package_height_in)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    category = VALUES(category),
                    stock_quantity = VALUES(stock_quantity),
                    reorder_point = VALUES(reorder_point),
                    cost_price = VALUES(cost_price),
                    retail_price = VALUES(retail_price),
                    description = VALUES(description),
                    status = VALUES(status),
                    weight_oz = VALUES(weight_oz),
                    package_length_in = VALUES(package_length_in),
                    package_width_in = VALUES(package_width_in),
                    package_height_in = VALUES(package_height_in)',
                [$sku, $name, $resolvedCategory, $stock_quantity, $reorder_point, $cost_price, $retail_price, $description, $status, $weight_oz, $package_length_in, $package_width_in, $package_height_in]
            );
        }

        if ($affected !== false) {
            if ($isTemporarySku && $skuInput !== '' && $skuInput !== $sku) {
                wf_migrate_temp_sku_records($skuInput, $sku);
            }
            if (
                !$isTemporarySku &&
                $sourceTempSku !== '' &&
                stripos($sourceTempSku, 'WF-TMP-') === 0 &&
                $sourceTempSku !== $sku
            ) {
                wf_migrate_temp_sku_records($sourceTempSku, $sku);
            }
            Response::success([
                'message' => $alreadyExists ? 'Item updated successfully' : 'Item added successfully',
                'id' => $sku,
                'sku' => $sku,
                'created' => !$alreadyExists,
                'updated' => $alreadyExists
            ]);
        } else {
            throw new Exception('Failed to add item');
        }
    } else {
        // Legacy format - convert for backwards compatibility
        $requiredFields = ['item_name'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                Response::error("Field '$field' is required", null, 400);
            }
        }

        // Extract data and map to database columns
        $name = $data['item_name'];
        $category = $data['category'] ?? '';
        $stock_quantity = intval($data['quantity'] ?? 0);
        $sku = $data['unit'] ?? 'WF-GEN-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $description = $data['notes'] ?? '';
        $reorder_point = min(floor($stock_quantity / 2), 5);
        $cost_price = floatval($data['costPerUnit'] ?? 0);
        $retail_price = $cost_price * 1.5; // Default markup
        $status = $data['status'] ?? WF_Constants::ITEM_STATUS_DRAFT;
        $allowedStatuses = [
            WF_Constants::ITEM_STATUS_ACTIVE,
            WF_Constants::ITEM_STATUS_DRAFT,
            WF_Constants::ITEM_STATUS_ARCHIVED
        ];
        if (!is_string($name) || trim($name) === '' || strlen(trim($name)) > 255) {
            Response::error("Field 'item_name' is invalid", null, 422);
        }
        if (!is_string($sku) || preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku) !== 1) {
            Response::error("Field 'unit' is invalid", null, 422);
        }
        if (!in_array($status, $allowedStatuses, true)) {
            Response::error('Invalid status value', null, 422);
        }
        if ($stock_quantity < 0 || $cost_price < 0 || $retail_price < 0) {
            Response::error('Numeric fields must be non-negative', null, 422);
        }

        // Insert using items table
        $affected = Database::execute('INSERT INTO items (sku, name, category, stock_quantity, reorder_point, cost_price, retail_price, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$sku, $name, $category, $stock_quantity, $reorder_point, $cost_price, $retail_price, $description, $status]);

        if ($affected !== false) {
            Response::success(['message' => 'Item added successfully', 'id' => $sku]);
        } else {
            throw new Exception('Failed to add item');
        }
    }

} catch (PDOException $e) {
    Response::serverError('Database operation failed', $e->getMessage());
} catch (Exception $e) {
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
