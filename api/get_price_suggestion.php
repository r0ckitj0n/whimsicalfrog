<?php
header('Content-Type: application/json');
require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Ensure user is logged in and is an Admin
$isLoggedIn = isset($_SESSION['user']);
$isAdmin = false;

if ($isLoggedIn) {
    $userData = $_SESSION['user'];
    // Handle both string and array formats
    if (is_string($userData)) {
        $userData = json_decode($userData, true);
    }
    if (is_array($userData)) {
        $isAdmin = isset($userData['role']) && $userData['role'] === 'Admin';
    }
}

if (!$isLoggedIn || !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Admin privileges required.']);
    exit;
}

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Only GET is allowed.']);
    exit;
}

// Get SKU parameter
$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU parameter is required.']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get the most recent price suggestion for this SKU
    $stmt = $pdo->prepare("
        SELECT 
            suggested_price,
            reasoning,
            confidence,
            factors,
            detected_materials,
            detected_features,
            market_intelligence,
            pricing_strategy,
            competitive_analysis,
            demand_indicators,
            target_audience,
            seasonality_factors,
            brand_premium_factor,
            price_elasticity_estimate,
            value_proposition,
            market_positioning,
            pricing_confidence_breakdown,
            cost_plus_multiplier,
            market_research_data,
            competitive_price_range,
            value_based_factors,
            psychological_pricing_notes,
            trend_alignment_score,
            uniqueness_score,
            demand_score,
            market_saturation_level,
            recommended_pricing_tier,
            profit_margin_analysis,
            pricing_elasticity_notes,
            created_at
        FROM price_suggestions 
        WHERE sku = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$sku]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Parse the reasoning to extract individual components with dollar amounts
        $reasoningText = $result['reasoning'] ?? '';
        $components = parseReasoningIntoComponents($reasoningText, $result);
        
        echo json_encode([
            'success' => true,
            'suggestedPrice' => floatval($result['suggested_price']),
            'reasoning' => $reasoningText,
            'components' => $components,
            'confidence' => $result['confidence'],
            'factors' => json_decode($result['factors'] ?? '{}', true),
            'analysis' => [
                'detected_materials' => json_decode($result['detected_materials'] ?? '[]', true),
                'detected_features' => json_decode($result['detected_features'] ?? '[]', true),
                'market_intelligence' => json_decode($result['market_intelligence'] ?? '[]', true),
                'pricing_strategy' => $result['pricing_strategy'],
                'competitive_analysis' => json_decode($result['competitive_analysis'] ?? '[]', true),
                'demand_indicators' => json_decode($result['demand_indicators'] ?? '[]', true),
                'target_audience' => json_decode($result['target_audience'] ?? '[]', true),
                'seasonality_factors' => json_decode($result['seasonality_factors'] ?? '[]', true),
                'brand_premium_factor' => floatval($result['brand_premium_factor'] ?? 1.0),
                'price_elasticity_estimate' => floatval($result['price_elasticity_estimate'] ?? 0.5),
                'value_proposition' => $result['value_proposition'],
                'market_positioning' => $result['market_positioning'],
                'pricing_confidence_breakdown' => json_decode($result['pricing_confidence_breakdown'] ?? '[]', true),
                'cost_plus_multiplier' => floatval($result['cost_plus_multiplier'] ?? 2.5),
                'market_research_data' => json_decode($result['market_research_data'] ?? '[]', true),
                'competitive_price_range' => $result['competitive_price_range'],
                'value_based_factors' => json_decode($result['value_based_factors'] ?? '[]', true),
                'psychological_pricing_notes' => $result['psychological_pricing_notes'],
                'trend_alignment_score' => floatval($result['trend_alignment_score'] ?? 0.5),
                'uniqueness_score' => floatval($result['uniqueness_score'] ?? 0.5),
                'demand_score' => floatval($result['demand_score'] ?? 0.5),
                'market_saturation_level' => $result['market_saturation_level'],
                'recommended_pricing_tier' => $result['recommended_pricing_tier'],
                'profit_margin_analysis' => $result['profit_margin_analysis'],
                'pricing_elasticity_notes' => $result['pricing_elasticity_notes']
            ],
            'createdAt' => $result['created_at']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No price suggestion found for this SKU'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get_price_suggestion.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}

function parseReasoningIntoComponents($reasoningText, $dbData) {
    $components = [];
    
    if (empty($reasoningText)) {
        return $components;
    }
    
    // Split reasoning by bullet points or similar separators
    $reasoningItems = preg_split('/[•·]|\s+•\s+/', $reasoningText);
    
    foreach ($reasoningItems as $item) {
        $item = trim($item);
        if (empty($item)) continue;
        
        // Extract dollar amount and label
        if (preg_match('/^(.+?):\s*\$(\d+(?:\.\d{2})?)/', $item, $matches)) {
            $label = trim($matches[1]);
            $amount = floatval($matches[2]);
            
            // Determine component type and explanation based on label
            $componentData = determineComponentTypeAndExplanation($label, $amount, $dbData);
            
            $components[] = [
                'label' => $label,
                'amount' => $amount,
                'type' => $componentData['type'],
                'explanation' => $componentData['explanation']
            ];
        }
    }
    
    return $components;
}

function determineComponentTypeAndExplanation($label, $amount, $dbData) {
    $labelLower = strtolower($label);
    
    // Map label patterns to component types and explanations
    if (strpos($labelLower, 'cost-plus') !== false || strpos($labelLower, 'cost plus') !== false) {
        return [
            'type' => 'cost_plus',
            'explanation' => 'Base pricing using cost multiplier analysis. This method adds a standard markup to the production cost to ensure profitability while remaining competitive.'
        ];
    }
    
    if (strpos($labelLower, 'market research') !== false) {
        return [
            'type' => 'market_research',
            'explanation' => 'Competitive market analysis and pricing research. Based on analysis of similar products in the market, competitor pricing, and industry benchmarks.'
        ];
    }
    
    if (strpos($labelLower, 'competitive') !== false) {
        return [
            'type' => 'competitive_analysis',
            'explanation' => 'Analysis of competitor pricing and market positioning. Considers direct competitors, market share, and competitive advantages to optimize pricing strategy.'
        ];
    }
    
    if (strpos($labelLower, 'value-based') !== false || strpos($labelLower, 'value based') !== false) {
        return [
            'type' => 'value_based',
            'explanation' => 'Pricing based on perceived customer value and benefits. Considers the unique value proposition, customer benefits, and willingness to pay for specific features.'
        ];
    }
    
    if (strpos($labelLower, 'brand premium') !== false) {
        return [
            'type' => 'brand_premium',
            'explanation' => 'Premium pricing based on brand positioning and market perception. Reflects the additional value customers place on brand reputation, quality, and exclusivity.'
        ];
    }
    
    if (strpos($labelLower, 'psychological') !== false) {
        return [
            'type' => 'psychological_pricing',
            'explanation' => 'Price optimization using psychological pricing principles. Techniques like charm pricing ($19.99 vs $20.00) to make prices more appealing to customers.'
        ];
    }
    
    if (strpos($labelLower, 'seasonal') !== false) {
        return [
            'type' => 'seasonality',
            'explanation' => 'Seasonal pricing adjustments based on demand patterns. Considers seasonal trends, holiday demand, and market timing to optimize pricing.'
        ];
    }
    
    // Default for unrecognized patterns
    return [
        'type' => 'analysis',
        'explanation' => 'Advanced algorithmic analysis considering multiple market factors and pricing strategies. This comprehensive approach evaluates various pricing models and market conditions.'
    ];
}
?> 