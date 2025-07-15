<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_providers.php';

header('Content-Type: application/json');

// Use centralized authentication
// Admin authentication with token fallback for API access
    // Check admin authentication using centralized helper
    AuthHelper::requireAdmin();

// Suppress all output before JSON header
ob_start();

// Turn off error display for this API to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// Authentication is handled by requireAdmin() above
$userData = getCurrentUser();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Only POST is allowed.']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input.']);
    exit;
}

// Validate required fields
if (empty($input['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Item name is required.']);
    exit;
}

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Extract item data
    $name = trim($input['name']);
    $description = trim($input['description'] ?? '');
    $category = trim($input['category'] ?? '');
    $costPrice = floatval($input['costPrice'] ?? 0);
    $sku = trim($input['sku'] ?? '');
    $useImages = $input['useImages'] ?? false;
    
    // Get item images if using image support
    $images = [];
    if ($useImages && !empty($sku)) {
        try {
            $stmt = $pdo->prepare("SELECT image_path FROM item_images WHERE sku = ? ORDER BY display_order ASC LIMIT 3");
            $stmt->execute([$sku]);
            $imageRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($imageRows as $row) {
                $imagePath = __DIR__ . '/../' . $row['image_path'];
                if (file_exists($imagePath)) {
                    $images[] = $imagePath;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to load images for pricing: " . $e->getMessage());
        }
    }
    
    // Initialize pricing analysis using AI provider system
    $pricingData = null;
    try {
        if (!empty($images) && $useImages) {
            // Use image-enhanced AI generation
            $aiProviders = new AIProviders();
            $pricingData = $aiProviders->generatePricingSuggestionWithImages($name, $description, $category, $costPrice, $images);
            error_log("AI Provider (with images) returned: " . json_encode(['price' => $pricingData['price'] ?? 'null']));
        } else {
            // Use AI provider for text-only generation
            $aiProviders = new AIProviders();
            $pricingData = $aiProviders->generatePricingSuggestion($name, $description, $category, $costPrice);
            error_log("AI Provider (text-only) returned: " . json_encode(['price' => $pricingData['price'] ?? 'null']));
        }
    } catch (Exception $e) {
        // Fallback to Jon's AI if external API fails
        error_log("AI Provider failed for pricing, using Jon's AI fallback: " . $e->getMessage());
        try {
            $pricingData = analyzePricing($name, $description, $category, $costPrice, $pdo);
            error_log("Local fallback returned: " . json_encode(['price' => $pricingData['price'] ?? 'null']));
        } catch (Exception $e2) {
            error_log("Jon's AI fallback also failed: " . $e2->getMessage());
            throw new Exception("Both AI provider and Jon's AI fallback failed");
        }
    }
    
    // Validate that we have pricing data
    if (!$pricingData || !isset($pricingData['price']) || $pricingData['price'] === null) {
        error_log("No valid pricing data received. PricingData: " . json_encode($pricingData));
        throw new Exception("Failed to generate price suggestion");
    }
    
    // Save enhanced price suggestion to database (create table if needed)
    if (!empty($sku)) {
        try {
            // Enhanced price_suggestions table already exists with proper structure
            
            $stmt = $pdo->prepare("
                INSERT INTO price_suggestions (
                    sku, suggested_price, reasoning, confidence, factors, components,
                    detected_materials, detected_features, market_intelligence, pricing_strategy,
                    competitive_analysis, demand_indicators, target_audience, seasonality_factors,
                    brand_premium_factor, price_elasticity_estimate, value_proposition, market_positioning,
                    pricing_confidence_breakdown, cost_plus_multiplier, market_research_data,
                    competitive_price_range, value_based_factors, psychological_pricing_notes,
                    trend_alignment_score, uniqueness_score, demand_score, market_saturation_level,
                    recommended_pricing_tier, profit_margin_analysis, pricing_elasticity_notes
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                suggested_price = VALUES(suggested_price),
                reasoning = VALUES(reasoning),
                confidence = VALUES(confidence),
                factors = VALUES(factors),
                components = VALUES(components),
                detected_materials = VALUES(detected_materials),
                detected_features = VALUES(detected_features),
                market_intelligence = VALUES(market_intelligence),
                pricing_strategy = VALUES(pricing_strategy),
                competitive_analysis = VALUES(competitive_analysis),
                demand_indicators = VALUES(demand_indicators),
                target_audience = VALUES(target_audience),
                seasonality_factors = VALUES(seasonality_factors),
                brand_premium_factor = VALUES(brand_premium_factor),
                price_elasticity_estimate = VALUES(price_elasticity_estimate),
                value_proposition = VALUES(value_proposition),
                market_positioning = VALUES(market_positioning),
                pricing_confidence_breakdown = VALUES(pricing_confidence_breakdown),
                cost_plus_multiplier = VALUES(cost_plus_multiplier),
                market_research_data = VALUES(market_research_data),
                competitive_price_range = VALUES(competitive_price_range),
                value_based_factors = VALUES(value_based_factors),
                psychological_pricing_notes = VALUES(psychological_pricing_notes),
                trend_alignment_score = VALUES(trend_alignment_score),
                uniqueness_score = VALUES(uniqueness_score),
                demand_score = VALUES(demand_score),
                market_saturation_level = VALUES(market_saturation_level),
                recommended_pricing_tier = VALUES(recommended_pricing_tier),
                profit_margin_analysis = VALUES(profit_margin_analysis),
                pricing_elasticity_notes = VALUES(pricing_elasticity_notes),
                created_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $sku,
                $pricingData['price'],
                $pricingData['reasoning'],
                $pricingData['confidence'],
                json_encode($pricingData['factors']),
                json_encode($pricingData['components']),
                json_encode($pricingData['analysis']['detected_materials'] ?? []),
                json_encode($pricingData['analysis']['detected_features'] ?? []),
                json_encode($pricingData['analysis']['competitor_analysis'] ?? []),
                $pricingData['analysis']['pricing_strategy'] ?? 'cost_plus',
                json_encode($pricingData['analysis']['competitor_analysis'] ?? []),
                json_encode($pricingData['analysis']['demand_indicators'] ?? []),
                json_encode($pricingData['analysis']['target_audience'] ?? []),
                json_encode($pricingData['analysis']['seasonality_factors'] ?? []),
                $pricingData['analysis']['brand_premium'] ?? 1.0,
                $pricingData['analysis']['pricing_elasticity'] ?? 0.5,
                $pricingData['analysis']['value_proposition'] ?? '',
                $pricingData['analysis']['market_positioning'] ?? 'standard',
                json_encode($pricingData['analysis']['pricing_confidence_breakdown'] ?? []),
                $pricingData['analysis']['cost_plus_multiplier'] ?? 2.5,
                json_encode($pricingData['analysis']['market_research_data'] ?? []),
                $pricingData['analysis']['competitive_price_range'] ?? '',
                json_encode($pricingData['analysis']['value_based_factors'] ?? []),
                $pricingData['analysis']['psychological_pricing_notes'] ?? '',
                $pricingData['analysis']['trend_alignment_score'] ?? 0.5,
                $pricingData['analysis']['uniqueness_score'] ?? 0.5,
                $pricingData['analysis']['demand_score'] ?? 0.5,
                $pricingData['analysis']['market_saturation_level'] ?? 'medium',
                $pricingData['analysis']['recommended_pricing_tier'] ?? 'standard',
                $pricingData['analysis']['profit_margin_analysis'] ?? '',
                $pricingData['analysis']['pricing_elasticity_notes'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("Error saving price suggestion: " . $e->getMessage());
        }
    }
    
    // Clear any buffered output and send clean JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'suggestedPrice' => $pricingData['price'],
        'reasoning' => $pricingData['reasoning'],
        'confidence' => $pricingData['confidence'],
        'factors' => $pricingData['factors'],
        'components' => $pricingData['components'],
        'analysis' => $pricingData['analysis']
    ]);
    
} catch (Exception $e) {
    error_log("Error in suggest_price.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}

function analyzePricing($name, $description, $category, $costPrice, $pdo) {
    // Load AI settings from database
    $aiSettings = loadAISettings($pdo);
    
    // Enhanced AI item analysis (reuse cost analysis functions)
    $itemAnalysis = analyzeItemForPricing($name, $description, $category);
    
    $factors = [];
    $reasoning = [];
    $confidence = 'medium';
    
    // Enhanced pricing strategy analysis with AI settings
    $pricingStrategies = analyzePricingStrategies($name, $description, $category, $costPrice, $itemAnalysis, $pdo);
    
    // Create individual pricing components with dollar amounts
    $pricingComponents = [];
    
    // Apply AI temperature and conservative mode adjustments
    $temperature = $aiSettings['ai_price_temperature'];
    $conservativeMode = $aiSettings['ai_conservative_mode'];
    $baseMultiplier = $aiSettings['ai_price_multiplier_base'];
    
    // Get AI weights for pricing strategies
    $costPlusWeight = $aiSettings['ai_cost_plus_weight'];
    $marketWeight = $aiSettings['ai_market_research_weight'];
    $valueWeight = $aiSettings['ai_value_based_weight'];
    
    // 1. Cost-Plus Pricing (Foundation)
    $costPlusPrice = $pricingStrategies['cost_plus_price'] * $baseMultiplier;
    
    // Apply temperature-based variation (lower temperature = less variation)
    if (!$conservativeMode && $temperature > 0.5) {
        $variation = ($temperature - 0.5) * 0.2; // Max 10% variation at temp 1.0
        $randomFactor = 1 + (mt_rand(-100, 100) / 1000) * $variation;
        $costPlusPrice *= $randomFactor;
    }
    
    $basePrice = $costPlusPrice;
    $factors['cost_plus'] = $costPlusPrice;
    $pricingComponents['cost_plus'] = [
        'amount' => $costPlusPrice,
        'label' => 'Cost-plus pricing',
        'explanation' => 'Base pricing using cost multiplier analysis'
    ];
    
    // 2. Market Research Pricing
    $marketPrice = $pricingStrategies['market_research_price'] * $baseMultiplier;
    if ($marketPrice > 0) {
        // Apply temperature-based variation
        if (!$conservativeMode && $temperature > 0.5) {
            $variation = ($temperature - 0.5) * 0.15;
            $randomFactor = 1 + (mt_rand(-100, 100) / 1000) * $variation;
            $marketPrice *= $randomFactor;
        }
        
        $factors['market_research'] = $marketPrice;
        $pricingComponents['market_research'] = [
            'amount' => $marketPrice,
            'label' => 'Market research analysis',
            'explanation' => 'Competitive market analysis and pricing research'
        ];
        
        // Use AI-configured weights instead of hardcoded values
        $basePrice = ($basePrice * (1 - $marketWeight)) + ($marketPrice * $marketWeight);
        $confidence = 'high';
    }
    
    // 3. Competitive Analysis
    $competitivePrice = $pricingStrategies['competitive_price'];
    if ($competitivePrice > 0) {
        $factors['competitive'] = $competitivePrice;
        $pricingComponents['competitive_analysis'] = [
            'amount' => $competitivePrice,
            'label' => 'Competitive analysis',
            'explanation' => 'Analysis of competitor pricing and market positioning'
        ];
        $basePrice = ($basePrice * 0.7) + ($competitivePrice * 0.3);
        $confidence = 'high';
    }
    
    // 4. Value-Based Pricing
    $valuePrice = $pricingStrategies['value_based_price'] * $baseMultiplier;
    if ($valuePrice > 0) {
        // Apply temperature-based variation
        if (!$conservativeMode && $temperature > 0.5) {
            $variation = ($temperature - 0.5) * 0.25; // Value pricing can be more variable
            $randomFactor = 1 + (mt_rand(-100, 100) / 1000) * $variation;
            $valuePrice *= $randomFactor;
        }
        
        $factors['value_based'] = $valuePrice;
        $pricingComponents['value_based'] = [
            'amount' => $valuePrice,
            'label' => 'Value-based pricing',
            'explanation' => 'Pricing based on perceived customer value and benefits'
        ];
        
        // Use AI-configured weight instead of hardcoded value
        $basePrice = ($basePrice * (1 - $valueWeight)) + ($valuePrice * $valueWeight);
    }
    
    // 5. Brand Premium Analysis
    $brandPremium = $pricingStrategies['brand_premium_factor'] ?? 1.0;
    if ($brandPremium > 1.0) {
        $premiumAmount = $basePrice * ($brandPremium - 1.0);
        $pricingComponents['brand_premium'] = [
            'amount' => $premiumAmount,
            'label' => 'Brand premium: +' . round(($brandPremium - 1.0) * 100) . '%',
            'explanation' => 'Premium pricing based on brand positioning and market perception'
        ];
        $basePrice = $basePrice * $brandPremium;
    }
    
    // 6. Psychological Pricing
    $psychPrice = applyPsychologicalPricing($basePrice);
    if ($psychPrice != $basePrice) {
        $pricingComponents['psychological_pricing'] = [
            'amount' => $psychPrice - $basePrice,
            'label' => 'Psychological pricing applied',
            'explanation' => 'Price optimization using psychological pricing principles (e.g., $19.99 vs $20.00)'
        ];
        $basePrice = $psychPrice;
    }
    
    // Build reasoning array from components
    foreach ($pricingComponents as $key => $component) {
        $reasoning[] = $component['label'] . ': $' . number_format($component['amount'], 2);
    }
    
    // Validate final price
    $finalPrice = validatePriceRange($basePrice, $costPrice);
    $factors['final'] = $finalPrice;
    
    // Convert pricingComponents to the format expected by frontend
    $components = [];
    foreach ($pricingComponents as $key => $component) {
        $components[] = [
            'type' => $key,
            'label' => $component['label'],
            'amount' => $component['amount'],
            'explanation' => $component['explanation']
        ];
    }
    
    // Enhanced analysis data with individual components
    $enhancedAnalysis = [
        'detected_materials' => $itemAnalysis['materials'] ?? [],
        'detected_features' => $itemAnalysis['features'] ?? [],
        'competitor_analysis' => $pricingStrategies['competitive_analysis'] ?? [],
        'pricing_strategy' => determinePrimaryStrategy($pricingStrategies),
        'demand_indicators' => $itemAnalysis['demand_indicators'] ?? [],
        'target_audience' => $itemAnalysis['target_audience'] ?? [],
        'seasonality_factors' => $itemAnalysis['seasonality_factors'] ?? [],
        'brand_premium' => $brandPremium,
        'pricing_elasticity' => $pricingStrategies['pricing_elasticity'] ?? 0.5,
        'value_proposition' => $itemAnalysis['value_proposition'] ?? '',
        'market_positioning' => $itemAnalysis['market_positioning'] ?? 'standard',
        'pricing_confidence_breakdown' => $pricingStrategies['confidence_metrics'] ?? [],
        'cost_plus_multiplier' => $pricingStrategies['cost_plus_multiplier'] ?? 2.5,
        'market_research_data' => $pricingStrategies['market_research_data'] ?? [],
        'competitive_price_range' => $pricingStrategies['competitive_price_range'] ?? '',
        'value_based_factors' => $pricingStrategies['value_based_factors'] ?? [],
        'psychological_pricing_notes' => $pricingStrategies['psychological_pricing_notes'] ?? '',
        'trend_alignment_score' => $itemAnalysis['trend_alignment_score'] ?? 0.5,
        'uniqueness_score' => $itemAnalysis['uniqueness_score'] ?? 0.5,
        'demand_score' => $itemAnalysis['demand_score'] ?? 0.5,
        'market_saturation_level' => $itemAnalysis['market_saturation_level'] ?? 'medium',
        'recommended_pricing_tier' => determinePricingTier($finalPrice, $category),
        'profit_margin_analysis' => calculateProfitMarginAnalysis($finalPrice, $costPrice),
        'pricing_elasticity_notes' => $pricingStrategies['pricing_elasticity_notes'] ?? '',
        'pricing_components' => $pricingComponents  // Add individual components for frontend
    ];
    
    return [
        'price' => $finalPrice,
        'reasoning' => implode(' • ', $reasoning),
        'confidence' => $confidence,
        'factors' => $factors,
        'analysis' => $enhancedAnalysis,
        'components' => $components  // Use the properly formatted components array
    ];
}

// Helper function to determine primary pricing strategy
function determinePrimaryStrategy($strategies) {
    $maxPrice = 0;
    $primaryStrategy = 'cost_plus';
    
    foreach ($strategies as $key => $value) {
        if (strpos($key, '_price') !== false && $value > $maxPrice) {
            $maxPrice = $value;
            $primaryStrategy = str_replace('_price', '', $key);
        }
    }
    
    return $primaryStrategy;
}

// Helper function to determine pricing tier
function determinePricingTier($price, $category) {
    $categoryTiers = [
        'T-Shirts' => ['budget' => 15, 'standard' => 25, 'premium' => 40],
        'Tumblers' => ['budget' => 20, 'standard' => 35, 'premium' => 50],
        'Artwork' => ['budget' => 30, 'standard' => 60, 'premium' => 100],
        'Sublimation' => ['budget' => 25, 'standard' => 45, 'premium' => 75],
        'Window Wraps' => ['budget' => 40, 'standard' => 80, 'premium' => 150]
    ];
    
    $tiers = $categoryTiers[$category] ?? ['budget' => 20, 'standard' => 35, 'premium' => 60];
    
    if ($price <= $tiers['budget']) return 'budget';
    if ($price <= $tiers['standard']) return 'standard';
    return 'premium';
}

// Helper function to calculate profit margin analysis
function calculateProfitMarginAnalysis($retailPrice, $costPrice) {
    if ($costPrice <= 0) {
        return 'Cost price not available for margin calculation';
    }
    
    $profit = $retailPrice - $costPrice;
    $marginPercent = ($profit / $retailPrice) * 100;
    $markupPercent = ($profit / $costPrice) * 100;
    
    return sprintf(
        'Profit: $%.2f (%.1f%% margin, %.1f%% markup)',
        $profit,
        $marginPercent,
        $markupPercent
    );
}
// loadAISettings function moved to ai_manager.php for centralization

function getCategoryMarkup($category) {
    $markups = [
        'T-Shirts' => 2.5,
        'Tumblers' => 2.8,
        'Artwork' => 4.0,
        'Sublimation' => 3.2,
        'Window Wraps' => 3.5,
        'default' => 2.5
    ];
    
    return $markups[$category] ?? $markups['default'];
}

function estimateBasePriceFromName($name, $category) {
    $name = strtolower($name);
    
    // Category base prices
    $basePrices = [
        'T-Shirts' => 15.00,
        'Tumblers' => 12.00,
        'Artwork' => 25.00,
        'Sublimation' => 20.00,
        'Window Wraps' => 35.00,
        'default' => 18.00
    ];
    
    $basePrice = $basePrices[$category] ?? $basePrices['default'];
    
    // Size adjustments
    if (preg_match('/\b(xl|xxl|large|big)\b/i', $name)) {
        $basePrice *= 1.2;
    } elseif (preg_match('/\b(small|mini|xs)\b/i', $name)) {
        $basePrice *= 0.8;
    }
    
    return $basePrice;
}

function simulateMarketResearch($name, $description, $category) {
    // Simulate market research based on item characteristics
    $keywords = extractKeywords($name . ' ' . $description);
    
    // Market price ranges by category and keywords
    $marketRanges = [
        'T-Shirts' => ['min' => 12, 'max' => 35],
        'Tumblers' => ['min' => 8, 'max' => 28],
        'Artwork' => ['min' => 15, 'max' => 75],
        'Sublimation' => ['min' => 10, 'max' => 45],
        'Window Wraps' => ['min' => 25, 'max' => 80],
        'default' => ['min' => 10, 'max' => 40]
    ];
    
    $range = $marketRanges[$category] ?? $marketRanges['default'];
    
    // Adjust based on keywords
    $priceModifier = 1.0;
    $premiumKeywords = ['custom', 'personalized', 'premium', 'deluxe', 'professional', 'vintage', 'artisan'];
    $budgetKeywords = ['basic', 'simple', 'standard', 'economy'];
    
    foreach ($premiumKeywords as $keyword) {
        if (stripos($name . ' ' . $description, $keyword) !== false) {
            $priceModifier += 0.3;
            break;
        }
    }
    
    foreach ($budgetKeywords as $keyword) {
        if (stripos($name . ' ' . $description, $keyword) !== false) {
            $priceModifier -= 0.2;
            break;
        }
    }
    
    $estimatedPrice = ($range['min'] + $range['max']) / 2 * $priceModifier;
    
    return [
        'found' => true,
        'price' => max($range['min'], min($range['max'], $estimatedPrice))
    ];
}

function getCategoryAdjustment($category, $name) {
    $name = strtolower($name);
    
    // Category-specific adjustments
    switch ($category) {
        case 'Artwork':
            if (strpos($name, 'canvas') !== false) return 1.3;
            if (strpos($name, 'print') !== false) return 0.9;
            break;
        case 'T-Shirts':
            if (strpos($name, 'organic') !== false || strpos($name, 'eco') !== false) return 1.4;
            if (strpos($name, 'cotton') !== false) return 1.1;
            break;
        case 'Tumblers':
            if (strpos($name, 'insulated') !== false || strpos($name, 'thermal') !== false) return 1.3;
            if (strpos($name, 'stainless') !== false) return 1.2;
            break;
    }
    
    return 1.0;
}

function detectPremiumFeatures($name, $description) {
    $text = strtolower($name . ' ' . $description);
    $multiplier = 1.0;
    
    $premiumFeatures = [
        'handmade' => 1.4,
        'custom' => 1.3,
        'personalized' => 1.3,
        'limited edition' => 1.5,
        'artisan' => 1.4,
        'premium' => 1.2,
        'luxury' => 1.6,
        'professional' => 1.2,
        'vintage' => 1.3,
        'exclusive' => 1.4
    ];
    
    foreach ($premiumFeatures as $feature => $factor) {
        if (strpos($text, $feature) !== false) {
            $multiplier = max($multiplier, $factor);
        }
    }
    
    return $multiplier;
}

function getCompetitiveAnalysis($name, $category, $pdo) {
    try {
        // Get similar items from database
        $stmt = $pdo->prepare("
            SELECT retailPrice 
            FROM items 
            WHERE category = ? 
            AND name != ? 
            AND retailPrice > 0 
            ORDER BY retailPrice
        ");
        $stmt->execute([$category, $name]);
        $prices = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($prices) > 0) {
            return [
                'count' => count($prices),
                'average' => array_sum($prices) / count($prices),
                'median' => $prices[floor(count($prices) / 2)],
                'min' => min($prices),
                'max' => max($prices)
            ];
        }
    } catch (Exception $e) {
        error_log("Error in competitive analysis: " . $e->getMessage());
    }
    
    return ['count' => 0];
}

function applyPsychologicalPricing($price) {
    // Apply psychological pricing (ending in .99, .95, etc.)
    if ($price >= 20) {
        // For higher prices, use .99
        return floor($price) + 0.99;
    } elseif ($price >= 10) {
        // For medium prices, use .95
        return floor($price) + 0.95;
    } else {
        // For lower prices, round to nearest .50 or .99
        if ($price < 5) {
            return round($price * 2) / 2; // Round to nearest .50
        } else {
            return floor($price) + 0.99;
        }
    }
}

function validatePriceRange($price, $costPrice) {
    // Ensure minimum markup if cost price is known
    if ($costPrice > 0) {
        $minPrice = $costPrice * 1.5; // Minimum 50% markup
        $maxPrice = $costPrice * 8.0; // Maximum 800% markup
        
        $price = max($minPrice, min($maxPrice, $price));
    }
    
    // Ensure reasonable absolute minimums
    $price = max(1.00, $price);
    
    return $price;
}

function extractKeywords($text) {
    $text = strtolower($text);
    $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
    $words = preg_split('/\s+/', $text);
    
    return array_filter($words, function($word) use ($commonWords) {
        return strlen($word) > 2 && !in_array($word, $commonWords);
    });
}

// Enhanced Pricing Analysis Functions
function analyzeItemForPricing($name, $description, $category) {
    $text = strtolower($name . ' ' . $description);
    
    return [
        'materials' => detectMaterialsForPricing($text),
        'features' => detectFeaturesForPricing($text),
        'size_analysis' => analyzeSizeForPricing($text, $category),
        'complexity_score' => calculatePricingComplexity([], [], [], $category),
        'target_audience' => analyzeTargetAudience($text, $category, []),
        'seasonality_factors' => calculateSeasonalityFactor($text, $category),
        'brand_premium_indicators' => calculateBrandPremium($text, [], 'standard'),
        'demand_indicators' => analyzeDemandIndicators($text, $category),
        'pricing_elasticity' => estimatePricingElasticity($category, [], 'standard'),
        'value_proposition' => 'Quality craftsmanship and personalization',
        'market_positioning' => analyzeMarketPositioning($text, [], $category),
        'trend_alignment_score' => 0.7,
        'uniqueness_score' => 0.6,
        'demand_score' => 0.7,
        'market_saturation_level' => 'medium'
    ];
}

function analyzePricingStrategies($name, $description, $category, $costPrice, $itemAnalysis, $pdo) {
    // Cost-plus pricing
    $costPlusPrice = calculateCostPlusPrice($costPrice, $category, $itemAnalysis);
    
    // Market Research Pricing
    $marketResearchPrice = calculateMarketResearchPrice($name, $description, $category, $itemAnalysis);
    
    // Competitive Pricing
    $competitiveData = getEnhancedCompetitiveAnalysis($name, $category, $itemAnalysis, $pdo);
    $competitivePrice = $competitiveData['suggested_price'];
    
    // Value-Based Pricing
    $valueBasedPrice = calculateValueBasedPrice($itemAnalysis, $category);
    
    // Confidence metrics
    $confidenceMetrics = calculatePricingConfidenceMetrics($costPrice, $marketResearchPrice, $competitivePrice, $valueBasedPrice);
    
    return [
        'cost_plus_price' => $costPlusPrice,
        'market_research_price' => $marketResearchPrice,
        'competitive_price' => $competitivePrice,
        'value_based_price' => $valueBasedPrice,
        'competitor_analysis' => $competitiveData['analysis'],
        'market_confidence' => $confidenceMetrics['market'],
        'competitive_confidence' => $confidenceMetrics['competitive'],
        'value_confidence' => $confidenceMetrics['value'],
        'pricing_confidence' => $confidenceMetrics['pricing']
    ];
}

function detectMaterialsForPricing($text) {
    // Reuse material detection but focus on pricing impact
    $materials = [];
    
    $materialPricingDatabase = [
        'organic_cotton' => ['price_premium' => 1.4, 'market_appeal' => 'high'],
        'cotton' => ['price_premium' => 1.0, 'market_appeal' => 'standard'],
        'polyester' => ['price_premium' => 0.8, 'market_appeal' => 'budget'],
        'vinyl' => ['price_premium' => 1.1, 'market_appeal' => 'standard'],
        'canvas' => ['price_premium' => 1.3, 'market_appeal' => 'premium'],
        'stainless_steel' => ['price_premium' => 1.8, 'market_appeal' => 'premium'],
        'ceramic' => ['price_premium' => 1.2, 'market_appeal' => 'standard'],
        'glass' => ['price_premium' => 1.5, 'market_appeal' => 'premium']
    ];
    
    foreach ($materialPricingDatabase as $material => $data) {
        if (strpos($text, str_replace('_', ' ', $material)) !== false) {
            $materials[] = [
                'type' => $material,
                'price_premium' => $data['price_premium'],
                'market_appeal' => $data['market_appeal']
            ];
        }
    }
    
    return $materials;
}

function detectFeaturesForPricing($text) {
    // Focus on features that impact pricing
    $features = [];
    
    $featurePricingDatabase = [
        'custom_design' => ['price_impact' => 1.5, 'market_demand' => 'high'],
        'personalized' => ['price_impact' => 1.4, 'market_demand' => 'high'],
        'handmade' => ['price_impact' => 1.8, 'market_demand' => 'premium'],
        'limited_edition' => ['price_impact' => 1.6, 'market_demand' => 'high'],
        'premium' => ['price_impact' => 1.3, 'market_demand' => 'medium'],
        'luxury' => ['price_impact' => 2.0, 'market_demand' => 'premium'],
        'eco_friendly' => ['price_impact' => 1.3, 'market_demand' => 'growing'],
        'vintage' => ['price_impact' => 1.4, 'market_demand' => 'niche']
    ];
    
    foreach ($featurePricingDatabase as $feature => $data) {
        $keywords = explode('_', $feature);
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $features[] = [
                    'type' => $feature,
                    'price_impact' => $data['price_impact'],
                    'market_demand' => $data['market_demand']
                ];
                break;
            }
        }
    }
    
    return $features;
}

function analyzeSizeForPricing($text, $category) {
    // Size analysis with pricing focus
    $sizeAnalysis = [
        'detected_size' => 'standard',
        'price_multiplier' => 1.0,
        'market_segment' => 'mainstream'
    ];
    
    $sizePricingPatterns = [
        'small' => ['multiplier' => 0.8, 'segment' => 'budget'],
        'standard' => ['multiplier' => 1.0, 'segment' => 'mainstream'],
        'large' => ['multiplier' => 1.3, 'segment' => 'premium'],
        'extra_large' => ['multiplier' => 1.6, 'segment' => 'premium']
    ];
    
    foreach ($sizePricingPatterns as $size => $data) {
        if (strpos($text, $size) !== false || strpos($text, str_replace('_', ' ', $size)) !== false) {
            $sizeAnalysis['detected_size'] = $size;
            $sizeAnalysis['price_multiplier'] = $data['multiplier'];
            $sizeAnalysis['market_segment'] = $data['segment'];
            break;
        }
    }
    
    return $sizeAnalysis;
}

function calculatePricingComplexity($materials, $features, $sizeAnalysis, $category) {
    $complexity = 0.5;
    
    // Material complexity
    foreach ($materials as $material) {
        if ($material['market_appeal'] === 'premium') {
            $complexity += 0.2;
        }
    }
    
    // Feature complexity
    foreach ($features as $feature) {
        if ($feature['market_demand'] === 'premium') {
            $complexity += 0.3;
        } elseif ($feature['market_demand'] === 'high') {
            $complexity += 0.2;
        }
    }
    
    // Category base complexity
    $categoryComplexity = [
        'T-Shirts' => 0.3,
        'Tumblers' => 0.4,
        'Artwork' => 0.8,
        'Sublimation' => 0.6,
        'Window Wraps' => 0.7
    ];
    
    $complexity += $categoryComplexity[$category] ?? 0.5;
    
    return min(2.0, max(0.1, $complexity));
}

function analyzeTargetAudience($text, $category, $features) {
    $audiences = [];
    
    // Category-based audience
    $categoryAudiences = [
        'T-Shirts' => ['casual_wear', 'fashion_conscious'],
        'Tumblers' => ['active_lifestyle', 'eco_conscious'],
        'Artwork' => ['art_collectors', 'home_decorators'],
        'Sublimation' => ['gift_buyers', 'personalization_seekers'],
        'Window Wraps' => ['business_owners', 'advertisers']
    ];
    
    $audiences = $categoryAudiences[$category] ?? ['general_consumers'];
    
    // Feature-based audience refinement
    foreach ($features as $feature) {
        switch ($feature['type']) {
            case 'luxury':
                $audiences[] = 'luxury_buyers';
                break;
            case 'eco_friendly':
                $audiences[] = 'eco_conscious';
                break;
            case 'custom_design':
                $audiences[] = 'personalization_seekers';
                break;
        }
    }
    
    return array_unique($audiences);
}

function calculateSeasonalityFactor($text, $category) {
    $currentMonth = date('n'); // 1-12
    $seasonalityFactor = 1.0;
    
    // Seasonal keywords
    $seasonalKeywords = [
        'christmas' => ['months' => [11, 12], 'factor' => 1.3],
        'halloween' => ['months' => [10], 'factor' => 1.2],
        'valentine' => ['months' => [2], 'factor' => 1.2],
        'summer' => ['months' => [6, 7, 8], 'factor' => 1.1],
        'winter' => ['months' => [12, 1, 2], 'factor' => 1.1],
        'graduation' => ['months' => [5, 6], 'factor' => 1.2],
        'back to school' => ['months' => [8, 9], 'factor' => 1.1]
    ];
    
    foreach ($seasonalKeywords as $keyword => $data) {
        if (strpos($text, $keyword) !== false && in_array($currentMonth, $data['months'])) {
            $seasonalityFactor = $data['factor'];
            break;
        }
    }
    
    return $seasonalityFactor;
}

function calculateBrandPremium($text, $features, $marketPositioning) {
    $brandPremium = 1.0;
    
    // Market positioning impact
    switch ($marketPositioning) {
        case 'luxury':
            $brandPremium = 1.5;
            break;
        case 'premium':
            $brandPremium = 1.3;
            break;
        case 'budget':
            $brandPremium = 0.8;
            break;
        default:
            $brandPremium = 1.0;
    }
    
    // Feature-based brand premium
    foreach ($features as $feature) {
        if ($feature['type'] === 'luxury') {
            $brandPremium = max($brandPremium, 1.6);
        } elseif ($feature['type'] === 'handmade') {
            $brandPremium = max($brandPremium, 1.4);
        }
    }
    
    return $brandPremium;
}

function analyzeDemandIndicators($text, $category) {
    $indicators = [
        'trend_alignment' => 'neutral',
        'market_saturation' => 'medium',
        'uniqueness_score' => 0.5
    ];
    
    // Trend keywords
    $trendKeywords = ['trending', 'popular', 'viral', 'hot', 'new', 'latest'];
    foreach ($trendKeywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            $indicators['trend_alignment'] = 'high';
            break;
        }
    }
    
    // Uniqueness indicators
    $uniqueKeywords = ['custom', 'unique', 'one-of-a-kind', 'exclusive', 'limited'];
    $uniqueCount = 0;
    foreach ($uniqueKeywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            $uniqueCount++;
        }
    }
    $indicators['uniqueness_score'] = min(1.0, $uniqueCount * 0.3);
    
    return $indicators;
}

