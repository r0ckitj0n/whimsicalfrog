<?php
// api/get_ai_cost_suggestion.php
// Returns the most recent stored AI cost suggestion (from cost_suggestions) for a SKU.

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';

AuthHelper::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed();
}

$sku = $_GET['sku'] ?? '';
$sku = is_string($sku) ? trim($sku) : '';
if ($sku === '') {
    Response::error('SKU parameter is required.', null, 400);
}

try {
    $row = Database::queryOne(
        "SELECT suggested_cost, reasoning, confidence, breakdown, created_at
         FROM cost_suggestions
         WHERE sku = ?
         ORDER BY created_at DESC
         LIMIT 1",
        [$sku]
    );

    if (!$row) {
        Response::json([
            'success' => false,
            'error' => 'No AI cost suggestion found for this SKU'
        ]);
    }

    $breakdown = [];
    $rawBreakdown = $row['breakdown'] ?? '';
    if (is_string($rawBreakdown) && $rawBreakdown !== '') {
        $decoded = json_decode($rawBreakdown, true);
        if (is_array($decoded)) $breakdown = $decoded;
    } elseif (is_array($rawBreakdown)) {
        $breakdown = $rawBreakdown;
    }

    Response::json([
        'success' => true,
        'suggested_cost' => (float) ($row['suggested_cost'] ?? 0),
        'reasoning' => (string) ($row['reasoning'] ?? ''),
        'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : null,
        'breakdown' => $breakdown,
        'created_at' => $row['created_at'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log("Error in get_ai_cost_suggestion.php: " . $e->getMessage());
    Response::serverError('Internal server error occurred.');
}

