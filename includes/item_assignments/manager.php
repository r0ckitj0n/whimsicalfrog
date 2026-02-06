<?php
/**
 * Item Assignment Manager Logic
 */

function get_item_structure($item_sku)
{
    $item = Database::queryOne("SELECT sku, name, category FROM items WHERE sku = ?", [$item_sku]);
    if (!$item) throw new Exception('Item not found');

    $sizes = Database::queryAll(
        "SELECT isa.id as assignment_id, isa.global_size_id, gs.size_name, gs.size_code, gs.category
         FROM item_size_assignments isa
         JOIN global_sizes gs ON isa.global_size_id = gs.id
         WHERE isa.item_sku = ? AND isa.is_active = 1
         ORDER BY gs.display_order ASC, gs.size_name ASC",
        [$item_sku]
    );

    foreach ($sizes as &$size) {
        $size['colors'] = Database::queryAll(
            "SELECT ica.id as assignment_id, ica.global_color_id, ica.stock_level, ica.price_adjustment,
                    gc.color_name, gc.color_code, gc.category
             FROM item_color_assignments ica
             JOIN global_colors gc ON ica.global_color_id = gc.id
             WHERE ica.item_sku = ? AND ica.global_size_id = ? AND ica.is_active = 1
             ORDER BY gc.display_order ASC, gc.color_name ASC",
            [$item_sku, $size['global_size_id']]
        );
    }

    return ['item' => $item, 'structure' => $sizes];
}

function handle_assign_size($data)
{
    $sku = $data['item_sku'] ?? '';
    $sizeId = (int)($data['size_id'] ?? 0);
    if (empty($sku) || $sizeId <= 0) throw new Exception('SKU and size ID required');

    $exists = Database::queryOne("SELECT id FROM item_size_assignments WHERE item_sku = ? AND global_size_id = ?", [$sku, $sizeId]);
    if ($exists) throw new Exception('Already assigned');

    return Database::execute("INSERT INTO item_size_assignments (item_sku, global_size_id) VALUES (?, ?)", [$sku, $sizeId]);
}

function handle_remove_size($data)
{
    $sku = $data['item_sku'] ?? '';
    $sizeId = (int)($data['size_id'] ?? 0);
    if (empty($sku) || $sizeId <= 0) throw new Exception('SKU and size ID required');

    Database::beginTransaction();
    try {
        Database::execute("DELETE FROM item_color_assignments WHERE item_sku = ? AND global_size_id = ?", [$sku, $sizeId]);
        Database::execute("DELETE FROM item_size_assignments WHERE item_sku = ? AND global_size_id = ?", [$sku, $sizeId]);
        Database::commit();
    } catch (Exception $e) {
        Database::rollBack();
        throw $e;
    }
    return true;
}
