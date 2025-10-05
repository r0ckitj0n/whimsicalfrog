<?php
/**
 * CLI Utility: Seed gender-specific size rows from unisex sizes
 * Location: scripts/dev/seed_gender_sizes.php
 *
 * Usage examples:
 *   php scripts/dev/seed_gender_sizes.php --sku=WF-TEE-001 --genders="Girls,Men" --mode=copy --dry-run
 *   php scripts/dev/seed_gender_sizes.php --sku=WF-TEE-001 --genders="Girls,Men" --mode=split --zero-unisex
 *   php scripts/dev/seed_gender_sizes.php --sku=WF-TEE-001 --genders="Women,Men" --overwrite
 *
 * Flags:
 *   --sku=SKU                    Required. Item SKU to process.
 *   --genders="A,B,C"           Required. Comma-separated gender names to seed (e.g., Girls,Men).
 *   --mode=copy|split           Optional. "copy" copies unisex stock to each gender; "split" divides evenly. Default: copy.
 *   --dry-run                   Optional. Compute and print actions without DB writes.
 *   --overwrite                 Optional. If a gender+size row exists, update its stock instead of skipping.
 *   --zero-unisex               Optional (with mode=split). Set original unisex size rows stock to 0 after split.
 */

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/functions.php';

function argval($key, $default = null)
{
    $long = "--{$key}=";
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, $long) === 0) return substr($arg, strlen($long));
        if ($arg === "--{$key}") return true; // boolean flags
    }
    return $default;
}

