<?php
// Suppress all output before JSON header
ob_start();
header('Content-Type: application/json');
require_once 'config.php';

// Turn off error display for this API to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

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
        $isAdmin = isset($userData['role']) && strtolower($userData['role']) === 'admin';
    }
}

if (!$isLoggedIn || !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Admin privileges required.']);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Only POST is allowed.']);
    exit;
}

// Get and validate input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input.']);
    exit;
}

// Extract required fields
$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$category = trim($input['category'] ?? '');
$sku = trim($input['sku'] ?? '');

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Item name is required for cost suggestion.']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Initialize cost analysis
    $costData = analyzeCostStructure($name, $description, $category, $pdo);
    
    // Save cost suggestion to database (create table if needed)
    if (!empty($sku)) {
        try {
            // Enhanced cost_suggestions table already exists with proper structure
            
            $stmt = $pdo->prepare("
                INSERT INTO cost_suggestions (
                    sku, suggested_cost, reasoning, confidence, breakdown,
                    detected_materials, detected_features, size_analysis, complexity_score,
                    production_time_estimate, skill_level_required, market_positioning, eco_friendly_score,
                    material_cost_factors, labor_complexity_factors, energy_usage_factors, equipment_requirements,
                    material_confidence, labor_confidence, energy_confidence, equipment_confidence,
                    materials_cost_amount, labor_cost_amount, energy_cost_amount, equipment_cost_amount
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                suggested_cost = VALUES(suggested_cost),
                reasoning = VALUES(reasoning),
                confidence = VALUES(confidence),
                breakdown = VALUES(breakdown),
                detected_materials = VALUES(detected_materials),
                detected_features = VALUES(detected_features),
                size_analysis = VALUES(size_analysis),
                complexity_score = VALUES(complexity_score),
                production_time_estimate = VALUES(production_time_estimate),
                skill_level_required = VALUES(skill_level_required),
                market_positioning = VALUES(market_positioning),
                eco_friendly_score = VALUES(eco_friendly_score),
                material_cost_factors = VALUES(material_cost_factors),
                labor_complexity_factors = VALUES(labor_complexity_factors),
                energy_usage_factors = VALUES(energy_usage_factors),
                equipment_requirements = VALUES(equipment_requirements),
                material_confidence = VALUES(material_confidence),
                labor_confidence = VALUES(labor_confidence),
                energy_confidence = VALUES(energy_confidence),
                equipment_confidence = VALUES(equipment_confidence),
                materials_cost_amount = VALUES(materials_cost_amount),
                labor_cost_amount = VALUES(labor_cost_amount),
                energy_cost_amount = VALUES(energy_cost_amount),
                equipment_cost_amount = VALUES(equipment_cost_amount),
                created_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $sku,
                $costData['cost'],
                $costData['reasoning'],
                $costData['confidence'],
                json_encode($costData['breakdown']),
                json_encode($costData['analysis']['detected_materials']),
                json_encode($costData['analysis']['detected_features']),
                json_encode($costData['analysis']['size_analysis']),
                $costData['analysis']['complexity_score'],
                $costData['analysis']['production_time_estimate'],
                $costData['analysis']['skill_level_required'],
                $costData['analysis']['market_positioning'],
                $costData['analysis']['eco_friendly_score'],
                json_encode($costData['analysis']['material_cost_factors']),
                json_encode($costData['analysis']['labor_complexity_factors']),
                json_encode($costData['analysis']['energy_usage_factors']),
                json_encode($costData['analysis']['equipment_requirements']),
                $costData['analysis']['material_confidence'],
                $costData['analysis']['labor_confidence'],
                $costData['analysis']['energy_confidence'],
                $costData['analysis']['equipment_confidence'],
                $costData['breakdown']['materials'] ?? 0,
                $costData['breakdown']['labor'] ?? 0,
                $costData['breakdown']['energy'] ?? 0,
                $costData['breakdown']['equipment'] ?? 0
            ]);
        } catch (PDOException $e) {
            error_log("Error saving cost suggestion: " . $e->getMessage());
        }
    }
    
    // Clear any buffered output and send clean JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'suggestedCost' => $costData['cost'],
        'reasoning' => $costData['reasoning'],
        'confidence' => $costData['confidence'],
        'breakdown' => $costData['breakdown'],
        'analysis' => $costData['analysis']
    ]);
    
} catch (Exception $e) {
    error_log("Error in suggest_cost.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}

function analyzeCostStructure($name, $description, $category, $pdo) {
    // Load AI settings from database
    $aiSettings = loadAISettings($pdo);
    
    // Enhanced AI product analysis
    $analysis = analyzeProductEnhanced($name, $description, $category);
    
    // Get category-specific base costs
    $baseCosts = getCategoryBaseCosts($category);
    
    // Calculate costs with enhanced analysis
    $materialsCost = calculateMaterialsCostEnhanced($analysis, $baseCosts, $category);
    $laborCost = calculateLaborCostEnhanced($analysis, $baseCosts, $category);
    $energyCost = calculateEnergyCostEnhanced($analysis, $baseCosts, $category);
    $equipmentCost = calculateEquipmentCostEnhanced($analysis, $baseCosts, $category);
    
    // Apply complexity multipliers
    $complexityMultiplier = getComplexityMultiplier($analysis);
    
    // Apply AI settings adjustments
    $temperature = $aiSettings['ai_cost_temperature'];
    $conservativeMode = $aiSettings['ai_conservative_mode'];
    $baseMultiplier = $aiSettings['ai_cost_multiplier_base'];
    
    // Apply base multiplier and temperature variations
    $adjustedMaterialsCost = $materialsCost * $baseMultiplier;
    $adjustedLaborCost = $laborCost * $baseMultiplier;
    $adjustedEnergyCost = $energyCost * $baseMultiplier;
    $adjustedEquipmentCost = $equipmentCost * $baseMultiplier;
    
    // Apply temperature-based variation (lower temperature = less variation)
    if (!$conservativeMode && $temperature > 0.5) {
        $variation = ($temperature - 0.5) * 0.15; // Max 7.5% variation at temp 1.0
        
        $adjustedMaterialsCost *= 1 + (mt_rand(-100, 100) / 1000) * $variation;
        $adjustedLaborCost *= 1 + (mt_rand(-100, 100) / 1000) * $variation;
        $adjustedEnergyCost *= 1 + (mt_rand(-100, 100) / 1000) * $variation;
        $adjustedEquipmentCost *= 1 + (mt_rand(-100, 100) / 1000) * $variation;
    }
    
    // Calculate total cost with adjusted values
    $totalCost = ($adjustedMaterialsCost + $adjustedLaborCost + $adjustedEnergyCost + $adjustedEquipmentCost) * $complexityMultiplier;
    
    // Determine confidence level
    $confidence = determineConfidence($analysis, $category);
    
    // Generate enhanced reasoning
    $reasoning = generateEnhancedCostReasoning($materialsCost, $laborCost, $energyCost, $equipmentCost, $complexityMultiplier, $analysis);
    
    // Create detailed breakdown using adjusted costs
    $breakdown = [
        'materials' => round($adjustedMaterialsCost * $complexityMultiplier, 2),
        'labor' => round($adjustedLaborCost * $complexityMultiplier, 2),
        'energy' => round($adjustedEnergyCost * $complexityMultiplier, 2),
        'equipment' => round($adjustedEquipmentCost * $complexityMultiplier, 2),
        'complexity_multiplier' => $complexityMultiplier,
        'base_total' => round($adjustedMaterialsCost + $adjustedLaborCost + $adjustedEnergyCost + $adjustedEquipmentCost, 2),
        'final_total' => round($totalCost, 2)
    ];
    
    // Enhanced analysis data
    $enhancedAnalysis = [
        'detected_materials' => $analysis['materials'],
        'detected_features' => $analysis['features'],
        'size_analysis' => $analysis['size_analysis'],
        'complexity_score' => $analysis['complexity_score'],
        'production_time_estimate' => $analysis['production_time_estimate'],
        'skill_level_required' => $analysis['skill_level_required'],
        'market_positioning' => $analysis['market_positioning'],
        'eco_friendly_score' => $analysis['eco_friendly_score'],
        'material_cost_factors' => $analysis['material_cost_factors'],
        'labor_complexity_factors' => $analysis['labor_complexity_factors'],
        'energy_usage_factors' => $analysis['energy_usage_factors'],
        'equipment_requirements' => $analysis['equipment_requirements'],
        'material_confidence' => $analysis['material_confidence'],
        'labor_confidence' => $analysis['labor_confidence'],
        'energy_confidence' => $analysis['energy_confidence'],
        'equipment_confidence' => $analysis['equipment_confidence']
    ];
    
    return [
        'cost' => round($totalCost, 2),
        'reasoning' => $reasoning,
        'confidence' => $confidence,
        'breakdown' => $breakdown,
        'analysis' => $enhancedAnalysis
    ];
}

function loadAISettings($pdo) {
    $settings = [
        'ai_cost_temperature' => 0.7,
        'ai_price_temperature' => 0.7,
        'ai_cost_multiplier_base' => 1.0,
        'ai_price_multiplier_base' => 1.0,
        'ai_conservative_mode' => false,
        'ai_market_research_weight' => 0.3,
        'ai_cost_plus_weight' => 0.4,
        'ai_value_based_weight' => 0.3
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, setting_type FROM business_settings WHERE category = 'ai'");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $key = $row['setting_key'];
            $value = $row['setting_value'];
            $type = $row['setting_type'];
            
            // Convert value based on type
            switch ($type) {
                case 'number':
                    $settings[$key] = (float)$value;
                    break;
                case 'boolean':
                    $settings[$key] = in_array(strtolower($value), ['true', '1']);
                    break;
                default:
                    $settings[$key] = $value;
            }
        }
    } catch (Exception $e) {
        error_log("Error loading AI settings: " . $e->getMessage());
    }
    
    return $settings;
}

function analyzeProduct($name, $description) {
    $text = strtolower($name . ' ' . $description);
    
    // Detect materials
    $materials = [];
    $materialKeywords = [
        'cotton' => ['cotton', 'organic cotton', '100% cotton'],
        'polyester' => ['polyester', 'poly', 'synthetic'],
        'canvas' => ['canvas', 'stretched canvas'],
        'vinyl' => ['vinyl', 'htv', 'heat transfer vinyl'],
        'stainless_steel' => ['stainless steel', 'steel', 'metal'],
        'ceramic' => ['ceramic', 'porcelain'],
        'glass' => ['glass', 'tempered glass'],
        'wood' => ['wood', 'wooden', 'bamboo'],
        'paper' => ['paper', 'cardstock', 'photo paper']
    ];
    
    foreach ($materialKeywords as $material => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $materials[] = $material;
                break;
            }
        }
    }
    
    // Detect complexity features
    $features = [];
    $featureKeywords = [
        'custom' => ['custom', 'personalized', 'customized', 'bespoke'],
        'multicolor' => ['multicolor', 'multi-color', 'full color', 'rainbow'],
        'premium' => ['premium', 'luxury', 'high-end', 'professional'],
        'handmade' => ['handmade', 'hand-made', 'artisan', 'crafted'],
        'large_format' => ['large', 'oversized', 'big', 'jumbo'],
        'small_batch' => ['limited', 'small batch', 'exclusive'],
        'detailed' => ['detailed', 'intricate', 'complex', 'elaborate'],
        'sublimation' => ['sublimation', 'sublimated', 'dye sublimation'],
        'embroidered' => ['embroidered', 'embroidery', 'stitched'],
        'engraved' => ['engraved', 'engraving', 'etched', 'laser']
    ];
    
    foreach ($featureKeywords as $feature => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $features[] = $feature;
                break;
            }
        }
    }
    
    // Detect size indicators
    $size = 'standard';
    if (preg_match('/\b(small|mini|tiny)\b/i', $text)) {
        $size = 'small';
    } elseif (preg_match('/\b(large|big|oversized|jumbo)\b/i', $text)) {
        $size = 'large';
    } elseif (preg_match('/\b(xl|xxl|extra large)\b/i', $text)) {
        $size = 'extra_large';
    }
    
    return [
        'materials' => $materials,
        'features' => $features,
        'size' => $size,
        'text_length' => strlen($text),
        'has_description' => !empty(trim($description))
    ];
}

