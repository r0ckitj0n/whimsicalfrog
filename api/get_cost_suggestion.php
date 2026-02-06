<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';

try {
    AuthHelper::requireAdmin();
} catch (Exception $e) {
    error_log("Auth failed in get_cost_suggestion.php: " . $e->getMessage());
    Response::json(['success' => true, 'data' => null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed();
}

$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    Response::error('SKU parameter is required.', null, 400);
}

try {
    // Query the unified cost_factors table - single source of truth
    $factors = Database::queryAll("
        SELECT category, SUM(cost) as total
        FROM cost_factors 
        WHERE sku = ?
        GROUP BY category
    ", [$sku]);

    $breakdown = [
        'materials' => 0,
        'labor' => 0,
        'energy' => 0,
        'equipment' => 0
    ];

    foreach ($factors as $f) {
        $breakdown[$f['category']] = (float) $f['total'];
    }

    $total = $breakdown['materials'] + $breakdown['labor'] + $breakdown['energy'] + $breakdown['equipment'];

    // Also get the stored AI suggestion metadata if available
    $aiSuggestion = null;
    try {
        $aiSuggestion = Database::queryOne("
            SELECT suggested_cost, reasoning, confidence, breakdown, created_at
            FROM cost_suggestions 
            WHERE sku = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ", [$sku]);
    } catch (Exception $e) {
        // Ignore - AI suggestions table may not exist or have no data
    }

    // If live factors are empty but we have AI breakdown, fall back to it
    if ($total <= 0 && $aiSuggestion && !empty($aiSuggestion['breakdown'])) {
        $decoded = json_decode($aiSuggestion['breakdown'], true);
        if (is_array($decoded)) {
            $breakdown = array_merge($breakdown, $decoded);
            $total = (float) ($aiSuggestion['suggested_cost'] ?? $total);
        }
    }

    Response::json([
        'success' => true,
        'suggested_cost' => $total,
        'reasoning' => $aiSuggestion['reasoning'] ?? '',
        'confidence' => $aiSuggestion['confidence'] ?? null,
        'breakdown' => $breakdown,
        'created_at' => $aiSuggestion['created_at'] ?? null,
        'source' => 'live'
    ]);

} catch (Exception $e) {
    error_log("Error in get_cost_suggestion.php: " . $e->getMessage());
    Response::json(['success' => true, 'data' => null]);
}