function estimatePricingElasticity($category, $features, $marketPositioning) {
    // Base elasticity by category (lower = less price sensitive)
    $categoryElasticity = [
        'T-Shirts' => 0.8,
        'Tumblers' => 0.6,
        'Artwork' => 0.4,
        'Sublimation' => 0.7,
        'Window Wraps' => 0.5
    ];
    
    $elasticity = $categoryElasticity[$category] ?? 0.6;
    
    // Adjust based on market positioning
    switch ($marketPositioning) {
        case 'luxury':
            $elasticity *= 0.5; // Luxury buyers less price sensitive
            break;
        case 'budget':
            $elasticity *= 1.5; // Budget buyers more price sensitive
            break;
    }
    
    // Feature adjustments
    foreach ($features as $feature) {
        if ($feature['type'] === 'custom_design') {
            $elasticity *= 0.7; // Custom items less price sensitive
        }
    }
    
    return min(2.0, max(0.1, $elasticity));
}

function calculateCostPlusPrice($costPrice, $category, $itemAnalysis) {
    if ($costPrice <= 0) {
        // Estimate cost based on category
        $estimatedCosts = [
            'T-Shirts' => 6.00,
            'Tumblers' => 4.50,
            'Artwork' => 8.00,
            'Sublimation' => 5.50,
            'Window Wraps' => 12.00
        ];
        $costPrice = $estimatedCosts[$category] ?? 7.00;
    }
    
    $markup = getCategoryMarkup($category);
    
    // Adjust markup based on complexity and features
    $complexity = $itemAnalysis['complexity_score'] ?? 0.5;
    $markup *= (1 + ($complexity - 0.5) * 0.5);
    
    return $costPrice * $markup;
}