function getCategoryBaseCosts($category) {
    $baseCosts = [
        'T-Shirts' => [
            'materials' => 7.50,  // Blank shirt + basic vinyl
            'labor' => 8.00,      // Design + cutting + pressing
            'energy' => 1.00,     // Heat press + cutting machine
            'equipment' => 1.50   // Equipment depreciation
        ],
        'Tumblers' => [
            'materials' => 12.00, // Tumbler + sublimation materials
            'labor' => 10.00,     // Design + printing + pressing + QC
            'energy' => 2.00,     // Sublimation press + printer
            'equipment' => 2.50   // Equipment depreciation
        ],
        'Artwork' => [
            'materials' => 18.00, // Canvas + ink + coating
            'labor' => 25.00,     // Design + printing + finishing + QC
            'energy' => 4.00,     // Large format printer + drying
            'equipment' => 5.00   // Equipment depreciation
        ],
        'Sublimation' => [
            'materials' => 8.00,  // Base item + sublimation materials
            'labor' => 12.00,     // Design + printing + pressing
            'energy' => 2.50,     // Sublimation equipment
            'equipment' => 3.00   // Equipment depreciation
        ],
        'Window Wraps' => [
            'materials' => 15.00, // Vinyl + transfer materials
            'labor' => 20.00,     // Design + cutting + weeding + application
            'energy' => 1.50,     // Cutting machine + heat gun
            'equipment' => 2.00   // Equipment depreciation
        ]
    ];
    
    // Default costs for unknown categories
    return $baseCosts[$category] ?? [
        'materials' => 10.00,
        'labor' => 15.00,
        'energy' => 2.00,
        'equipment' => 2.50
    ];
}

