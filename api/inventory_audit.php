<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/stock_manager.php';

AuthHelper::requireAdmin();

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

try {
    Database::getInstance();

    if ($action === 'export') {
        $filename = 'inventory-audit-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $out = fopen('php://output', 'w');
        if (!$out) {
            Response::serverError('Unable to open output stream');
        }

        fputcsv($out, ['type', 'sku', 'name', 'category', 'size_code', 'size_name', 'color_name', 'current_stock', 'counted_stock']);

        $items = Database::queryAll("SELECT sku, name, category, stock_quantity FROM items ORDER BY name ASC");
        $itemBySku = [];
        foreach ($items as $i) {
            $itemBySku[(string)$i['sku']] = $i;
        }

        $sizeRows = Database::queryAll(
            "SELECT item_sku, size_code, size_name, stock_level
             FROM item_sizes
             WHERE is_active = 1 AND color_id IS NULL
             ORDER BY item_sku ASC, display_order ASC, size_name ASC"
        );

        $sizesBySku = [];
        foreach ($sizeRows as $sr) {
            $sku = (string)($sr['item_sku'] ?? '');
            if ($sku === '') continue;
            $sizesBySku[$sku] = true;
            $item = $itemBySku[$sku] ?? null;
            fputcsv($out, [
                'size',
                $sku,
                $item['name'] ?? '',
                $item['category'] ?? '',
                $sr['size_code'] ?? '',
                $sr['size_name'] ?? '',
                '',
                (int)($sr['stock_level'] ?? 0),
                ''
            ]);
        }

        $colorRows = Database::queryAll(
            "SELECT item_sku, color_name, stock_level
             FROM item_colors
             WHERE is_active = 1
             ORDER BY item_sku ASC, display_order ASC, color_name ASC"
        );

        $colorsBySku = [];
        foreach ($colorRows as $cr) {
            $sku = (string)($cr['item_sku'] ?? '');
            if ($sku === '') continue;
            $colorsBySku[$sku] = true;
        }

        foreach ($colorRows as $cr) {
            $sku = (string)($cr['item_sku'] ?? '');
            if ($sku === '') continue;
            if (!empty($sizesBySku[$sku])) {
                continue;
            }
            $item = $itemBySku[$sku] ?? null;
            fputcsv($out, [
                'color',
                $sku,
                $item['name'] ?? '',
                $item['category'] ?? '',
                '',
                '',
                $cr['color_name'] ?? '',
                (int)($cr['stock_level'] ?? 0),
                ''
            ]);
        }

        foreach ($items as $i) {
            $sku = (string)($i['sku'] ?? '');
            if ($sku === '') continue;
            if (!empty($sizesBySku[$sku])) continue;
            if (!empty($colorsBySku[$sku])) continue;

            fputcsv($out, [
                'item',
                $sku,
                $i['name'] ?? '',
                $i['category'] ?? '',
                '',
                '',
                '',
                (int)($i['stock_quantity'] ?? 0),
                ''
            ]);
        }

        fclose($out);
        exit;
    }

    if ($action === 'import') {
        if (!isset($_FILES['csv']) || !is_array($_FILES['csv'])) {
            Response::error('CSV file is required', null, 400);
        }

        $tmp = $_FILES['csv']['tmp_name'] ?? '';
        if ($tmp === '' || !file_exists($tmp)) {
            Response::error('Uploaded file missing', null, 400);
        }

        $handle = fopen($tmp, 'r');
        if (!$handle) {
            Response::error('Unable to read uploaded file', null, 400);
        }

        $header = fgetcsv($handle);
        $updated = 0;
        $skipped = 0;
        $errors = [];

        Database::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowMap = [];
                if (is_array($header)) {
                    for ($i = 0; $i < count($header); $i++) {
                        $key = (string)$header[$i];
                        $rowMap[$key] = $row[$i] ?? '';
                    }
                }

                $type = trim((string)($rowMap['type'] ?? ''));
                $sku = trim((string)($rowMap['sku'] ?? ''));
                $sizeCode = trim((string)($rowMap['size_code'] ?? ''));
                $colorName = trim((string)($rowMap['color_name'] ?? ''));
                $countedRaw = trim((string)($rowMap['counted_stock'] ?? ''));

                if ($sku === '' || $countedRaw === '') {
                    $skipped++;
                    continue;
                }

                if (!is_numeric($countedRaw) || (float)$countedRaw < 0) {
                    $errors[] = ['sku' => $sku, 'error' => 'Invalid counted_stock'];
                    continue;
                }
                $counted = (int)$countedRaw;

                if ($type === 'size') {
                    if ($sizeCode === '') {
                        $errors[] = ['sku' => $sku, 'error' => 'Missing size_code'];
                        continue;
                    }

                    $sizeRow = Database::queryOne(
                        "SELECT id, item_sku FROM item_sizes WHERE item_sku = ? AND color_id IS NULL AND size_code = ? AND is_active = 1 ORDER BY id ASC LIMIT 1",
                        [$sku, $sizeCode]
                    );
                    if (!$sizeRow) {
                        $errors[] = ['sku' => $sku, 'error' => 'Size row not found'];
                        continue;
                    }

                    Database::execute("UPDATE item_sizes SET stock_level = ? WHERE id = ?", [$counted, (int)$sizeRow['id']]);
                    syncTotalStockWithSizes(Database::getInstance(), $sku);
                    $updated++;
                    continue;
                }

                if ($type === 'color') {
                    if ($colorName === '') {
                        $errors[] = ['sku' => $sku, 'error' => 'Missing color_name'];
                        continue;
                    }

                    $color_row = Database::queryOne(
                        "SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ? AND is_active = 1 ORDER BY id ASC LIMIT 1",
                        [$sku, $colorName]
                    );
                    if (!$color_row) {
                        $errors[] = ['sku' => $sku, 'error' => 'Color row not found'];
                        continue;
                    }

                    Database::execute("UPDATE item_colors SET stock_level = ? WHERE id = ?", [$counted, (int)$color_row['id']]);
                    syncTotalStockWithColors(Database::getInstance(), $sku);
                    $updated++;
                    continue;
                }

                if ($type === 'item') {
                    $hasSizesRow = Database::queryOne("SELECT 1 FROM item_sizes WHERE item_sku = ? AND is_active = 1 LIMIT 1", [$sku]);
                    $hasColorsRow = Database::queryOne("SELECT 1 FROM item_colors WHERE item_sku = ? AND is_active = 1 LIMIT 1", [$sku]);

                    if ($hasSizesRow || $hasColorsRow) {
                        $errors[] = ['sku' => $sku, 'error' => 'Item has options; use size/color rows instead'];
                        continue;
                    }

                    Database::execute("UPDATE items SET stock_quantity = ? WHERE sku = ?", [$counted, $sku]);
                    $updated++;
                    continue;
                }

                $skipped++;
            }

            Database::commit();
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }

        if (is_resource($handle)) {
            fclose($handle);
        }

        Response::success([
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    Response::error('Invalid action', null, 400);
} catch (Exception $e) {
    Response::serverError('Inventory Audit error', $e->getMessage());
}
