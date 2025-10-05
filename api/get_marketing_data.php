<?php
/**
 * Public Marketing Data API
 * Provides basic marketing data for popups without authentication
 */

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';

// CORS for public access (GET only)
Response::setCorsHeaders(['*'], ['GET'], ['Content-Type']);

// Check if SKU parameter is provided
if (!isset($_GET['sku']) || empty($_GET['sku'])) {
    Response::error('SKU parameter is required.', null, 400);
}

$sku = trim($_GET['sku']);

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Get basic marketing data for this SKU (public-safe fields only)
    $suggestion = Database::queryOne(
        "SELECT 
            sku,
            suggested_description,
            selling_points,
            competitive_advantages,
            call_to_action_suggestions,
            urgency_factors,
            customer_benefits,
            target_audience,
            unique_selling_points,
            value_propositions
        FROM marketing_suggestions 
        WHERE sku = ? 
        ORDER BY created_at DESC 
        LIMIT 1",
        [$sku]
    );

    if ($suggestion) {
        // Decode JSON fields
        $jsonFields = [
            'selling_points', 'competitive_advantages', 'call_to_action_suggestions',
            'urgency_factors', 'customer_benefits', 'unique_selling_points', 'value_propositions'
        ];

        foreach ($jsonFields as $field) {
            if (isset($suggestion[$field])) {
                $suggestion[$field] = json_decode($suggestion[$field], true);
            }
        }

        Response::json([
            'success' => true,
            'exists' => true,
            'marketing_data' => $suggestion
        ]);
    } else {
        Response::json([
            'success' => true,
            'exists' => false,
            'message' => 'No marketing data found for this SKU.'
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in get_marketing_data.php: " . $e->getMessage());
    Response::serverError('Database error occurred.');
} catch (Exception $e) {
    error_log("Error in get_marketing_data.php: " . $e->getMessage());
    Response::serverError('Internal server error occurred.');
}
?> 