function calculateMaterialsCost($analysis, $baseCosts, $category) {
    $cost = $baseCosts['materials'];
    
    // Material-specific adjustments
    foreach ($analysis['materials'] as $material) {
        switch ($material) {
            case 'cotton':
                $cost *= 1.1; // Organic cotton premium
                break;
            case 'stainless_steel':
                $cost *= 1.3; // Premium material
                break;
            case 'canvas':
                $cost *= 1.2; // Quality canvas
                break;
            case 'ceramic':
                $cost *= 1.4; // Fragile, premium material
                break;
            case 'glass':
                $cost *= 1.5; // Premium, fragile
                break;
        }
    }
    
    // Feature-based adjustments
    if (in_array('premium', $analysis['features'])) {
        $cost *= 1.4;
    }
    if (in_array('multicolor', $analysis['features'])) {
        $cost *= 1.2; // More ink/materials
    }
    if (in_array('custom', $analysis['features'])) {
        $cost *= 1.1; // Custom materials sourcing
    }
    
    // Size adjustments
    switch ($analysis['size']) {
        case 'small':
            $cost *= 0.8;
            break;
        case 'large':
            $cost *= 1.3;
            break;
        case 'extra_large':
            $cost *= 1.6;
            break;
    }
    
    return $cost;
}

