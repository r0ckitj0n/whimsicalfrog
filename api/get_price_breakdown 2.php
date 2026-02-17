<?php
// api/get_price_breakdown.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

try {
    $sku = $_GET['sku'] ?? '';
    if (!$sku)
        Response::error('Missing SKU');

    $factors = Database::queryAll("
        SELECT id, sku, label, amount, type, explanation, source, created_at
        FROM price_factors 
        WHERE sku = ?
        ORDER BY created_at, id
    ", [$sku]);

    // Live DBs may temporarily lag schema additions; degrade gracefully instead of 500'ing.
    $warnings = [];
    $item = null;
    try {
        $item = Database::queryOne("SELECT retail_price, ai_price_confidence, ai_price_at FROM items WHERE sku = ?", [$sku]);
    } catch (Throwable $e) {
        error_log('[get_price_breakdown] items AI metadata columns missing or query failed: ' . $e->getMessage());
        $warnings[] = 'AI metadata columns unavailable on items table (ai_price_confidence/ai_price_at). Schema sync needed.';
        $item = Database::queryOne("SELECT retail_price FROM items WHERE sku = ?", [$sku]);
    }
    $storedPrice = $item ? (float) ($item['retail_price'] ?? 0) : 0.0;
    $aiConfidence = $item ? (float) ($item['ai_price_confidence'] ?? 0) : 0;
    $aiAt = $item['ai_price_at'] ?? null;

    // Only sum contributing factors. Analysis/meta rows should not affect stored retail.
    $total = array_reduce($factors, function ($sum, $f) {
        $type = strtolower(trim((string) ($f['type'] ?? '')));
        if ($type === 'analysis' || $type === 'meta') return $sum;
        return $sum + (float) ($f['amount'] ?? 0);
    }, 0.0);

    Response::success([
        'factors' => array_map(function ($f) {
            return [
                'id' => (int) $f['id'],
                'sku' => $f['sku'],
                'label' => $f['label'],
                'amount' => (float) $f['amount'],
                'type' => $f['type'],
                'explanation' => $f['explanation'],
                'source' => $f['source'],
                'created_at' => $f['created_at']
            ];
        }, $factors),
        'warnings' => $warnings,
        'totals' => [
            'total' => (float) $total,
            'stored' => (float) $storedPrice,
            'ai_confidence' => $aiConfidence,
            'ai_at' => $aiAt
        ]
    ]);

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