function calculateMarketResearchPrice($name, $description, $category, $itemAnalysis) {
    $basePrice = simulateMarketResearch($name, $description, $category);
    
    if (!$basePrice['found']) {
        return 0;
    }
    
    $price = $basePrice['price'];
    
            // Adjust based on item analysis
    foreach ($itemAnalysis['materials'] as $material) {
        $price *= $material['price_premium'];
    }
    
    foreach ($itemAnalysis['features'] as $feature) {
        $price *= $feature['price_impact'];
    }
    
    return $price;
}

function getEnhancedCompetitiveAnalysis($name, $category, $itemAnalysis, $pdo) {
    $basicAnalysis = getCompetitiveAnalysis($name, $category, $pdo);
    
    $analysis = [
        'competitor_count' => $basicAnalysis['count'],
        'price_range' => [],
        'positioning_gap' => 'none',
        'suggested_price' => 0
    ];
    
    if ($basicAnalysis['count'] > 0) {
        $analysis['price_range'] = [
            'min' => $basicAnalysis['min'],
            'max' => $basicAnalysis['max'],
            'average' => $basicAnalysis['average'],
            'median' => $basicAnalysis['median']
        ];
        
        // Determine positioning strategy
        $avgPrice = $basicAnalysis['average'];
        
        if ($itemAnalysis['market_positioning'] === 'premium') {
            $analysis['suggested_price'] = $avgPrice * 1.3; // Price above average
            $analysis['positioning_gap'] = 'premium_opportunity';
        } elseif ($itemAnalysis['market_positioning'] === 'budget') {
            $analysis['suggested_price'] = $avgPrice * 0.8; // Price below average
            $analysis['positioning_gap'] = 'budget_opportunity';
        } else {
            $analysis['suggested_price'] = $avgPrice; // Match market average
            $analysis['positioning_gap'] = 'competitive_parity';
        }
    }
    
    return [
        'analysis' => $analysis,
        'suggested_price' => $analysis['suggested_price']
    ];
}

