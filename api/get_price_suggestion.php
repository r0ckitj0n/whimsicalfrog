<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';

// Centralized admin check
AuthHelper::requireAdmin();

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed();
}

// Get SKU parameter
$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    Response::error('SKU parameter is required.', null, 400);
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Get the most recent price suggestion for this SKU
    $result = Database::queryOne("
        SELECT 
            suggested_price,
            reasoning,
            confidence,
            factors,
            components,
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
    ", [$sku]);

    if ($result) {
        // Get components from database first, fallback to parsing if not available
        $storedComponents = json_decode($result['components'] ?? '[]', true);
        $reasoningText = $result['reasoning'] ?? '';

        // Use stored components if available, otherwise parse from reasoning text
        $components = !empty($storedComponents) ? $storedComponents : parseReasoningIntoComponents($reasoningText, $result);

        Response::json([
            'success' => true,
            'suggested_price' => floatval($result['suggested_price']),
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
            'created_at' => $result['created_at']
        ]);
    } else {
        Response::json([
            'success' => false,
            'error' => 'No price suggestion found for this SKU'
        ]);
    }

} catch (Exception $e) {
    error_log("Error in get_price_suggestion.php: " . $e->getMessage());
    Response::serverError('Internal server error occurred.');
}

function parseReasoningIntoComponents($reasoningText, $dbData)
{
    $components = [];

    if (empty($reasoningText)) {
        return $components;
    }

    // Split reasoning by bullet points or similar separators
    $reasoningItems = preg_split('/[•·]|\s+•\s+/', $reasoningText);

    $foundComponents = false;
    foreach ($reasoningItems as $item) {
        $item = trim($item);
        if (empty($item)) {
            continue;
        }

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
            $foundComponents = true;
        }
    }

    // If no structured components found, create a single component from the full reasoning
    if (!$foundComponents && !empty($reasoningText)) {
        $suggested_price = floatval($dbData['suggested_price'] ?? 0);
        $components[] = [
            'label' => 'AI Pricing Analysis',
            'amount' => $suggested_price,
            'type' => 'comprehensive_analysis',
            'explanation' => $reasoningText
        ];
    }

    return $components;
}

function determineComponentTypeAndExplanation($label, $amount, $dbData)
{
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
            'explanation' => 'Competitive market analysis and pricing research. Based on analysis of similar items in the market, competitor pricing, and industry benchmarks.'
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

    // Handle comprehensive analysis type
    if ($labelLower === 'ai pricing analysis' || $labelLower === 'comprehensive analysis') {
        return [
            'type' => 'comprehensive_analysis',
            'explanation' => 'Comprehensive AI pricing analysis considering multiple market factors, competitive positioning, and strategic pricing approaches to determine optimal pricing.'
        ];
    }

    // Default for unrecognized patterns
    return [
        'type' => 'analysis',
        'explanation' => 'Advanced algorithmic analysis considering multiple market factors and pricing strategies. This comprehensive approach evaluates various pricing models and market conditions.'
    ];
}
