<?php
/**
 * Item Sizes - Stock/Variant Maintenance Tools
 *
 * Provides admin-only maintenance operations used by the Inventory Variant Editor:
 * - sync_stock: recompute total stock from sizes (and colors) into items.stock_quantity
 * - distribute_general_stock_evenly: normalize size stock distribution within each (gender,color) group
 * - ensure_color_sizes: ensure each active color has a full set of size rows (cloned from general sizes)
 */

require_once __DIR__ . '/../stock_manager.php';

function wf_item_sizes_sync_stock(PDO $pdo, string $sku): int
{
    $newTotal = syncTotalStockWithSizes($pdo, $sku);
    if ($newTotal === false) {
        throw new Exception('Failed to sync stock');
    }
    return (int) $newTotal;
}

function wf_item_sizes_distribute_evenly(PDO $pdo, string $sku): int
{
    // Only touch active sizes; keep the total stock the same, just redistribute evenly per group.
    $groups = Database::queryAll(
        "SELECT COALESCE(gender, '') AS g, color_id
         FROM item_sizes
         WHERE item_sku = ? AND is_active = 1
         GROUP BY COALESCE(gender, ''), color_id",
        [$sku]
    ) ?: [];

    Database::beginTransaction();
    try {
        $upd = $pdo->prepare("UPDATE item_sizes SET stock_level = ? WHERE id = ?");

        foreach ($groups as $grp) {
            $gender = (string)($grp['g'] ?? '');
            $colorId = $grp['color_id'] ?? null;

            $rows = Database::queryAll(
                "SELECT id, stock_level
                 FROM item_sizes
                 WHERE item_sku = ?
                   AND is_active = 1
                   AND COALESCE(gender, '') = ?
                   AND " . ($colorId === null ? "color_id IS NULL" : "color_id = ?") . "
                 ORDER BY display_order ASC, id ASC",
                $colorId === null ? [$sku, $gender] : [$sku, $gender, (int)$colorId]
            ) ?: [];

            $count = count($rows);
            if ($count <= 1) continue;

            $total = 0;
            foreach ($rows as $r) $total += (int)($r['stock_level'] ?? 0);

            $base = intdiv($total, $count);
            $rem = $total % $count;

            foreach ($rows as $idx => $r) {
                $new = $base + ($idx < $rem ? 1 : 0);
                $upd->execute([$new, (int)$r['id']]);
            }

            if ($colorId !== null) {
                syncColorStockWithSizes($pdo, (int)$colorId);
            }
        }

        $newTotal = syncTotalStockWithSizes($pdo, $sku);
        if ($newTotal === false) throw new Exception('Failed to sync total stock after distribution');

        Database::commit();
        return (int) $newTotal;
    } catch (Throwable $e) {
        Database::rollBack();
        throw $e;
    }
}

function wf_item_sizes_ensure_color_sizes(PDO $pdo, string $sku): array
{
    // Clone "general" sizes (color_id IS NULL) onto each active color where missing.
    $general = Database::queryAll(
        "SELECT size_name, size_code, price_adjustment, display_order, COALESCE(gender,'') AS g
         FROM item_sizes
         WHERE item_sku = ? AND color_id IS NULL AND is_active = 1
         ORDER BY display_order ASC, id ASC",
        [$sku]
    ) ?: [];

    if (count($general) === 0) {
        return [
            'inserted' => 0,
            'colors_touched' => 0,
            'message' => 'No general sizes found to clone (create sizes first, then re-run).'
        ];
    }

    $colors = Database::queryAll(
        "SELECT id
         FROM item_colors
         WHERE item_sku = ? AND is_active = 1
         ORDER BY display_order ASC, id ASC",
        [$sku]
    ) ?: [];

    if (count($colors) === 0) {
        return [
            'inserted' => 0,
            'colors_touched' => 0,
            'message' => 'No active colors found for this SKU.'
        ];
    }

    // Determine whether the schema has a gender column; older environments might not.
    $hasGender = false;
    try {
        $cols = Database::queryAll("SHOW COLUMNS FROM item_sizes", []) ?: [];
        foreach ($cols as $c) {
            if (strtolower((string)($c['Field'] ?? '')) === 'gender') {
                $hasGender = true;
                break;
            }
        }
    } catch (Throwable $____) {
        $hasGender = true; // safe default for modern schema
    }

    Database::beginTransaction();
    try {
        $inserted = 0;
        $colorsTouched = 0;

        // Build insert SQL with optional gender column.
        $sql = $hasGender
            ? "INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender)
               VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)"
            : "INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active)
               VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

        $stmt = $pdo->prepare($sql);

        foreach ($colors as $c) {
            $colorId = (int)($c['id'] ?? 0);
            if ($colorId <= 0) continue;

            $existingRows = Database::queryAll(
                "SELECT size_code, COALESCE(gender,'') AS g
                 FROM item_sizes
                 WHERE item_sku = ? AND color_id = ?",
                [$sku, $colorId]
            ) ?: [];

            $existing = [];
            foreach ($existingRows as $r) {
                $key = (string)($r['size_code'] ?? '') . '||' . (string)($r['g'] ?? '');
                $existing[$key] = true;
            }

            $touchedThisColor = false;
            foreach ($general as $g) {
                $sizeCode = (string)($g['size_code'] ?? '');
                $genderKey = (string)($g['g'] ?? '');
                $key = $sizeCode . '||' . $genderKey;
                if ($sizeCode === '' || isset($existing[$key])) continue;

                $sizeName = (string)($g['size_name'] ?? '');
                $priceAdj = (float)($g['price_adjustment'] ?? 0);
                $disp = (int)($g['display_order'] ?? 0);

                if ($hasGender) {
                    $stmt->execute([$sku, $colorId, $sizeName, $sizeCode, 0, $priceAdj, $disp, $genderKey !== '' ? $genderKey : null]);
                } else {
                    $stmt->execute([$sku, $colorId, $sizeName, $sizeCode, 0, $priceAdj, $disp]);
                }

                $inserted++;
                $touchedThisColor = true;
            }

            if ($touchedThisColor) {
                $colorsTouched++;
                syncColorStockWithSizes($pdo, $colorId);
            }
        }

        $newTotal = syncTotalStockWithSizes($pdo, $sku);
        if ($newTotal === false) throw new Exception('Failed to sync total stock after ensuring sizes');

        Database::commit();
        return [
            'inserted' => $inserted,
            'colors_touched' => $colorsTouched,
            'new_total_stock' => (int)$newTotal
        ];
    } catch (Throwable $e) {
        Database::rollBack();
        throw $e;
    }
}

