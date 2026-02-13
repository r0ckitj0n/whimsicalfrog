<?php
// api/populate_price_from_ai.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/item_price_sync.php';

function ensureAiTierColumnsExist(): void
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
        error_log('Failed to ensure AI tier columns: ' . $e->getMessage());
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::methodNotAllowed('Method not allowed');
    }
    requireAdmin(true);

    $data = Response::getJsonInput();
    $sku = trim((string)($data['sku'] ?? ''));
    $suggestion = $data['suggestion'] ?? null;

    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
        Response::error('Invalid SKU format', null, 422);
    }
    if (!$suggestion || !is_array($suggestion)) {
        Response::error('Missing required fields');
    }

    ensureAiTierColumnsExist();

    // 1. Clear existing price factors
    // 2. Save AI metadata to items table
    // Ensure confidence is numeric - AI may return 'N/A' which breaks decimal columns
    $confidenceRaw = $suggestion['confidence'] ?? null;
    $confidence = is_numeric($confidenceRaw) ? (float) $confidenceRaw : null;
    $qualityTier = $data['quality_tier'] ?? null;
    if ($qualityTier !== null && !in_array($qualityTier, ['standard', 'premium', 'luxury'], true)) {
        Response::error('Invalid quality_tier', null, 422);
    }

    // Start transaction (after all validations that can Response::error/exit).
    Database::beginTransaction();

    // 1. Clear existing price factors
    Database::execute("DELETE FROM price_factors WHERE sku = ?", [$sku]);

    // Build dynamic SET clause to only update price-specific tier if provided
    $setClauses = ['ai_price_confidence = ?', 'ai_price_at = ?'];
    $params = [$confidence, $suggestion['created_at'] ?? date('Y-m-d H:i:s')];
    
    if ($qualityTier !== null) {
        $setClauses[] = 'price_quality_tier = ?';
        $params[] = $qualityTier;
    }
    $params[] = $sku;

    Database::execute("
        UPDATE items 
        SET " . implode(', ', $setClauses) . " 
        WHERE sku = ?
    ", $params);

    // 3. Insert new price factors.
    // IMPORTANT: Suggestion components may contain alternative anchors (cost-plus, market research, etc.)
    // that are not additive and must not be summed into the stored retail price.
    // We persist:
    //   - "analysis" rows for component breakdown (display-only; excluded from totals)
    //   - a single "final" factor that matches suggestion.suggested_price (contributes to totals)
    $suggestedPrice = (float) ($suggestion['suggested_price'] ?? 0);
    if ($suggestedPrice <= 0) {
        throw new Exception('Invalid suggested_price from AI suggestion.');
    }
    $suggestedPrice = round($suggestedPrice, 2);

    $reasoning = trim((string) ($suggestion['reasoning'] ?? ''));
    if (strlen($reasoning) > 2000) {
        $reasoning = substr($reasoning, 0, 2000);
    }

    // Insert analysis rows (components) first so the UI can show a nice "Cost Breakdown"-style list.
    $componentsRaw = $suggestion['components'] ?? [];
    if (is_string($componentsRaw)) {
        $decoded = json_decode($componentsRaw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $componentsRaw = $decoded;
        }
    }

    // Components may come as an array or an object map.
    $components = [];
    if (is_array($componentsRaw)) {
        $isAssoc = array_keys($componentsRaw) !== range(0, count($componentsRaw) - 1);
        if ($isAssoc) {
            foreach ($componentsRaw as $type => $val) {
                if (is_array($val)) {
                    $val['type'] = $val['type'] ?? $type;
                    $components[] = $val;
                } else {
                    $components[] = ['type' => $type, 'amount' => $val];
                }
            }
        } else {
            $components = $componentsRaw;
        }
    }

    foreach ($components as $comp) {
        if (!is_array($comp)) continue;
        $label = trim((string) ($comp['label'] ?? $comp['type'] ?? ''));
        if ($label === '') continue;
        if (strlen($label) > 120) $label = substr($label, 0, 120);

        $amountRaw = $comp['amount'] ?? 0;
        $amount = is_numeric($amountRaw) ? round((float) $amountRaw, 2) : 0.0;

        $explanation = trim((string) ($comp['explanation'] ?? ''));
        if (strlen($explanation) > 2000) $explanation = substr($explanation, 0, 2000);

        Database::execute(
            "INSERT INTO price_factors (sku, label, amount, type, explanation, source)
             VALUES (?, ?, ?, 'analysis', ?, 'ai')",
            [$sku, $label, $amount, $explanation]
        );
    }

    Database::execute(
        "INSERT INTO price_factors (sku, label, amount, type, explanation, source)
         VALUES (?, 'AI Suggested Retail', ?, 'final', ?, 'ai')",
        [$sku, $suggestedPrice, $reasoning]
    );

    // Keep items.retail_price consistent with the breakdown.
    wf_sync_item_retail_price_from_factors($sku);

    Database::commit();
    Response::success(null, 'Populated price factors from AI');

} catch (Exception $e) {
    try {
        Database::rollBack();
    } catch (Throwable $_ignored) {
        // ignore
    }
    Response::serverError($e->getMessage());
}