function calculateLaborCost($analysis, $baseCosts, $category) {
    $cost = $baseCosts['labor'];
    
    // Feature-based labor adjustments
    if (in_array('custom', $analysis['features'])) {
        $cost *= 1.5; // Custom work takes longer
    }
    if (in_array('detailed', $analysis['features'])) {
        $cost *= 1.4; // Intricate work
    }
    if (in_array('handmade', $analysis['features'])) {
        $cost *= 1.6; // Hand crafting premium
    }
    if (in_array('multicolor', $analysis['features'])) {
        $cost *= 1.3; // Multiple colors = more setup time
    }
    if (in_array('embroidered', $analysis['features'])) {
        $cost *= 1.8; // Embroidery is labor intensive
    }
    if (in_array('engraved', $analysis['features'])) {
        $cost *= 1.4; // Engraving setup and monitoring
    }
    
    // Size affects labor time
    switch ($analysis['size']) {
        case 'small':
            $cost *= 0.9; // Slightly less handling
            break;
        case 'large':
            $cost *= 1.2; // More handling time
            break;
        case 'extra_large':
            $cost *= 1.4; // Significantly more handling
            break;
    }
    
    // Description length indicates complexity
    if (isset($analysis['has_description']) && $analysis['has_description'] && $analysis['text_length'] > 200) {
        $cost *= 1.1; // Complex items need more planning
    }
    
    return $cost;
}

function calculateEnergyCost($analysis, $baseCosts, $category) {
    $cost = $baseCosts['energy'];
    
    // Feature-based energy adjustments
    if (in_array('sublimation', $analysis['features'])) {
        $cost *= 1.4; // Sublimation uses more energy
    }
    if (in_array('engraved', $analysis['features'])) {
        $cost *= 1.3; // Laser engraving energy intensive
    }
    if (in_array('large_format', $analysis['features'])) {
        $cost *= 1.5; // Large format printing uses more energy
    }
    
    // Size affects energy consumption
    switch ($analysis['size']) {
        case 'small':
            $cost *= 0.8;
            break;
        case 'large':
            $cost *= 1.3;
            break;
        case 'extra_large':
            $cost *= 1.6;
            break;
    }
    
    return $cost;
}

function calculateEquipmentCost($analysis, $baseCosts, $category) {
    $cost = $baseCosts['equipment'];
    
    // Feature-based equipment wear adjustments
    if (in_array('premium', $analysis['features'])) {
        $cost *= 1.2; // Premium work uses equipment more intensively
    }
    if (in_array('engraved', $analysis['features'])) {
        $cost *= 1.4; // Laser equipment depreciation
    }
    if (in_array('embroidered', $analysis['features'])) {
        $cost *= 1.3; // Embroidery machine wear
    }
    
    // Size affects equipment usage
    switch ($analysis['size']) {
        case 'large':
            $cost *= 1.2;
            break;
        case 'extra_large':
            $cost *= 1.4;
            break;
    }
    
    return $cost;
}

function getComplexityMultiplier($analysis) {
    $multiplier = 1.0;
    
    // Base complexity from feature count
    $featureCount = count($analysis['features']);
    if ($featureCount > 3) {
        $multiplier += 0.1 * ($featureCount - 3); // 10% per additional feature beyond 3
    }
    
    // Specific high-complexity features
    $highComplexityFeatures = ['handmade', 'custom', 'detailed', 'small_batch'];
    $highComplexityCount = count(array_intersect($analysis['features'], $highComplexityFeatures));
    $multiplier += $highComplexityCount * 0.15; // 15% per high-complexity feature
    
    // Material complexity
    $complexMaterials = ['ceramic', 'glass', 'stainless_steel'];
    $complexMaterialCount = count(array_intersect($analysis['materials'], $complexMaterials));
    $multiplier += $complexMaterialCount * 0.1; // 10% per complex material
    
    // Cap the multiplier to prevent extreme values
    return min($multiplier, 2.5); // Max 250% of base cost
}

function determineConfidence($analysis, $category) {
    $confidence = 'medium';
    
    // High confidence factors
    $highConfidenceFactors = 0;
    if (!empty($category)) $highConfidenceFactors++;
    if (count($analysis['materials']) > 0) $highConfidenceFactors++;
    if (count($analysis['features']) > 1) $highConfidenceFactors++;
    if (isset($analysis['has_description']) && $analysis['has_description']) $highConfidenceFactors++;
    
    // Low confidence factors
    $lowConfidenceFactors = 0;
    if (empty($category)) $lowConfidenceFactors++;
    if (count($analysis['materials']) == 0) $lowConfidenceFactors++;
    if (!isset($analysis['has_description']) || !$analysis['has_description']) $lowConfidenceFactors++;
    if (in_array('custom', $analysis['features'])) $lowConfidenceFactors++; // Custom items are harder to estimate
    
    if ($highConfidenceFactors >= 3 && $lowConfidenceFactors <= 1) {
        $confidence = 'high';
    } elseif ($lowConfidenceFactors >= 2 || $highConfidenceFactors <= 1) {
        $confidence = 'low';
    }
    
    return $confidence;
}

