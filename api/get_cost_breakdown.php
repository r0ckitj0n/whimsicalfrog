<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';

try {
    $sku = $_GET['sku'] ?? '';
    if (!$sku)
        Response::error('Missing SKU');

    // Query the unified cost_factors table
    $factors = Database::queryAll("
        SELECT id, sku, category, label, cost, source, created_by, created_at, updated_at
        FROM cost_factors 
        WHERE sku = ?
        ORDER BY category, created_at
    ", [$sku]);

    // Group by category
    $materials = [];
    $labor = [];
    $energy = [];
    $equipment = [];

    foreach ($factors as $f) {
        $item = [
            'id' => (int) $f['id'],
            'sku' => $f['sku'],
            'label' => $f['label'],
            'cost' => (float) $f['cost'],
            'source' => $f['source'],
            'created_by' => $f['created_by'],
            'created_at' => $f['created_at'],
            'updated_at' => $f['updated_at']
        ];

        switch ($f['category']) {
            case 'materials':
                $materials[] = $item;
                break;
            case 'labor':
                $labor[] = $item;
                break;
            case 'energy':
                $energy[] = $item;
                break;
            case 'equipment':
                $equipment[] = $item;
                break;
        }
    }

    // Get stored cost_price and AI metadata from items table.
    // Live DBs may temporarily lag schema additions; degrade gracefully instead of 500'ing.
    $warnings = [];
    $item = null;
    try {
        $item = Database::queryOne("SELECT cost_price, ai_cost_confidence, ai_cost_at FROM items WHERE sku = ?", [$sku]);
    } catch (Throwable $e) {
        error_log('[get_cost_breakdown] items AI metadata columns missing or query failed: ' . $e->getMessage());
        $warnings[] = 'AI metadata columns unavailable on items table (ai_cost_confidence/ai_cost_at). Schema sync needed.';
        $item = Database::queryOne("SELECT cost_price FROM items WHERE sku = ?", [$sku]);
    }
    $storedPrice = $item ? (float) ($item['cost_price'] ?? 0) : 0.0;
    $aiConfidence = $item ? (float) ($item['ai_cost_confidence'] ?? 0) : 0;
    $aiAt = $item['ai_cost_at'] ?? null;

    $calculateTotal = function ($items) {
        return array_reduce($items, fn($sum, $item) => $sum + (float) ($item['cost'] ?? 0), 0);
    };

    $materialTotal = $calculateTotal($materials);
    $laborTotal = $calculateTotal($labor);
    $energyTotal = $calculateTotal($energy);
    $equipmentTotal = $calculateTotal($equipment);

    Response::success([
        'materials' => $materials,
        'labor' => $labor,
        'energy' => $energy,
        'equipment' => $equipment,
        'warnings' => $warnings,
        'totals' => [
            'materials' => $materialTotal,
            'labor' => $laborTotal,
            'energy' => $energyTotal,
            'equipment' => $equipmentTotal,
            'total' => $materialTotal + $laborTotal + $energyTotal + $equipmentTotal,
            'stored' => $storedPrice,
            'ai_confidence' => $aiConfidence,
            'ai_at' => $aiAt
        ]
    ]);

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
