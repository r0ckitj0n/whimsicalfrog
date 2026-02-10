<?php
/**
 * Database Tables Manager Logic
 */

function wf_is_valid_sql_identifier($identifier)
{
    return is_string($identifier) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) === 1;
}

function wf_get_table_columns($tableName)
{
    if (!wf_is_valid_sql_identifier($tableName)) {
        throw new Exception('Invalid table name');
    }
    $columns = Database::queryAll("SHOW COLUMNS FROM `" . $tableName . "`");
    return array_column($columns, 'Field');
}

function getTableDocumentation()
{
    return [
        'items' => [
            'description' => 'Main inventory items table',
            'fields' => [
                'sku' => 'Primary key',
                'name' => 'Display name',
                'description' => 'Detailed description',
                'category' => 'Item category',
                'cost_price' => 'Production cost',
                'retail_price' => 'Selling price',
                'stock_quantity' => 'Inventory quantity',
                'reorder_point' => 'Reorder alert level'
            ]
        ],
        'item_images' => [
            'description' => 'Inventory item images',
            'fields' => ['item_sku' => 'FK to items.sku', 'image_path' => 'File path', 'is_primary' => 'Main image flag']
        ],
        'orders' => [
            'description' => 'Customer orders',
            'fields' => ['order_id' => 'PK', 'total_amount' => 'Order value', 'status' => 'Order status']
        ]
    ];
}

function handle_update_cell($input)
{
    $tableName = $input['table'] ?? '';
    $column = $input['column'] ?? '';
    $newValue = $input['new_value'] ?? '';
    $rowData = $input['row_data'] ?? [];

    if (empty($tableName) || empty($column) || empty($rowData)) {
        throw new Exception('Missing parameters');
    }
    if (!wf_is_valid_sql_identifier($tableName) || !wf_is_valid_sql_identifier($column)) {
        throw new Exception('Invalid table or column');
    }
    if (!is_array($rowData) || count($rowData) === 0) {
        throw new Exception('Invalid row_data');
    }

    $tableColumns = wf_get_table_columns($tableName);
    if (!in_array($column, $tableColumns, true)) {
        throw new Exception('Unknown column');
    }

    $where_conditions = [];
    $whereParams = [];
    foreach ($rowData as $col => $val) {
        if (!wf_is_valid_sql_identifier($col)) {
            throw new Exception('Invalid row identifier column');
        }
        if (!in_array($col, $tableColumns, true)) {
            throw new Exception('Unknown row identifier column');
        }
        if ($val === null || $val === '') {
            $where_conditions[] = "(`$col` IS NULL OR `$col` = '')";
        } else {
            $where_conditions[] = "`$col` = ?";
            $whereParams[] = $val;
        }
    }

    // Special handling: when updating items.category, also update category_id FK when schema supports it.
    if ($tableName === 'items' && $column === 'category') {
        $hasCategoryId = false;
        try {
            $columns = Database::queryAll("SHOW COLUMNS FROM `items`");
            foreach ($columns as $columnInfo) {
                if (($columnInfo['Field'] ?? '') === 'category_id') {
                    $hasCategoryId = true;
                    break;
                }
            }
        } catch (Throwable $e) {
            error_log('handle_update_cell category_id schema check failed: ' . $e->getMessage());
        }

        if ($hasCategoryId) {
            // Look up category_id from the category name
            $categoryRow = Database::queryOne("SELECT id FROM categories WHERE name = ?", [$newValue]);
            if ($categoryRow && isset($categoryRow['id'])) {
                $sql = "UPDATE `$tableName` SET `category_id` = ?, `category` = ? WHERE " . implode(' AND ', $where_conditions) . " LIMIT 1";
                $params = array_merge([$categoryRow['id'], $newValue], $whereParams);
                return Database::execute($sql, $params);
            }

            // If no category match, keep category text and clear FK to avoid stale category references.
            $sql = "UPDATE `$tableName` SET `category_id` = NULL, `category` = ? WHERE " . implode(' AND ', $where_conditions) . " LIMIT 1";
            $params = array_merge([$newValue], $whereParams);
            return Database::execute($sql, $params);
        }
        // No category_id column: update legacy text field only.
    }

    $sql = "UPDATE `$tableName` SET `$column` = ? WHERE " . implode(' AND ', $where_conditions) . " LIMIT 1";
    $params = array_merge([$newValue], $whereParams);
    return Database::execute($sql, $params);
}