function generateCostReasoning($materials, $labor, $energy, $equipment, $multiplier, $analysis) {
    $reasoning = "Base cost breakdown: ";
    $reasoning .= "Materials $" . number_format($materials, 2) . " • ";
    $reasoning .= "Labor $" . number_format($labor, 2) . " • ";
    $reasoning .= "Energy $" . number_format($energy, 2) . " • ";
    $reasoning .= "Equipment $" . number_format($equipment, 2);
    
    if ($multiplier > 1.0) {
        $reasoning .= " • Complexity adjustment: +" . number_format(($multiplier - 1) * 100, 0) . "%";
        
        $factors = [];
        if (in_array('custom', $analysis['features'])) $factors[] = "custom work";
        if (in_array('premium', $analysis['features'])) $factors[] = "premium quality";
        if (in_array('handmade', $analysis['features'])) $factors[] = "handcrafted";
        if (in_array('detailed', $analysis['features'])) $factors[] = "detailed work";
        if (count($analysis['features']) > 3) $factors[] = "multiple features";
        
        if (!empty($factors)) {
            $reasoning .= " (" . implode(", ", array_slice($factors, 0, 3)) . ")";
        }
    }
    
    return $reasoning;
}

// Enhanced AI Analysis Functions
function analyzeProductEnhanced($name, $description, $category) {
    $text = strtolower($name . ' ' . $description);
    
    // Enhanced material detection with quality analysis
    $materials = detectMaterialsEnhanced($text);
    
    // Enhanced feature detection
    $features = detectFeaturesEnhanced($text);
    
    // Size and dimension analysis
    $sizeAnalysis = analyzeSizeAndDimensions($text, $category);
    
    // Complexity scoring (0.1 to 2.0)
    $complexityScore = calculateComplexityScore($materials, $features, $sizeAnalysis, $category);
    
    // Production time estimation (in minutes)
    $productionTime = estimateProductionTime($materials, $features, $complexityScore, $category);
    
    // Skill level assessment
    $skillLevel = assessSkillLevel($features, $complexityScore, $category);
    
    // Market positioning analysis
    $marketPositioning = analyzeMarketPositioning($text, $features, $category);
    
    // Eco-friendly scoring (0.0 to 1.0)
    $ecoScore = calculateEcoFriendlyScore($materials, $features);
    
    // Detailed cost factor analysis
    $materialFactors = analyzeMaterialCostFactors($materials, $features, $category);
    $laborFactors = analyzeLaborComplexityFactors($features, $complexityScore, $skillLevel);
    $energyFactors = analyzeEnergyUsageFactors($materials, $features, $productionTime);
    $equipmentFactors = analyzeEquipmentRequirements($materials, $features, $category);
    
    // AI confidence metrics (0.0 to 1.0)
    $confidenceMetrics = calculateConfidenceMetrics($materials, $features, $sizeAnalysis, $category);
    
    return [
        'materials' => $materials,
        'features' => $features,
        'size_analysis' => $sizeAnalysis,
        'complexity_score' => $complexityScore,
        'production_time_estimate' => $productionTime,
        'skill_level_required' => $skillLevel,
        'market_positioning' => $marketPositioning,
        'eco_friendly_score' => $ecoScore,
        'material_cost_factors' => $materialFactors,
        'labor_complexity_factors' => $laborFactors,
        'energy_usage_factors' => $energyFactors,
        'equipment_requirements' => $equipmentFactors,
        'material_confidence' => $confidenceMetrics['material'],
        'labor_confidence' => $confidenceMetrics['labor'],
        'energy_confidence' => $confidenceMetrics['energy'],
        'equipment_confidence' => $confidenceMetrics['equipment']
    ];
}

