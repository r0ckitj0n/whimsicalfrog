<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';

// Centralized admin check
AuthHelper::requireAdmin();

// Check if SKU parameter is provided
if (!isset($_GET['sku']) || empty($_GET['sku'])) {
    Response::error('SKU parameter is required.', null, 400);
}

$sku = trim($_GET['sku']);

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Get existing marketing suggestion for this SKU
    $suggestion = Database::queryOne(
        "SELECT * FROM marketing_suggestions WHERE sku = ? ORDER BY created_at DESC LIMIT 1",
        [$sku]
    );

    if ($suggestion) {
        // Decode JSON fields
        $jsonFields = [
            'keywords', 'emotional_triggers', 'selling_points', 'competitive_advantages',
            'unique_selling_points', 'value_propositions', 'marketing_channels',
            'urgency_factors', 'social_proof_elements', 'call_to_action_suggestions',
            'conversion_triggers', 'objection_handlers', 'seo_keywords', 'content_themes',
            'customer_benefits', 'pain_points_addressed', 'lifestyle_alignment',
            'analysis_factors', 'market_trends'
        ];

        foreach ($jsonFields as $field) {
            if (isset($suggestion[$field])) {
                $suggestion[$field] = json_decode($suggestion[$field], true);
            }
        }

        Response::json([
            'success' => true,
            'exists' => true,
            'suggestion' => $suggestion
        ]);
    } else {
        Response::json([
            'success' => true,
            'exists' => false,
            'message' => 'No marketing suggestion found for this SKU.'
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in get_marketing_suggestion.php: " . $e->getMessage());
    Response::serverError('Database error occurred.');
} catch (Exception $e) {
    error_log("Error in get_marketing_suggestion.php: " . $e->getMessage());
    Response::serverError('Internal server error occurred.');
}
?>