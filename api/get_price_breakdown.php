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
        ORDER BY created_at
    ", [$sku]);

    $item = Database::queryOne("SELECT retail_price, ai_price_confidence, ai_price_at FROM items WHERE sku = ?", [$sku]);
    $storedPrice = $item ? (float) $item['retail_price'] : 0.0;
    $aiConfidence = $item ? (float) ($item['ai_price_confidence'] ?? 0) : 0;
    $aiAt = $item ? $item['ai_price_at'] : null;

    $total = array_reduce($factors, fn($sum, $f) => $sum + (float) $f['amount'], 0);

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