function detectMaterialsEnhanced($text) {
    $materials = [];
    
    $materialDatabase = [
        'cotton' => [
            'keywords' => ['cotton', 'organic cotton', '100% cotton', 'cotton blend'],
            'quality' => 'standard',
            'cost_factor' => 1.0,
            'eco_score' => 0.7
        ],
        'premium_cotton' => [
            'keywords' => ['organic cotton', 'pima cotton', 'supima cotton', 'combed cotton'],
            'quality' => 'premium',
            'cost_factor' => 1.4,
            'eco_score' => 0.9
        ],
        'polyester' => [
            'keywords' => ['polyester', 'poly', 'synthetic', 'poly-cotton'],
            'quality' => 'standard',
            'cost_factor' => 0.8,
            'eco_score' => 0.3
        ],
        'vinyl' => [
            'keywords' => ['vinyl', 'htv', 'heat transfer vinyl', 'adhesive vinyl'],
            'quality' => 'standard',
            'cost_factor' => 1.2,
            'eco_score' => 0.2
        ],
        'canvas' => [
            'keywords' => ['canvas', 'stretched canvas', 'canvas board', 'cotton canvas'],
            'quality' => 'premium',
            'cost_factor' => 1.5,
            'eco_score' => 0.6
        ],
        'stainless_steel' => [
            'keywords' => ['stainless steel', 'steel', 'metal', 'double wall', 'insulated'],
            'quality' => 'premium',
            'cost_factor' => 2.0,
            'eco_score' => 0.8
        ],
        'ceramic' => [
            'keywords' => ['ceramic', 'porcelain', 'clay'],
            'quality' => 'standard',
            'cost_factor' => 1.3,
            'eco_score' => 0.7
        ],
        'glass' => [
            'keywords' => ['glass', 'tempered glass', 'borosilicate'],
            'quality' => 'premium',
            'cost_factor' => 1.6,
            'eco_score' => 0.8
        ]
    ];
    
    foreach ($materialDatabase as $materialType => $data) {
        foreach ($data['keywords'] as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $materials[] = [
                    'type' => $materialType,
                    'quality' => $data['quality'],
                    'cost_factor' => $data['cost_factor'],
                    'eco_score' => $data['eco_score'],
                    'detected_keyword' => $keyword
                ];
                break;
            }
        }
    }
    
    return $materials;
}

function detectFeaturesEnhanced($text) {
    $features = [];
    
    $featureDatabase = [
        'custom_design' => [
            'keywords' => ['custom', 'personalized', 'customized', 'bespoke', 'made to order'],
            'complexity_impact' => 1.5,
            'time_impact' => 30,
            'skill_requirement' => 'intermediate'
        ],
        'multicolor' => [
            'keywords' => ['multicolor', 'multi-color', 'full color', 'rainbow', 'gradient'],
            'complexity_impact' => 1.3,
            'time_impact' => 15,
            'skill_requirement' => 'intermediate'
        ],
        'premium_finish' => [
            'keywords' => ['premium', 'luxury', 'high-end', 'professional', 'deluxe'],
            'complexity_impact' => 1.4,
            'time_impact' => 20,
            'skill_requirement' => 'advanced'
        ],
        'handmade' => [
            'keywords' => ['handmade', 'hand-made', 'artisan', 'crafted', 'hand crafted'],
            'complexity_impact' => 1.8,
            'time_impact' => 60,
            'skill_requirement' => 'expert'
        ],
        'embroidery' => [
            'keywords' => ['embroidered', 'embroidery', 'stitched', 'sewn'],
            'complexity_impact' => 1.6,
            'time_impact' => 45,
            'skill_requirement' => 'advanced'
        ],
        'sublimation' => [
            'keywords' => ['sublimated', 'sublimation', 'dye sublimation'],
            'complexity_impact' => 1.2,
            'time_impact' => 10,
            'skill_requirement' => 'intermediate'
        ],
        'engraving' => [
            'keywords' => ['engraved', 'engraving', 'etched', 'laser engraved'],
            'complexity_impact' => 1.4,
            'time_impact' => 25,
            'skill_requirement' => 'intermediate'
        ]
    ];
    
    foreach ($featureDatabase as $featureType => $data) {
        foreach ($data['keywords'] as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $features[] = [
                    'type' => $featureType,
                    'complexity_impact' => $data['complexity_impact'],
                    'time_impact' => $data['time_impact'],
                    'skill_requirement' => $data['skill_requirement'],
                    'detected_keyword' => $keyword
                ];
                break;
            }
        }
    }
    
    return $features;
}

function analyzeSizeAndDimensions($text, $category) {
    $sizeAnalysis = [
        'detected_size' => 'standard',
        'size_multiplier' => 1.0,
        'dimensions' => null
    ];
    
    // Size detection patterns
    $sizePatterns = [
        'small' => ['small', 'mini', 'xs', 'extra small', '8oz', '11oz'],
        'standard' => ['standard', 'regular', 'medium', 'md', '15oz', '20oz'],
        'large' => ['large', 'big', 'lg', 'xl', '24oz', '30oz'],
        'extra_large' => ['extra large', 'xxl', 'xxxl', 'jumbo', '40oz']
    ];
    
    foreach ($sizePatterns as $size => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                $sizeAnalysis['detected_size'] = $size;
                
                // Size multipliers for cost calculation
                switch ($size) {
                    case 'small': $sizeAnalysis['size_multiplier'] = 0.8; break;
                    case 'standard': $sizeAnalysis['size_multiplier'] = 1.0; break;
                    case 'large': $sizeAnalysis['size_multiplier'] = 1.3; break;
                    case 'extra_large': $sizeAnalysis['size_multiplier'] = 1.6; break;
                }
                break 2;
            }
        }
    }
    
    // Dimension extraction (basic pattern matching)
    if (preg_match('/(\d+)\s*[x×]\s*(\d+)/', $text, $matches)) {
        $sizeAnalysis['dimensions'] = [
            'width' => intval($matches[1]),
            'height' => intval($matches[2])
        ];
    }
    
    return $sizeAnalysis;
}

