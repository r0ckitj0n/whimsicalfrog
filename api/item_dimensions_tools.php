<?php
// Item Dimensions Tools
// Ensures weight and package dimension columns exist on items, scans all inventory for missing
// shipping attributes, and backfills gaps using the same AI dimension-generation flow used by
// item-level "Generate All" (image-aware info + dimensions suggestion), with heuristic fallback.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';
require_once __DIR__ . '/ai_providers.php';

try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        Response::methodNotAllowed('Method not allowed');
    }

    // Optional: basic admin check (non-fatal in dev)
    $isAdmin = isset($_SESSION['user']['role']) && strtolower((string) $_SESSION['user']['role']) === WF_Constants::ROLE_ADMIN;
    $strict = isset($_GET['strict']) && $_GET['strict'] == '1';
    if ($strict && !$isAdmin) {
        Response::forbidden('Admin access required');
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? 'run_all';
    $useAI = (isset($_GET['use_ai']) && $_GET['use_ai'] == '1') || (isset($_POST['use_ai']) && $_POST['use_ai'] == '1');

    // Ensure DB available
    try {
        Database::getInstance();
    } catch (Exception $e) {
        Response::serverError('Database error');
    }

    $results = [
        'ensured' => false,
        'scanned' => 0,
        'missing' => 0,
        'updated' => 0,
        'skipped' => 0,
        'preview' => []
    ];

    // 1) Ensure columns
    $ensureColumns = function () {
        $existing = Database::queryAll("SHOW COLUMNS FROM items");
        $cols = array_map(function ($r) {
            return strtolower($r['Field']); }, $existing);
        $adds = [];
        if (!in_array('weight_oz', $cols)) {
            $adds[] = "ADD COLUMN weight_oz DECIMAL(8,2) NULL DEFAULT NULL AFTER retail_price";
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

    // 2) Backfill missing values using image-aware AI dimensions (same flow as item "Generate")
    // and heuristic fallback.
    $backfillMissing = function () use (&$results, $useAI) {
        $normalizeDimensionValue = static function ($value): ?float {
            if (!is_numeric($value)) {
                return null;
            }
            $num = round((float) $value, 2);
            if (!is_finite($num) || $num <= 0) {
                return null;
            }
            return $num;
        };

        $normalizeDimensionsSuggestion = static function ($raw) use ($normalizeDimensionValue): ?array {
            if (!is_array($raw)) {
                return null;
            }

            $weight = $normalizeDimensionValue($raw['weight_oz'] ?? null);
            $dimensions = is_array($raw['dimensions_in'] ?? null) ? $raw['dimensions_in'] : [];
            $length = $normalizeDimensionValue($dimensions['length'] ?? null);
            $width = $normalizeDimensionValue($dimensions['width'] ?? null);
            $height = $normalizeDimensionValue($dimensions['height'] ?? null);

            if ($weight === null || $length === null || $width === null || $height === null) {
                return null;
            }

            return [
                'weight_oz' => $weight,
                'package_length_in' => $length,
                'package_width_in' => $width,
                'package_height_in' => $height
            ];
        };

        $resolveCategoryFromAnalysis = static function ($analysisCategory, $title, $description, $existingCategories) {
            $analysisCategory = trim((string) $analysisCategory);
            $title = strtolower(trim((string) $title));
            $description = strtolower(trim((string) $description));
            $analysisLower = strtolower($analysisCategory);
            $text = trim($title . ' ' . $description . ' ' . $analysisLower);

            if (!is_array($existingCategories) || count($existingCategories) === 0) {
                return $analysisCategory;
            }

            $bestCategory = $analysisCategory;
            $bestScore = -INF;

            foreach ($existingCategories as $candidateRaw) {
                $candidate = trim((string) $candidateRaw);
                if ($candidate === '') {
                    continue;
                }
                $candidateLower = strtolower($candidate);
                $score = 0.0;

                if ($analysisLower !== '' && $candidateLower === $analysisLower) {
                    $score += 60.0;
                }

                if ($candidateLower !== '' && preg_match('/\b' . preg_quote($candidateLower, '/') . '\b/i', $text)) {
                    $score += 30.0;
                }

                $tokens = preg_split('/[^a-z0-9]+/i', $candidateLower) ?: [];
                foreach ($tokens as $token) {
                    $token = trim($token);
                    if ($token === '' || strlen($token) < 3) {
                        continue;
                    }
                    if (preg_match('/\b' . preg_quote($token, '/') . '\b/i', $text)) {
                        $score += 10.0;
                    }
                }

                $isHatCategory = preg_match('/\b(hat|hats|cap|caps|beanie|headwear)\b/i', $candidateLower) === 1;
                $mentionsHatInImageText = preg_match('/\b(hat|hats|cap|caps|beanie|headwear)\b/i', $text) === 1;
                if ($isHatCategory && !$mentionsHatInImageText) {
                    $score -= 40.0;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCategory = $candidate;
                }
            }

            if (is_infinite($bestScore) || $bestScore <= 0) {
                return $analysisCategory;
            }
            return $bestCategory;
        };

        $rows = Database::queryAll(
            "SELECT sku, name, description, category, weight_oz, package_length_in, package_width_in, package_height_in \n" .
            "FROM items"
        );
        $results['scanned'] = count($rows);
        $updated = 0;
        $skipped = 0;
        $preview = [];
        $ai = null;
        $supportsImages = false;
        $existingCategories = [];
        if ($useAI) {
            try {
                $ai = new AIProviders();
                $supportsImages = $ai->currentModelSupportsImages();
                $categoryRows = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
                $existingCategories = array_values(array_filter(array_map(static function ($row) {
                    return trim((string) ($row['category'] ?? array_values($row)[0] ?? ''));
                }, $categoryRows)));
            } catch (\Throwable $e) {
                error_log('item_dimensions_tools.php AI init failed: ' . $e->getMessage());
                $ai = null;
                $supportsImages = false;
            }
        }

        // Load per-category default weights from settings (JSON map)
        $weightsMapRaw = [];
        try {
            $weightsMapRaw = BusinessSettings::get('shipping_category_weight_defaults', []);
        } catch (\Throwable $____) {
        }
        $weightsMap = is_array($weightsMapRaw) ? $weightsMapRaw : [];
        // Normalize keys to uppercase for matching
        $normMap = [];
        foreach ($weightsMap as $k => $v) {
            $key = strtoupper(trim((string) $k));
            if ($key === '')
                continue;
            $w = null;
            if (is_array($v) && isset($v['weight_oz']) && is_numeric($v['weight_oz'])) {
                $w = (float) $v['weight_oz'];
            } elseif (is_numeric($v)) {
                $w = (float) $v;
            }
            if ($w !== null) {
                $normMap[$key] = $w;
            }
        }
        $defaultMapW = isset($normMap['DEFAULT']) ? (float) $normMap['DEFAULT'] : null;

        foreach ($rows as $r) {
            $sku = (string) ($r['sku'] ?? '');
            $cat = strtoupper((string) ($r['category'] ?? ''));
            $name = trim((string) ($r['name'] ?? ''));
            $description = trim((string) ($r['description'] ?? ''));
            $w = $r['weight_oz'];
            $L = $r['package_length_in'];
            $W = $r['package_width_in'];
            $H = $r['package_height_in'];

            $needsWeight = !is_numeric($w) || (float) $w <= 0;
            $needsDims = !is_numeric($L) || !is_numeric($W) || !is_numeric($H) || ((float) $L <= 0 || (float) $W <= 0 || (float) $H <= 0);
            if ($needsWeight || $needsDims) {
                $results['missing']++;
            }

            if (!$needsWeight && !$needsDims) {
                $skipped++;
                continue;
            }

            $newW = is_numeric($w) && (float) $w > 0 ? (float) $w : null;
            $newL = is_numeric($L) && (float) $L > 0 ? (float) $L : null;
            $newWi = is_numeric($W) && (float) $W > 0 ? (float) $W : null;
            $newH = is_numeric($H) && (float) $H > 0 ? (float) $H : null;

            // Prefer AI suggestion when requested
            if ($useAI && $ai) {
                try {
                    // Match item-modal "info" path: image-aware info context, then dimensions suggestion.
                    $ctxName = $name !== '' ? $name : $sku;
                    $ctxDescription = $description;
                    $ctxCategory = (string) ($r['category'] ?? '');

                    if ($supportsImages) {
                        $images = AIProviders::getItemImages($sku, 1);
                        if (!empty($images)) {
                            $analysis = $ai->analyzeItemImage($images[0], $existingCategories);
                            if (is_array($analysis)) {
                                $resolvedCategory = $resolveCategoryFromAnalysis(
                                    $analysis['category'] ?? '',
                                    $analysis['title'] ?? '',
                                    $analysis['description'] ?? '',
                                    $existingCategories
                                );
                                $ctxName = trim((string) ($analysis['title'] ?? $ctxName));
                                $ctxDescription = trim((string) ($analysis['description'] ?? $ctxDescription));
                                $ctxCategory = trim((string) ($resolvedCategory !== '' ? $resolvedCategory : $ctxCategory));
                            }
                        }
                    }

                    $sugg = $ai->generateDimensionsSuggestion($ctxName, $ctxDescription, $ctxCategory);
                    $normalized = $normalizeDimensionsSuggestion($sugg);
                    if ($normalized !== null) {
                        if ($newW === null) {
                            $newW = (float) $normalized['weight_oz'];
                        }
                        if ($newL === null) {
                            $newL = (float) $normalized['package_length_in'];
                        }
                        if ($newWi === null) {
                            $newWi = (float) $normalized['package_width_in'];
                        }
                        if ($newH === null) {
                            $newH = (float) $normalized['package_height_in'];
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("item_dimensions_tools.php AI dimensions failed for {$sku}: " . $e->getMessage());
                    // fall back to defaults below
                }
            }

            // Apply per-category default weight from settings if available
            if ($newW === null || $newW <= 0) {
                $catU = $cat;
                // Exact match first
                if (isset($normMap[$catU])) {
                    $newW = (float) $normMap[$catU];
                }
                // Contains match (e.g., key 'TUMBLER' matches 'Drinkware · Tumbler')
                if (($newW === null || $newW <= 0) && !empty($normMap)) {
                    foreach ($normMap as $key => $valW) {
                        if ($key === 'DEFAULT')
                            continue;
                        if (strpos($catU, $key) !== false) {
                            $newW = (float) $valW;
                            break;
                        }
                    }
                }
                // DEFAULT fallback from settings
                if (($newW === null || $newW <= 0) && $defaultMapW !== null) {
                    $newW = (float) $defaultMapW;
                }
            }

            // Heuristic fallback (industry-standard approximations) for remaining gaps
            if ($newW === null || $newW <= 0 || $newL === null || $newL <= 0 || $newWi === null || $newWi <= 0 || $newH === null || $newH <= 0) {
                $isTumbler = (strpos($cat, 'TUMBLER') !== false) || (strpos($sku, 'WF-TU') === 0);
                $isShirt = (strpos($cat, 'SHIRT') !== false || strpos($cat, 'TEE') !== false || strpos($cat, 'T-SHIRT') !== false || strpos($cat, 'TS') !== false) || (strpos($sku, 'WF-TS') === 0);
                $isArt = (strpos($cat, 'ART') !== false) || (strpos($sku, 'WF-AR') === 0);
                $isWrap = (strpos($cat, 'WRAP') !== false) || (strpos($sku, 'WF-WW') === 0);
                $isGen = (strpos($cat, 'GEN') !== false) || (strpos($sku, 'WF-GEN') === 0);

                $defW = 8.0;
                $defL = 8.0;
                $defWIn = 6.0;
                $defH = 4.0; // generic
                if ($isTumbler) {
                    $defW = 12.0;
                    $defL = 10.0;
                    $defWIn = 4.0;
                    $defH = 4.0;
                } elseif ($isShirt) {
                    $defW = 5.0;
                    $defL = 10.0;
                    $defWIn = 8.0;
                    $defH = 1.0;
                } elseif ($isArt) {
                    $defW = 16.0;
                    $defL = 12.0;
                    $defWIn = 9.0;
                    $defH = 2.0;
                } elseif ($isWrap) {
                    $defW = 10.0;
                    $defL = 12.0;
                    $defWIn = 3.0;
                    $defH = 3.0;
                } elseif ($isGen) {
                    $defW = 8.0;
                    $defL = 8.0;
                    $defWIn = 6.0;
                    $defH = 4.0;
                }

                if ($newW === null || $newW <= 0)
                    $newW = $defW;
                if ($newL === null || $newL <= 0)
                    $newL = $defL;
                if ($newWi === null || $newWi <= 0)
                    $newWi = $defWIn;
                if ($newH === null || $newH <= 0)
                    $newH = $defH;
            }

            Database::execute(
                "UPDATE items SET weight_oz = ?, package_length_in = ?, package_width_in = ?, package_height_in = ? WHERE sku = ?",
                [$newW, $newL, $newWi, $newH, $sku]
            );
            $updated++;
            if ($updated <= 50) {
                $preview[] = ['sku' => $sku, 'weight_oz' => $newW, 'LxWxH_in' => [$newL, $newWi, $newH]];
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

    Response::success($results);

} catch (Throwable $e) {
    Response::serverError('Server error', ['error' => $e->getMessage()]);
}
