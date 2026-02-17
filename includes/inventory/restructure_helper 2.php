<?php
/**
 * Inventory Restructuring Helper Logic
 */

function analyze_item_structure($item_sku)
{
    $colors = Database::queryAll("SELECT id, color_name, color_code, stock_level, is_active FROM item_colors WHERE item_sku = ?", [$item_sku]);
    $sizes = Database::queryAll("SELECT id, color_id, size_name, size_code, stock_level, price_adjustment, is_active FROM item_sizes WHERE item_sku = ?", [$item_sku]);
    
    $isBackwards = false;
    if (count($colors) > count($sizes) && count($colors) > 8) $isBackwards = true;
    
    return [
        'is_backwards' => $isBackwards,
        'total_colors' => count($colors),
        'total_sizes' => count($sizes),
        'colors' => $colors,
        'sizes' => $sizes
    ];
}

function migrate_item_structure($item_sku, $newStructure)
{
    Database::beginTransaction();
    try {
        Database::execute("DELETE FROM item_sizes WHERE item_sku = ?", [$item_sku]);
        Database::execute("DELETE FROM item_colors WHERE item_sku = ?", [$item_sku]);

        $total_stock = 0;
        foreach ($newStructure as $sizeData) {
            foreach ($sizeData['colors'] ?? [] as $colorData) {
                $colorName = $colorData['color_name'];
                $colorCode = $colorData['color_code'] ?? '#000000';
                
                $row = Database::queryOne("SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ?", [$item_sku, $colorName]);
                if (!$row) {
                    Database::execute("INSERT INTO item_colors (item_sku, color_name, color_code, stock_level) VALUES (?, ?, ?, 0)", [$item_sku, $colorName, $colorCode]);
                    $color_id = Database::lastInsertId();
                } else {
                    $color_id = $row['id'];
                }

                Database::execute(
                    "INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment) VALUES (?, ?, ?, ?, ?, ?)",
                    [$item_sku, $color_id, $sizeData['size_name'], $sizeData['size_code'], $colorData['stock_level'] ?? 0, $sizeData['price_adjustment'] ?? 0]
                );
                $total_stock += (int)($colorData['stock_level'] ?? 0);
            }
        }

        Database::execute("UPDATE items SET stock_quantity = ? WHERE sku = ?", [$total_stock, $item_sku]);
        Database::commit();
        return ['success' => true, 'total_stock' => $total_stock];
    } catch (Exception $e) {
        Database::rollBack();
        throw $e;
    }
}