function calculateComplexityScore($materials, $features, $sizeAnalysis, $category) {
    $baseScore = 0.5; // Base complexity
    
    // Material complexity
    foreach ($materials as $material) {
        if ($material['quality'] === 'premium') {
            $baseScore += 0.2;
        }
    }
    
    // Feature complexity
    foreach ($features as $feature) {
        $baseScore += ($feature['complexity_impact'] - 1.0) * 0.3;
    }
    
    // Size complexity
    if ($sizeAnalysis['size_multiplier'] > 1.2) {
        $baseScore += 0.1;
    }
    
    // Category base complexity
    $categoryComplexity = [
        'T-Shirts' => 0.3,
        'Tumblers' => 0.4,
        'Artwork' => 0.8,
        'Sublimation' => 0.6,
        'Window Wraps' => 0.7
    ];
    
    $baseScore += $categoryComplexity[$category] ?? 0.5;
    
    return min(2.0, max(0.1, $baseScore));
}

function estimateProductionTime($materials, $features, $complexityScore, $category) {
    // Base time by category (in minutes)
    $baseTimes = [
        'T-Shirts' => 15,
        'Tumblers' => 20,
        'Artwork' => 45,
        'Sublimation' => 25,
        'Window Wraps' => 35
    ];
    
    $baseTime = $baseTimes[$category] ?? 20;
    
    // Add feature time impacts
    foreach ($features as $feature) {
        $baseTime += $feature['time_impact'];
    }
    
    // Apply complexity multiplier
    $totalTime = $baseTime * $complexityScore;
    
    return round($totalTime);
}