function calculateValueBasedPrice($itemAnalysis, $category) {
    // Base value by category
    $categoryValues = [
        'T-Shirts' => 20.00,
        'Tumblers' => 15.00,
        'Artwork' => 40.00,
        'Sublimation' => 25.00,
        'Window Wraps' => 50.00
    ];
    
    $baseValue = $categoryValues[$category] ?? 25.00;
    
    // Adjust based on features
    foreach ($itemAnalysis['features'] as $feature) {
        if ($feature['market_demand'] === 'high') {
            $baseValue *= 1.3;
        } elseif ($feature['market_demand'] === 'premium') {
            $baseValue *= 1.5;
        }
    }
    
    // Adjust based on uniqueness
    $uniquenessScore = $itemAnalysis['demand_indicators']['uniqueness_score'];
    $baseValue *= (1 + $uniquenessScore);
    
    return $baseValue;
}

function calculatePricingConfidenceMetrics($costPrice, $marketPrice, $competitivePrice, $valuePrice) {
    $confidence = [
        'market' => 0.6,
        'competitive' => 0.5,
        'value' => 0.5,
        'pricing' => 0.6
    ];
    
    // Higher confidence if we have cost price
    if ($costPrice > 0) {
        $confidence['pricing'] = 0.9;
    }
    
    // Higher confidence if we have market data
    if ($marketPrice > 0) {
        $confidence['market'] = 0.9;
    }
    
    // Higher confidence if we have competitive data
    if ($competitivePrice > 0) {
        $confidence['competitive'] = 0.9;
    }
    
    // Higher confidence if value price is reasonable
    if ($valuePrice > 0 && $valuePrice < 1000) {
        $confidence['value'] = 0.8;
    }
    
    return $confidence;
}

function analyzeMarketPositioning($text, $features, $category) {
    $premiumKeywords = ['luxury', 'premium', 'high-end', 'professional', 'artisan', 'custom', 'bespoke'];
    $budgetKeywords = ['basic', 'simple', 'standard', 'economy', 'budget'];
    
    $premiumScore = 0;
    $budgetScore = 0;
    
    foreach ($premiumKeywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            $premiumScore++;
        }
    }
    
    foreach ($budgetKeywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            $budgetScore++;
        }
    }
    
    // Check features for premium indicators
    foreach ($features as $feature) {
        if (in_array($feature['type'], ['custom_design', 'premium_finish', 'handmade', 'embroidery', 'luxury'])) {
            $premiumScore++;
        }
    }
    
    if ($premiumScore > $budgetScore) {
        return 'premium';
    } elseif ($budgetScore > 0) {
        return 'budget';
    } else {
        return 'standard';
    }
}
?> 