try {
    Database::getInstance();

    $sku = trim((string)argval('sku', ''));
    $gendersStr = (string)argval('genders', '');
    $mode = strtolower((string)argval('mode', 'copy'));
    $dryRun = (bool)argval('dry-run', false);
    $overwrite = (bool)argval('overwrite', false);
    $zeroUnisex = (bool)argval('zero-unisex', false);

    if ($sku === '' || $gendersStr === '') {
        throw new Exception("--sku and --genders are required. See file header for usage.");
    }

    $genders = array_values(array_filter(array_map('trim', explode(',', $gendersStr)), function ($g) { return $g !== ''; }));
    if (empty($genders)) throw new Exception('No valid genders provided.');
    if (!in_array($mode, ['copy', 'split'], true)) throw new Exception('--mode must be copy or split');

    // Validate item exists
    $item = Database::queryOne('SELECT sku, name FROM items WHERE sku = ?', [$sku]);
    if (!$item) throw new Exception("Item not found: {$sku}");

    // Fetch unisex sizes (gender IS NULL), active only
    $unisexSizes = Database::queryAll(
        'SELECT id, item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active
         FROM item_sizes WHERE item_sku = ? AND gender IS NULL AND is_active = 1 ORDER BY display_order, size_name',
        [$sku]
    );

    if (empty($unisexSizes)) {
        echo "No unisex sizes found for {$sku}. Nothing to seed.\n";
        exit(0);
    }

    $actions = [];
    $perGenderShares = [];

    foreach ($unisexSizes as $row) {
        $baseStock = (int)($row['stock_level'] ?? 0);
        $shares = [];
        if ($mode === 'split') {
            $n = count($genders);
            $each = (int)floor($baseStock / max(1, $n));
            $rem = $baseStock - ($each * $n);
            foreach ($genders as $idx => $g) { $shares[$g] = $each + ($idx === ($n - 1) ? $rem : 0); }
        } else {
            foreach ($genders as $g) { $shares[$g] = $baseStock; }
        }
        $perGenderShares[$row['id']] = $shares;

        foreach ($genders as $g) {
            // Does target row already exist?
            $exists = Database::queryOne(
                'SELECT id, stock_level FROM item_sizes WHERE item_sku = ? AND size_code = ? AND size_name = ? AND gender = ? AND ' .
                (is_null($row['color_id']) ? 'color_id IS NULL' : 'color_id = ?') . ' LIMIT 1',
                is_null($row['color_id'])
                    ? [$sku, $row['size_code'], $row['size_name'], $g]
                    : [$sku, $row['size_code'], $row['size_name'], $g, (int)$row['color_id']]
            );

            $targetStock = (int)$shares[$g];
            if ($exists) {
                if ($overwrite) {
                    $actions[] = ['type' => 'update', 'gender' => $g, 'size' => $row['size_code'], 'color_id' => $row['color_id'], 'to' => $targetStock, 'id' => $exists['id']];
                } else {
                    $actions[] = ['type' => 'skip', 'gender' => $g, 'size' => $row['size_code'], 'color_id' => $row['color_id'], 'existing_id' => $exists['id']];
                }
            } else {
                $actions[] = [
                    'type' => 'insert', 'gender' => $g, 'size' => $row['size_code'], 'color_id' => $row['color_id'],
                    'record' => [
                        'item_sku' => $sku,
                        'color_id' => $row['color_id'],
                        'size_name' => $row['size_name'],
                        'size_code' => $row['size_code'],
                        'stock_level' => $targetStock,
                        'price_adjustment' => $row['price_adjustment'],
                        'display_order' => $row['display_order'],
                        'gender' => $g,
                        'is_active' => 1,
                    ]
                ];
            }
        }

        if ($mode === 'split' && $zeroUnisex) {
            $actions[] = ['type' => 'zero_unisex', 'id' => $row['id']];
        }
    }

    // Present plan
    echo "Seeding genders for SKU {$sku} (mode={$mode}, dryRun=" . ($dryRun ? 'yes' : 'no') . ", overwrite=" . ($overwrite ? 'yes' : 'no') . ", zeroUnisex=" . ($zeroUnisex ? 'yes' : 'no') . ")\n";
    echo "Genders: " . implode(', ', $genders) . "\n";

    $insertCount = 0; $updateCount = 0; $skipCount = 0; $zeroCount = 0;

    if ($dryRun) {
        foreach ($actions as $a) {
            if ($a['type'] === 'insert') {
                echo "+ INSERT size {$a['record']['size_code']} ({$a['record']['size_name']}) gender={$a['record']['gender']} color_id=" . ($a['record']['color_id'] ?? 'NULL') . " stock={$a['record']['stock_level']}\n";
                $insertCount++;
            } elseif ($a['type'] === 'update') {
                echo "~ UPDATE id={$a['id']} => stock={$a['to']} (gender {$a['gender']}, size {$a['size']}, color_id=" . ($a['color_id'] ?? 'NULL') . ")\n";
                $updateCount++;
            } elseif ($a['type'] === 'skip') {
                echo "= SKIP existing (gender {$a['gender']}, size {$a['size']}, color_id=" . ($a['color_id'] ?? 'NULL') . ")\n";
                $skipCount++;
            } elseif ($a['type'] === 'zero_unisex') {
                echo "! ZERO unisex id={$a['id']}\n"; $zeroCount++;
            }
        }
        echo "Dry run complete. Inserts: {$insertCount}, Updates: {$updateCount}, Skips: {$skipCount}, Zeroed: {$zeroCount}\n";
        exit(0);
    }

    // Apply actions transactionally
    Database::beginTransaction();
    try {
        foreach ($actions as $a) {
            if ($a['type'] === 'insert') {
                $r = $a['record'];
                Database::execute(
                    'INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $r['item_sku'], $r['color_id'], $r['size_name'], $r['size_code'], (int)$r['stock_level'],
                        $r['price_adjustment'], $r['display_order'], (int)$r['is_active'], $r['gender']
                    ]
                );
                $insertCount++;
            } elseif ($a['type'] === 'update') {
                Database::execute('UPDATE item_sizes SET stock_level = ? WHERE id = ?', [(int)$a['to'], (int)$a['id']]);
                $updateCount++;
            } elseif ($a['type'] === 'zero_unisex') {
                Database::execute('UPDATE item_sizes SET stock_level = 0 WHERE id = ?', [(int)$a['id']]);
                $zeroCount++;
            }
        }

        // Sync color totals and item total stock
        // Update color stock from sizes
        Database::execute(
            'UPDATE item_colors c SET c.stock_level = (
                SELECT COALESCE(SUM(s.stock_level), 0)
                FROM item_sizes s
                WHERE s.item_sku = c.item_sku AND s.color_id = c.id AND s.is_active = 1
            ) WHERE c.item_sku = ?'
            , [$sku]
        );

        // Update item stock total
        Database::execute(
            'UPDATE items i SET i.stockLevel = (
                SELECT COALESCE(SUM(s.stock_level), 0)
                FROM item_sizes s
                WHERE s.item_sku = i.sku AND s.is_active = 1
            ) WHERE i.sku = ?'
            , [$sku]
        );

        Database::commit();
        echo "Applied. Inserts: {$insertCount}, Updates: {$updateCount}, Zeroed: {$zeroCount}, Skips: {$skipCount}\n";
        exit(0);
    } catch (Exception $e) {
        Database::rollBack();
        throw $e;
    }
} catch (Exception $e) {
    fwrite(STDERR, '[seed_gender_sizes] Error: ' . $e->getMessage() . "\n");
    exit(1);
}
