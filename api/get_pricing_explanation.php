<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Ensure database is initialized via shared helper
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
    $reasoningText = $_GET['text'] ?? '';

    if (empty($reasoningText)) {
        echo json_encode([
            'success' => false,
            'error' => 'No reasoning text provided'
        ]);
        exit;
    }

    // Convert reasoning text to lowercase for matching
    $text = strtolower($reasoningText);

    // Define keyword mapping for better matching
    $keywordMap = [
        'market research' => 'market_research',
        'market analysis' => 'market_research',
        'competitive analysis' => 'competitive_analysis',
        'competitor' => 'competitive_analysis',
        'value-based' => 'value_based',
        'value pricing' => 'value_based',
        'psychological pricing' => 'psychological_pricing',
        'psychology' => 'psychological_pricing',
        'cost-plus' => 'cost_plus',
        'markup' => 'cost_plus',
        'premium' => 'premium_pricing',
        'luxury' => 'premium_pricing',
        'penetration' => 'penetration_pricing',
        'market entry' => 'penetration_pricing',
        'seasonal' => 'demand_based',
        'demand' => 'demand_based',
        'skimming' => 'skimming_pricing',
        'bundle' => 'bundle_pricing'
    ];

    // Find matching keyword
    $matchedKeyword = null;
    foreach ($keywordMap as $phrase => $keyword) {
        if (strpos($text, $phrase) !== false) {
            $matchedKeyword = $keyword;
            break;
        }
    }

    // Default to generic AI analysis if no specific match
    if (!$matchedKeyword) {
        echo json_encode([
            'success' => true,
            'title' => 'AI Pricing Analysis',
            'explanation' => 'Advanced algorithmic analysis considering multiple market factors and pricing strategies. This comprehensive approach evaluates various pricing models, market conditions, and competitive factors to recommend optimal pricing strategies.'
        ]);
        exit;
    }

    // Get explanation from database
    $result = Database::queryOne("SELECT title, explanation FROM pricing_explanations WHERE keyword = ?", [$matchedKeyword]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'title' => $result['title'],
            'explanation' => $result['explanation']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'title' => 'AI Pricing Analysis',
            'explanation' => 'Advanced algorithmic analysis considering multiple market factors and pricing strategies.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 