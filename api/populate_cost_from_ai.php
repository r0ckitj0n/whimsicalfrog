<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
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

    $qualityTier = $data['quality_tier'] ?? null;
    if ($qualityTier !== null && !in_array($qualityTier, ['standard', 'premium', 'luxury'], true)) {
        Response::error('Invalid quality_tier', null, 422);
    }

    // Build dynamic SET clause to only update cost-specific tier if provided
    $setClauses = ['ai_cost_confidence = ?', 'ai_cost_at = ?'];
    $params = [
        $suggestion['confidence'] ?? 0,
        $suggestion['created_at'] ?? date('Y-m-d H:i:s')
    ];
    
    if ($qualityTier !== null) {
        $setClauses[] = 'cost_quality_tier = ?';
        $params[] = $qualityTier;
    }
    $params[] = $sku;

    // Save AI metadata to items table
    Database::execute("
        UPDATE items 
        SET " . implode(', ', $setClauses) . " 
        WHERE sku = ?
    ", $params);

    Database::beginTransaction();
    Database::execute("DELETE FROM cost_factors WHERE sku = ?", [$sku]);

    // AI suggestion usually comes with categories inside a 'breakdown' property
    $breakdown = $suggestion['breakdown'] ?? $suggestion;

    $categories = ['materials', 'labor', 'energy', 'equipment'];

    foreach ($categories as $key) {
        $factors = $breakdown[$key] ?? null;
        if (!$factors)
            continue;

        if (is_array($factors)) {
            foreach ($factors as $factor) {
                // If AI provides { label: '...', cost: 10 } or { name: '...', cost: 10 }
                $label = $factor['label'] ?? $factor['name'] ?? $factor['description'] ?? 'AI Estimated ' . ucfirst($key);
                $label = trim((string)$label);
                if ($label === '' || strlen($label) > 255) {
                    $label = 'AI Estimated ' . ucfirst($key);
                }
                $cost = (float) ($factor['cost'] ?? 0);
                if ($cost > 0) {
                    Database::execute("
                        INSERT INTO cost_factors (sku, category, label, cost, source) 
                        VALUES (?, ?, ?, ?, 'ai')
                    ", [$sku, $key, $label, $cost]);
                }
            }
        } elseif (is_numeric($factors)) {
            // Handle primitives (e.g. Local AI returning totals)
            $cost = (float) $factors;
            if ($cost > 0) {
                $label = 'AI Estimated ' . ucfirst($key) . ' Total';
                Database::execute("
                    INSERT INTO cost_factors (sku, category, label, cost, source) 
                    VALUES (?, ?, ?, ?, 'ai')
                ", [$sku, $key, $label, $cost]);
            }
        }
    }

    // Keep items.cost_price consistent with the breakdown.
    wf_sync_item_cost_price_from_factors($sku);

    Database::commit();

    Response::success(null, 'Populated from AI');

} catch (Exception $e) {
    try {
        Database::rollBack();
    } catch (Throwable $_ignored) {
        // ignore
    }
    Response::serverError($e->getMessage());
}
