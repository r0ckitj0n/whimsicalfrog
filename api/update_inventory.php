<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

function ensureAiTierColumnsExistForInventoryUpdate(): void
{
    try {
        $cols = Database::queryAll("SHOW COLUMNS FROM items");
        $existing = [];
        foreach ($cols as $c) {
            if (!empty($c['Field'])) {
                $existing[$c['Field']] = true;
            }
        }
        if (!isset($existing['cost_quality_tier'])) {
            Database::execute("ALTER TABLE items ADD COLUMN cost_quality_tier VARCHAR(20) DEFAULT 'standard'");
        }
        if (!isset($existing['price_quality_tier'])) {
            Database::execute("ALTER TABLE items ADD COLUMN price_quality_tier VARCHAR(20) DEFAULT 'standard'");
        }
    } catch (Throwable $e) {
        error_log('Failed to ensure AI tier columns in update_inventory: ' . $e->getMessage());
    }
}

function hasCategoryIdColumnOnItems(): bool
{
    try {
        $cols = Database::queryAll("SHOW COLUMNS FROM items");
        foreach ($cols as $c) {
            if (($c['Field'] ?? '') === 'category_id') {
                return true;
            }
        }
    } catch (Throwable $e) {
        error_log('Failed checking category_id column in update_inventory: ' . $e->getMessage());
    }
    return false;
}

