<?php
/**
 * Public Marketing Data API
 * Provides basic marketing data for popups without authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php';

// Check if SKU parameter is provided
if (!isset($_GET['sku']) || empty($_GET['sku'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU parameter is required.']);
    exit;
}

$sku = trim($_GET['sku']);

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Get basic marketing data for this SKU (public-safe fields only)
    $stmt = $pdo->prepare("
        SELECT 
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
        LIMIT 1
    ");
    $stmt->execute([$sku]);
    $suggestion = $stmt->fetch();

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

        echo json_encode([
            'success' => true,
            'exists' => true,
            'marketing_data' => $suggestion
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'No marketing data found for this SKU.'
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in get_marketing_data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
} catch (Exception $e) {
    error_log("Error in get_marketing_data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}
?> 