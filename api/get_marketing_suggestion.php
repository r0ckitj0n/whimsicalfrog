<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Use centralized authentication
requireAdmin();

// Check if SKU parameter is provided
if (!isset($_GET['sku']) || empty($_GET['sku'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU parameter is required.']);
    exit;
}

$sku = trim($_GET['sku']);

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get existing marketing suggestion for this SKU
    $stmt = $pdo->prepare("
        SELECT * FROM marketing_suggestions 
        WHERE sku = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$sku]);
    $suggestion = $stmt->fetch();
    
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
        
        echo json_encode([
            'success' => true,
            'exists' => true,
            'suggestion' => $suggestion
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'No marketing suggestion found for this SKU.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in get_marketing_suggestion.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
} catch (Exception $e) {
    error_log("Error in get_marketing_suggestion.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}
?> 