function findCategoryIdByName(string $categoryName): ?int
{
    try {
        $row = Database::queryOne("SELECT id FROM categories WHERE name = ? LIMIT 1", [$categoryName]);
        if (!$row || !isset($row['id'])) return null;
        return (int) $row['id'];
    } catch (Throwable $e) {
        error_log('Failed resolving category_id in update_inventory: ' . $e->getMessage());
        return null;
    }
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
requireAdmin(true);

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

    // Handle field updates
    if (isset($data['sku']) && isset($data['field']) && isset($data['value'])) {
        $sku = trim((string)$data['sku']);
        $field = $data['field'];
        $value = $data['value'];
        if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
            Response::error('Invalid SKU format', null, 422);
        }

        if ($field === 'cost_quality_tier' || $field === 'price_quality_tier') {
            ensureAiTierColumnsExistForInventoryUpdate();
        }

        // Validate field
        $allowedFields = ['name', 'category', 'stock_quantity', 'reorder_point', 'cost_price', 'retail_price', 'description', 'status', 'weight_oz', 'package_length_in', 'package_width_in', 'package_height_in', 'locked_fields', 'locked_words', 'quality_tier', 'cost_quality_tier', 'price_quality_tier'];
        if (!in_array($field, $allowedFields)) {
            Response::error('Invalid field', null, 400);
        }
        if ($field === 'status') {
            $allowedStatuses = [WF_Constants::ITEM_STATUS_ACTIVE, WF_Constants::ITEM_STATUS_DRAFT, WF_Constants::ITEM_STATUS_ARCHIVED];
            if (!in_array((string)$value, $allowedStatuses, true)) {
                Response::error('Invalid status value', null, 422);
            }
        }
        if (in_array($field, ['stock_quantity', 'reorder_point', 'cost_price', 'retail_price', 'weight_oz', 'package_length_in', 'package_width_in', 'package_height_in'], true)) {
            if (!is_numeric($value) || (float)$value < 0) {
                Response::error('Numeric value must be non-negative', null, 422);
            }
        }

        // Keep category text + category_id aligned when schema supports category_id.
        if ($field === 'category' && hasCategoryIdColumnOnItems()) {
            $categoryValue = is_string($value) ? trim($value) : (string) $value;
            $categoryId = $categoryValue !== '' ? findCategoryIdByName($categoryValue) : null;
            $affected = Database::execute(
                "UPDATE items SET `category` = ?, `category_id` = ? WHERE sku = ?",
                [$categoryValue, $categoryId, $sku]
            );
        } else {
            // Update the field
            $affected = Database::execute("UPDATE items SET `$field` = ? WHERE sku = ?", [$value, $sku]);
        }

        if ($affected > 0) {
            Response::updated();
        } else {
            // No rows affected: either no change needed or item missing
            $exists = Database::queryOne("SELECT sku FROM items WHERE sku = ?", [$sku]);
            if ($exists) {
                Response::noChanges();
            } else {
                Response::notFound('Item not found');
            }
        }
    } else {
        // Handle full item updates
        $requiredFields = ['sku', 'name'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                Response::error(ucfirst($field) . ' is required', null, 400);
            }
        }

        $sku = trim((string)$data['sku']);
        $name = trim((string)$data['name']);
        $category = trim((string)($data['category'] ?? ''));
        $stock_quantity = intval($data['stock_quantity'] ?? 0);
        $reorder_point = intval($data['reorder_point'] ?? 5);
        $cost_price = floatval($data['cost_price'] ?? 0);
        $retail_price = floatval($data['retail_price'] ?? 0);
        $description = trim((string)($data['description'] ?? ''));
        $status = trim((string)($data['status'] ?? WF_Constants::ITEM_STATUS_DRAFT));
        $weight_oz = floatval($data['weight_oz'] ?? 0);
        $package_length_in = floatval($data['package_length_in'] ?? 0);
        $package_width_in = floatval($data['package_width_in'] ?? 0);
        $package_height_in = floatval($data['package_height_in'] ?? 0);
        $locked_fields = isset($data['locked_fields']) ? json_encode($data['locked_fields']) : null;
        $locked_words = isset($data['locked_words']) ? json_encode($data['locked_words']) : null;
        if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
            Response::error('Invalid SKU format', null, 422);
        }
        if ($name === '' || strlen($name) > 255) {
            Response::error('Invalid item name', null, 422);
        }
        if (strlen($category) > 100 || strlen($description) > 4000) {
            Response::error('Category or description too long', null, 422);
        }
        $allowedStatuses = [WF_Constants::ITEM_STATUS_ACTIVE, WF_Constants::ITEM_STATUS_DRAFT, WF_Constants::ITEM_STATUS_ARCHIVED];
        if (!in_array($status, $allowedStatuses, true)) {
            Response::error('Invalid status value', null, 422);
        }
        if (
            $stock_quantity < 0 || $reorder_point < 0 || $cost_price < 0 || $retail_price < 0 ||
            $weight_oz < 0 || $package_length_in < 0 || $package_width_in < 0 || $package_height_in < 0
        ) {
            Response::error('Numeric fields must be non-negative', null, 422);
        }

        // Update the item (full update)
        if (hasCategoryIdColumnOnItems()) {
            $categoryId = $category !== '' ? findCategoryIdByName((string) $category) : null;
            $affected = Database::execute(
                'UPDATE items SET name = ?, category = ?, category_id = ?, stock_quantity = ?, reorder_point = ?, cost_price = ?, retail_price = ?, description = ?, status = ?, weight_oz = ?, package_length_in = ?, package_width_in = ?, package_height_in = ?, locked_fields = ?, locked_words = ? WHERE sku = ?',
                [$name, $category, $categoryId, $stock_quantity, $reorder_point, $cost_price, $retail_price, $description, $status, $weight_oz, $package_length_in, $package_width_in, $package_height_in, $locked_fields, $locked_words, $sku]
            );
        } else {
            $affected = Database::execute('UPDATE items SET name = ?, category = ?, stock_quantity = ?, reorder_point = ?, cost_price = ?, retail_price = ?, description = ?, status = ?, weight_oz = ?, package_length_in = ?, package_width_in = ?, package_height_in = ?, locked_fields = ?, locked_words = ? WHERE sku = ?', [$name, $category, $stock_quantity, $reorder_point, $cost_price, $retail_price, $description, $status, $weight_oz, $package_length_in, $package_width_in, $package_height_in, $locked_fields, $locked_words, $sku]);
        }

        if ($affected > 0) {
            Response::updated();
        } else {
            // No-op update or item missing
            $exists = Database::queryOne('SELECT sku FROM items WHERE sku = ?', [$sku]);
            if ($exists) {
                Response::noChanges();
            } else {
                Response::notFound('Item not found');
            }
        }
    }

} catch (PDOException $e) {
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
