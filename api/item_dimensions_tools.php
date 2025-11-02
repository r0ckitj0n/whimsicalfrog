<?php
// Item Dimensions Tools
// Ensures weight and package dimension columns exist on items, and backfills missing values with
// sensible industry-standard defaults based on category/SKU. Safe to run multiple times.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/business_settings_helper.php';
require_once __DIR__ . '/ai_providers.php';

try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        Response::methodNotAllowed('Method not allowed');
    }

    // Optional: basic admin check (non-fatal in dev)
    $isAdmin = isset($_SESSION['user']['role']) && strtolower((string)$_SESSION['user']['role']) === 'admin';
    $strict = isset($_GET['strict']) && $_GET['strict'] == '1';
    if ($strict && !$isAdmin) {
        Response::forbidden('Admin access required');
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? 'run_all';
    $useAI  = (isset($_GET['use_ai']) && $_GET['use_ai'] == '1') || (isset($_POST['use_ai']) && $_POST['use_ai'] == '1');

    // Ensure DB available
    try { Database::getInstance(); } catch (Exception $e) { Response::serverError('Database error'); }

    $results = [ 'ensured' => false, 'updated' => 0, 'skipped' => 0, 'preview' => [] ];

    // 1) Ensure columns
    $ensureColumns = function () {
        $existing = Database::queryAll("SHOW COLUMNS FROM items");
        $cols = array_map(function ($r) { return strtolower($r['Field']); }, $existing);
        $adds = [];
        if (!in_array('weight_oz', $cols)) {
            $adds[] = "ADD COLUMN weight_oz DECIMAL(8,2) NULL DEFAULT NULL AFTER retailPrice";
        }
        if (!in_array('package_length_in', $cols)) {
            $adds[] = "ADD COLUMN package_length_in DECIMAL(8,2) NULL DEFAULT NULL AFTER weight_oz";
        }
        if (!in_array('package_width_in', $cols)) {
            $adds[] = "ADD COLUMN package_width_in DECIMAL(8,2) NULL DEFAULT NULL AFTER package_length_in";
        }
        if (!in_array('package_height_in', $cols)) {
            $adds[] = "ADD COLUMN package_height_in DECIMAL(8,2) NULL DEFAULT NULL AFTER package_width_in";
        }
        if (!empty($adds)) {
            $sql = "ALTER TABLE items \n" . implode(",\n", $adds);
            Database::execute($sql);
            return true;
        }
        return false;
    };

    // 2) Backfill missing values using category/SKU heuristics
    $backfillMissing = function () use (&$results, $useAI) {
        $rows = Database::queryAll(
            "SELECT sku, category, weight_oz, package_length_in, package_width_in, package_height_in \n" .
            "FROM items"
        );
        $updated = 0; $skipped = 0; $preview = [];
        $ai = null;
        if ($useAI) {
            try { $ai = new AIProviders(); } catch (\Throwable $e) { $ai = null; }
        }

        // Load per-category default weights from settings (JSON map)
        $weightsMapRaw = [];
        try { $weightsMapRaw = BusinessSettings::get('shipping_category_weight_defaults', []); } catch (\Throwable $____) {}
        $weightsMap = is_array($weightsMapRaw) ? $weightsMapRaw : [];
        // Normalize keys to uppercase for matching
        $normMap = [];
        foreach ($weightsMap as $k => $v) {
            $key = strtoupper(trim((string)$k)); if ($key === '') continue;
            $w = null;
            if (is_array($v) && isset($v['weight_oz']) && is_numeric($v['weight_oz'])) { $w = (float)$v['weight_oz']; }
            elseif (is_numeric($v)) { $w = (float)$v; }
            if ($w !== null) { $normMap[$key] = $w; }
        }
        $defaultMapW = isset($normMap['DEFAULT']) ? (float)$normMap['DEFAULT'] : null;

        foreach ($rows as $r) {
            $sku = (string)($r['sku'] ?? '');
            $cat = strtoupper((string)($r['category'] ?? ''));
            $w = $r['weight_oz'];
            $L = $r['package_length_in'];
            $W = $r['package_width_in'];
            $H = $r['package_height_in'];

            $needsWeight = !is_numeric($w) || (float)$w <= 0;
            $needsDims = !is_numeric($L) || !is_numeric($W) || !is_numeric($H) || ((float)$L <= 0 || (float)$W <= 0 || (float)$H <= 0);

            if (!$needsWeight && !$needsDims) { $skipped++; continue; }

            $newW = is_numeric($w) && (float)$w > 0 ? (float)$w : null;
            $newL = is_numeric($L) && (float)$L > 0 ? (float)$L : null;
            $newWi= is_numeric($W) && (float)$W > 0 ? (float)$W : null;
            $newH = is_numeric($H) && (float)$H > 0 ? (float)$H : null;

            // Prefer AI suggestion when requested
            if ($useAI && $ai) {
                try {
                    // For AI context, fetch name/description lazily
                    $ctx = Database::queryOne("SELECT name, description FROM items WHERE sku = ?", [$sku]);
                    $sugg = $ai->generateDimensionsSuggestion($ctx['name'] ?? $sku, $ctx['description'] ?? '', $r['category'] ?? '');
                    if (is_array($sugg)) {
                        if ($newW === null) $newW = (float)($sugg['weight_oz'] ?? 0);
                        if ($newL === null || $newWi === null || $newH === null) {
                            $dims = $sugg['dimensions_in'] ?? [];
                            if ($newL === null)  $newL  = (float)($dims['length'] ?? 0);
                            if ($newWi === null) $newWi = (float)($dims['width'] ?? 0);
                            if ($newH === null)  $newH  = (float)($dims['height'] ?? 0);
                        }
                    }
                } catch (\Throwable $e) {
                    // fall back to heuristics
                }
            }

            // Apply per-category default weight from settings if available
            if ($newW === null || $newW <= 0) {
                $catU = $cat;
                // Exact match first
                if (isset($normMap[$catU])) { $newW = (float)$normMap[$catU]; }
                // Contains match (e.g., key 'TUMBLER' matches 'Drinkware · Tumbler')
                if (($newW === null || $newW <= 0) && !empty($normMap)) {
                    foreach ($normMap as $key => $valW) {
                        if ($key === 'DEFAULT') continue;
                        if (strpos($catU, $key) !== false) { $newW = (float)$valW; break; }
                    }
                }
                // DEFAULT fallback from settings
                if (($newW === null || $newW <= 0) && $defaultMapW !== null) {
                    $newW = (float)$defaultMapW;
                }
            }

            // Heuristic fallback (industry-standard approximations) for remaining gaps
            if ($newW === null || $newW <= 0 || $newL === null || $newL <= 0 || $newWi === null || $newWi <= 0 || $newH === null || $newH <= 0) {
                $isTumbler = (strpos($cat, 'TUMBLER') !== false) || (strpos($sku, 'WF-TU') === 0);
                $isShirt   = (strpos($cat, 'SHIRT') !== false || strpos($cat, 'TEE') !== false || strpos($cat, 'T-SHIRT') !== false || strpos($cat, 'TS') !== false) || (strpos($sku, 'WF-TS') === 0);
                $isArt     = (strpos($cat, 'ART') !== false) || (strpos($sku, 'WF-AR') === 0);
                $isWrap    = (strpos($cat, 'WRAP') !== false) || (strpos($sku, 'WF-WW') === 0);
                $isGen     = (strpos($cat, 'GEN') !== false) || (strpos($sku, 'WF-GEN') === 0);

                $defW = 8.0; $defL = 8.0; $defWIn = 6.0; $defH = 4.0; // generic
                if ($isTumbler) { $defW = 12.0; $defL = 10.0; $defWIn = 4.0; $defH = 4.0; }
                elseif ($isShirt) { $defW = 5.0; $defL = 10.0; $defWIn = 8.0; $defH = 1.0; }
                elseif ($isArt) { $defW = 16.0; $defL = 12.0; $defWIn = 9.0; $defH = 2.0; }
                elseif ($isWrap) { $defW = 10.0; $defL = 12.0; $defWIn = 3.0; $defH = 3.0; }
                elseif ($isGen) { $defW = 8.0; $defL = 8.0; $defWIn = 6.0; $defH = 4.0; }

                if ($newW === null || $newW <= 0) $newW = $defW;
                if ($newL === null || $newL <= 0) $newL = $defL;
                if ($newWi === null || $newWi <= 0) $newWi = $defWIn;
                if ($newH === null || $newH <= 0) $newH = $defH;
            }

            Database::execute(
                "UPDATE items SET weight_oz = ?, package_length_in = ?, package_width_in = ?, package_height_in = ? WHERE sku = ?",
                [ $newW, $newL, $newWi, $newH, $sku ]
            );
            $updated++;
            if ($updated <= 50) {
                $preview[] = [ 'sku' => $sku, 'weight_oz' => $newW, 'LxWxH_in' => [$newL, $newWi, $newH] ];
            }
        }

        $results['updated'] = $updated;
        $results['skipped'] = $skipped;
        $results['preview'] = $preview;
        return $updated;
    };

    if ($action === 'ensure_columns' || $action === 'run_all') {
        $ensured = $ensureColumns();
        $results['ensured'] = $ensured;
    }

    if ($action === 'backfill_missing' || $action === 'run_all') {
        $backfillMissing();
    }

    Response::success(['message' => 'OK', 'results' => $results]);

} catch (Throwable $e) {
    Response::serverError('Server error', [ 'error' => $e->getMessage() ]);
}
