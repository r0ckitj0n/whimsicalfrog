<?php
// api/populate_price_from_ai.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

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
    $data = Response::getJsonInput();
    $sku = $data['sku'] ?? '';
    $suggestion = $data['suggestion'] ?? null;

    if (!$sku || !$suggestion)
        Response::error('Missing required fields');

    ensureAiTierColumnsExist();

    // Start transaction
    Database::beginTransaction();

    // 1. Clear existing price factors
    Database::execute("DELETE FROM price_factors WHERE sku = ?", [$sku]);

    // 2. Save AI metadata to items table
    // Ensure confidence is numeric - AI may return 'N/A' which breaks decimal columns
    $confidenceRaw = $suggestion['confidence'] ?? null;
    $confidence = is_numeric($confidenceRaw) ? (float) $confidenceRaw : null;
    $qualityTier = $data['quality_tier'] ?? null;

    // Build dynamic SET clause to only update quality_tier if provided
    $setClauses = ['ai_price_confidence = ?', 'ai_price_at = ?'];
    $params = [$confidence, $suggestion['created_at'] ?? date('Y-m-d H:i:s')];
    
    if ($qualityTier !== null) {
        $setClauses[] = 'quality_tier = ?';
        $setClauses[] = 'price_quality_tier = ?';
        $params[] = $qualityTier;
        $params[] = $qualityTier;
    }
    $params[] = $sku;

    Database::execute("
        UPDATE items 
        SET " . implode(', ', $setClauses) . " 
        WHERE sku = ?
    ", $params);

    // 3. Insert new price factors
    $components = $suggestion['components'] ?? [];

    if (is_array($components)) {
        foreach ($components as $comp) {
            Database::execute("
                INSERT INTO price_factors (sku, label, amount, type, explanation, source)
                VALUES (?, ?, ?, ?, ?, 'ai')
            ", [
                $sku,
                $comp['label'] ?? 'AI Price Component',
                (float) ($comp['amount'] ?? 0),
                $comp['type'] ?? 'analysis',
                $comp['explanation'] ?? '',
            ]);
        }
    }

    Database::commit();
    Response::success(null, 'Populated price factors from AI');

} catch (Exception $e) {
    Database::rollBack();
    Response::serverError($e->getMessage());
}