function assessSkillLevel($features, $complexityScore, $category) {
    $skillLevels = ['beginner', 'intermediate', 'advanced', 'expert'];
    
    // Base skill by category
    $categorySkills = [
        'T-Shirts' => 'beginner',
        'Tumblers' => 'intermediate',
        'Artwork' => 'advanced',
        'Sublimation' => 'intermediate',
        'Window Wraps' => 'intermediate'
    ];
    
    $baseSkill = $categorySkills[$category] ?? 'intermediate';
    $skillIndex = array_search($baseSkill, $skillLevels);
    
    // Adjust based on features
    foreach ($features as $feature) {
        $featureSkillIndex = array_search($feature['skill_requirement'], $skillLevels);
        if ($featureSkillIndex > $skillIndex) {
            $skillIndex = $featureSkillIndex;
        }
    }
    
    // Adjust based on complexity
    if ($complexityScore > 1.5) {
        $skillIndex = min(3, $skillIndex + 1);
    }
    
    return $skillLevels[$skillIndex];
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
        if (in_array($feature['type'], ['custom_design', 'premium_finish', 'handmade', 'embroidery'])) {
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

function calculateEcoFriendlyScore($materials, $features) {
    if (empty($materials)) return 0.5;
    
    $totalScore = 0;
    $count = 0;
    
    foreach ($materials as $material) {
        $totalScore += $material['eco_score'];
        $count++;
    }
    
    $avgScore = $totalScore / $count;
    
    // Bonus for eco-friendly features
    foreach ($features as $feature) {
        if ($feature['type'] === 'handmade') {
            $avgScore += 0.1;
        }
    }
    
    return min(1.0, max(0.0, $avgScore));
}

function analyzeMaterialCostFactors($materials, $features, $category) {
    $factors = [];
    
    foreach ($materials as $material) {
        $factors[] = [
            'material' => $material['type'],
            'cost_multiplier' => $material['cost_factor'],
            'quality_grade' => $material['quality'],
            'reasoning' => "Material cost factor: {$material['cost_factor']}x"
        ];
    }
    
    return $factors;
}

function analyzeLaborComplexityFactors($features, $complexityScore, $skillLevel) {
    $factors = [
        'complexity_score' => $complexityScore,
        'skill_level' => $skillLevel,
        'feature_impacts' => []
    ];
    
    foreach ($features as $feature) {
        $factors['feature_impacts'][] = [
            'feature' => $feature['type'],
            'time_impact' => $feature['time_impact'],
            'complexity_impact' => $feature['complexity_impact']
        ];
    }
    
    return $factors;
}

function analyzeEnergyUsageFactors($materials, $features, $productionTime) {
    $energyFactors = [
        'production_time' => $productionTime,
        'energy_intensive_processes' => [],
        'estimated_kwh' => 0
    ];
    
    // Check for energy-intensive processes
    foreach ($features as $feature) {
        if (in_array($feature['type'], ['sublimation', 'engraving'])) {
            $energyFactors['energy_intensive_processes'][] = $feature['type'];
            $energyFactors['estimated_kwh'] += 0.5;
        }
    }
    
    // Base energy consumption
    $energyFactors['estimated_kwh'] += ($productionTime / 60) * 0.2;
    
    return $energyFactors;
}

function analyzeEquipmentRequirements($materials, $features, $category) {
    $equipment = [];
    
    // Category-specific base equipment
    $categoryEquipment = [
        'T-Shirts' => ['heat press', 'cutting machine'],
        'Tumblers' => ['sublimation printer', 'heat press', 'oven'],
        'Artwork' => ['printer', 'cutting tools'],
        'Sublimation' => ['sublimation printer', 'heat press'],
        'Window Wraps' => ['large format printer', 'laminator']
    ];
    
    $equipment = $categoryEquipment[$category] ?? ['basic tools'];
    
    // Feature-specific equipment
    foreach ($features as $feature) {
        switch ($feature['type']) {
            case 'engraving':
                $equipment[] = 'laser engraver';
                break;
            case 'embroidery':
                $equipment[] = 'embroidery machine';
                break;
            case 'sublimation':
                $equipment[] = 'sublimation printer';
                break;
        }
    }
    
    return array_unique($equipment);
}

function calculateConfidenceMetrics($materials, $features, $sizeAnalysis, $category) {
    $confidence = [
        'material' => 0.7,
        'labor' => 0.7,
        'energy' => 0.6,
        'equipment' => 0.8
    ];
    
    // Higher confidence if we detected specific materials
    if (!empty($materials)) {
        $confidence['material'] = 0.9;
    }
    
    // Higher confidence if we detected specific features
    if (!empty($features)) {
        $confidence['labor'] = 0.9;
        $confidence['energy'] = 0.8;
    }
    
    // Category-specific confidence adjustments
    if (in_array($category, ['T-Shirts', 'Tumblers'])) {
        $confidence['equipment'] = 0.95;
    }
    
    return $confidence;
}

function generateEnhancedCostReasoning($materials, $labor, $energy, $equipment, $multiplier, $analysis) {
    $reasons = [];
    
    // Base cost components with enhanced details
    $reasons[] = "Materials: $" . number_format($materials, 2) . " (based on " . count($analysis['materials']) . " detected materials)";
    $reasons[] = "Labor: $" . number_format($labor, 2) . " (" . $analysis['skill_level_required'] . " skill, " . $analysis['production_time_estimate'] . " min)";
    $reasons[] = "Energy: $" . number_format($energy, 2) . " (" . number_format($analysis['energy_usage_factors']['estimated_kwh'], 2) . " kWh)";
    $reasons[] = "Equipment: $" . number_format($equipment, 2) . " (" . count($analysis['equipment_requirements']) . " tools required)";
    
    // Complexity factors
    if ($multiplier > 1.0) {
        $reasons[] = "Complexity: " . number_format($analysis['complexity_score'], 1) . "/2.0 (+" . round(($multiplier - 1) * 100, 1) . "%)";
    }
    
    // Market positioning
    $reasons[] = "Positioning: " . ucfirst($analysis['market_positioning']);
    
    // Eco-friendly score
    if ($analysis['eco_friendly_score'] > 0.7) {
        $reasons[] = "Eco-friendly materials detected";
    }
    
    return implode(' • ', $reasons);
}

// Enhanced cost calculation functions
function calculateMaterialsCostEnhanced($analysis, $baseCosts, $category) {
    $materialsCost = $baseCosts['materials'];
    
    foreach ($analysis['materials'] as $material) {
        $materialsCost *= $material['cost_factor'];
    }
    
    return $materialsCost * $analysis['size_analysis']['size_multiplier'];
}

function calculateLaborCostEnhanced($analysis, $baseCosts, $category) {
    $laborCost = $baseCosts['labor'];
    
    // Time-based adjustment
    $timeMultiplier = $analysis['production_time_estimate'] / 20; // 20 min baseline
    $laborCost *= $timeMultiplier;
    
    // Skill level adjustment
    $skillMultipliers = [
        'beginner' => 1.0,
        'intermediate' => 1.3,
        'advanced' => 1.7,
        'expert' => 2.2
    ];
    
    $laborCost *= $skillMultipliers[$analysis['skill_level_required']] ?? 1.3;
    
    return $laborCost;
}

function calculateEnergyCostEnhanced($analysis, $baseCosts, $category) {
    $energyCost = $baseCosts['energy'];
    
    // kWh-based calculation
    $energyCost = $analysis['energy_usage_factors']['estimated_kwh'] * 0.12; // $0.12 per kWh
    
    return max($baseCosts['energy'], $energyCost);
}

function calculateEquipmentCostEnhanced($analysis, $baseCosts, $category) {
    $equipmentCost = $baseCosts['equipment'];
    
    // Equipment complexity multiplier
    $equipmentCount = count($analysis['equipment_requirements']);
    if ($equipmentCount > 2) {
        $equipmentCost *= (1 + ($equipmentCount - 2) * 0.2);
    }
    
    return $equipmentCost;
}
?> 