<?php
// Suppress all output before JSON header
ob_start();
header('Content-Type: application/json');
require_once 'config.php';
require_once 'ai_providers.php';

// Turn off error display for this API to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// Increase execution time limit for AI API calls
set_time_limit(120); // 2 minutes for complex AI operations
ini_set('max_execution_time', 120);

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

// Extract brand voice and content tone preferences
$preferredBrandVoice = trim($input['brandVoice'] ?? '');
$preferredContentTone = trim($input['contentTone'] ?? '');

// Debug: Log the voice and tone values
error_log("Marketing API - Brand Voice: '$preferredBrandVoice', Content Tone: '$preferredContentTone'");

// Add timestamp for cache busting and ensuring different AI results
$timestamp = microtime(true);
$requestId = uniqid('mkt_', true);
error_log("Marketing API Request ID: $requestId - Timestamp: $timestamp");

// Extract image support flag
$useImages = $input['useImages'] ?? false;

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Item name is required for marketing suggestion.']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Create marketing_suggestions table if it doesn't exist
    $createTableSql = "CREATE TABLE IF NOT EXISTS marketing_suggestions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sku VARCHAR(50) NOT NULL,
        suggested_title VARCHAR(255),
        suggested_description TEXT,
        keywords JSON,
        target_audience VARCHAR(255),
        emotional_triggers JSON,
        psychographic_profile VARCHAR(255),
        demographic_targeting VARCHAR(255),
        selling_points JSON,
        market_positioning VARCHAR(100),
        competitive_advantages JSON,
        unique_selling_points JSON,
        value_propositions JSON,
        brand_voice VARCHAR(100),
        content_tone VARCHAR(100),
        marketing_channels JSON,
        seasonal_relevance VARCHAR(255),
        pricing_psychology VARCHAR(255),
        urgency_factors JSON,
        social_proof_elements JSON,
        call_to_action_suggestions JSON,
        conversion_triggers JSON,
        objection_handlers JSON,
        seo_keywords JSON,
        search_intent VARCHAR(255),
        content_themes JSON,
        customer_benefits JSON,
        pain_points_addressed JSON,
        lifestyle_alignment JSON,
        confidence_score DECIMAL(3,2),
        analysis_factors JSON,
        market_trends JSON,
        recommendation_reasoning TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sku (sku),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTableSql);
    
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
            error_log("Failed to load images for marketing: " . $e->getMessage());
        }
    }
    
    // Generate comprehensive marketing intelligence using sequential AI analysis
    try {
        $aiProviders = new AIProviders();
        
        // Step 1: Analyze images first (if available)
        $imageAnalysisData = [];
        $imageInsights = '';
        if (!empty($images) && $useImages) {
            error_log("Step 1: Analyzing images for enhanced marketing intelligence");
            try {
                $imageAnalysisData = $aiProviders->analyzeImagesForAltText($images, $name, $description, $category);
                // Extract insights from image analysis for marketing use
                $imageInsights = $aiProviders->extractMarketingInsightsFromImages($imageAnalysisData, $name, $category);
                error_log("Image insights extracted: " . substr($imageInsights, 0, 200) . "...");
            } catch (Exception $e) {
                error_log("Image analysis failed, continuing without image insights: " . $e->getMessage());
                $imageInsights = '';
            }
        }
        
        // Step 2: Generate enhanced marketing content using image insights
        error_log("Step 2: Generating marketing content with image insights");
        error_log("AI Parameters - Name: '$name', Category: '$category', Voice: '$preferredBrandVoice', Tone: '$preferredContentTone', Request ID: $requestId");
        
        // Set a shorter timeout for the main AI call to allow fallback
        set_time_limit(90);
        
        $marketingData = $aiProviders->generateEnhancedMarketingContent(
            $name, 
            $description, 
            $category, 
            $imageInsights, 
            $preferredBrandVoice, 
            $preferredContentTone
        );
        
        error_log("AI Response received for Request ID: $requestId - Title: '" . substr($marketingData['title'], 0, 50) . "...'");
        
    } catch (Exception $e) {
        // Fallback to Jon's AI if external API fails
        error_log("External AI Provider failed, using Jon's AI fallback: " . $e->getMessage());
        
        // Restore execution time for local processing
        set_time_limit(60);
        
        $marketingData = generateMarketingIntelligence($name, $description, $category, $pdo, $preferredBrandVoice, $preferredContentTone);
        
        // Generate basic image analysis for fallback
        if (!empty($images) && $useImages) {
            $imageAnalysisData = generateFallbackAltText($images, $name, $category);
        }
        
        error_log("Local fallback completed for Request ID: $requestId");
    }
    
    // Save marketing suggestion to database
    if (!empty($sku)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO marketing_suggestions (
                    sku, suggested_title, suggested_description, keywords, target_audience,
                    emotional_triggers, psychographic_profile, demographic_targeting, selling_points,
                    market_positioning, competitive_advantages, unique_selling_points, value_propositions,
                    brand_voice, content_tone, marketing_channels, seasonal_relevance, pricing_psychology,
                    urgency_factors, social_proof_elements, call_to_action_suggestions, conversion_triggers,
                    objection_handlers, seo_keywords, search_intent, content_themes, customer_benefits,
                    pain_points_addressed, lifestyle_alignment, confidence_score, analysis_factors,
                    market_trends, recommendation_reasoning
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                suggested_title = VALUES(suggested_title),
                suggested_description = VALUES(suggested_description),
                keywords = VALUES(keywords),
                target_audience = VALUES(target_audience),
                emotional_triggers = VALUES(emotional_triggers),
                psychographic_profile = VALUES(psychographic_profile),
                demographic_targeting = VALUES(demographic_targeting),
                selling_points = VALUES(selling_points),
                market_positioning = VALUES(market_positioning),
                competitive_advantages = VALUES(competitive_advantages),
                unique_selling_points = VALUES(unique_selling_points),
                value_propositions = VALUES(value_propositions),
                brand_voice = VALUES(brand_voice),
                content_tone = VALUES(content_tone),
                marketing_channels = VALUES(marketing_channels),
                seasonal_relevance = VALUES(seasonal_relevance),
                pricing_psychology = VALUES(pricing_psychology),
                urgency_factors = VALUES(urgency_factors),
                social_proof_elements = VALUES(social_proof_elements),
                call_to_action_suggestions = VALUES(call_to_action_suggestions),
                conversion_triggers = VALUES(conversion_triggers),
                objection_handlers = VALUES(objection_handlers),
                seo_keywords = VALUES(seo_keywords),
                search_intent = VALUES(search_intent),
                content_themes = VALUES(content_themes),
                customer_benefits = VALUES(customer_benefits),
                pain_points_addressed = VALUES(pain_points_addressed),
                lifestyle_alignment = VALUES(lifestyle_alignment),
                confidence_score = VALUES(confidence_score),
                analysis_factors = VALUES(analysis_factors),
                market_trends = VALUES(market_trends),
                recommendation_reasoning = VALUES(recommendation_reasoning),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $sku,
                $marketingData['title'],
                $marketingData['description'],
                json_encode($marketingData['keywords']),
                $marketingData['target_audience'],
                json_encode($marketingData['emotional_triggers']),
                $marketingData['psychographic_profile'],
                $marketingData['demographic_targeting'],
                json_encode($marketingData['selling_points']),
                $marketingData['market_positioning'],
                json_encode($marketingData['competitive_advantages']),
                json_encode($marketingData['unique_selling_points']),
                json_encode($marketingData['value_propositions']),
                $marketingData['brand_voice'],
                $marketingData['content_tone'],
                json_encode($marketingData['marketing_channels']),
                $marketingData['seasonal_relevance'],
                $marketingData['pricing_psychology'],
                json_encode($marketingData['urgency_factors']),
                json_encode($marketingData['social_proof_elements']),
                json_encode($marketingData['call_to_action_suggestions']),
                json_encode($marketingData['conversion_triggers']),
                json_encode($marketingData['objection_handlers']),
                json_encode($marketingData['seo_keywords']),
                $marketingData['search_intent'],
                json_encode($marketingData['content_themes']),
                json_encode($marketingData['customer_benefits']),
                json_encode($marketingData['pain_points_addressed']),
                json_encode($marketingData['lifestyle_alignment']),
                $marketingData['confidence_score'],
                json_encode($marketingData['analysis_factors']),
                json_encode($marketingData['market_trends']),
                $marketingData['recommendation_reasoning']
            ]);
        } catch (PDOException $e) {
            error_log("Error saving marketing suggestion: " . $e->getMessage());
        }
    }
    
    // Image analysis is now integrated into the sequential AI process above
    $imageAnalysis = $imageAnalysisData;
    
    // Clear any buffered output and send clean JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'title' => $marketingData['title'],
        'description' => $marketingData['description'],
        'keywords' => $marketingData['keywords'],
        'targetAudience' => $marketingData['target_audience'],
        'imageAnalysis' => $imageAnalysis, // New field for image analysis
        'marketingIntelligence' => [
            // Target Audience data
            'demographic_targeting' => $marketingData['demographic_targeting'],
            'psychographic_profile' => $marketingData['psychographic_profile'],
            
            // SEO & Keywords data  
            'seo_keywords' => $marketingData['seo_keywords'],
            'search_intent' => $marketingData['search_intent'],
            'seasonal_relevance' => $marketingData['seasonal_relevance'],
            
            // Selling Points data
            'selling_points' => $marketingData['selling_points'],
            'competitive_advantages' => $marketingData['competitive_advantages'],
            'customer_benefits' => $marketingData['customer_benefits'],
            
            // Conversion data
            'call_to_action_suggestions' => $marketingData['call_to_action_suggestions'],
            'urgency_factors' => $marketingData['urgency_factors'],
            'conversion_triggers' => $marketingData['conversion_triggers'],
            
            // Legacy fields for compatibility
            'emotional_triggers' => $marketingData['emotional_triggers'],
            'marketing_channels' => $marketingData['marketing_channels']
        ],
        'confidence' => $marketingData['confidence_score'],
        'reasoning' => $marketingData['recommendation_reasoning']
    ]);
    
} catch (Exception $e) {
    error_log("Error in suggest_marketing.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}

function generateMarketingIntelligence($name, $description, $category, $pdo, $preferredBrandVoice = '', $preferredContentTone = '') {
            // Debug: Log the voice and tone values in Jon's AI
        error_log("Jon's AI - Preferred Brand Voice: '$preferredBrandVoice', Preferred Content Tone: '$preferredContentTone'");
    
    // Comprehensive item analysis
    $analysis = analyzeItemForMarketing($name, $description, $category);
    
    // Use preferred brand voice and content tone, or determine from analysis
    $brandVoice = !empty($preferredBrandVoice) ? $preferredBrandVoice : determineBrandVoice($category, $analysis);
    $contentTone = !empty($preferredContentTone) ? $preferredContentTone : determineContentTone($category, $analysis);
    
    // Debug: Log the final voice and tone values
            error_log("Jon's AI - Final Brand Voice: '$brandVoice', Final Content Tone: '$contentTone'");
    
    // Generate enhanced title with brand voice influence
    $title = generateEnhancedTitle($name, $category, $analysis, $brandVoice);
    
    // Generate compelling description with brand voice and tone influence
    $enhancedDescription = generateCompellingDescription($name, $description, $category, $analysis, $brandVoice, $contentTone);
    
    // Generate comprehensive marketing data
    return [
        'title' => $title,
        'description' => $enhancedDescription,
        'keywords' => generateSEOKeywords($name, $category, $analysis),
        'target_audience' => identifyTargetAudience($category, $analysis),
        'emotional_triggers' => identifyEmotionalTriggers($category, $analysis),
        'psychographic_profile' => generatePsychographicProfile($category, $analysis),
        'demographic_targeting' => generateDemographicTargeting($category, $analysis),
        'selling_points' => generateSellingPoints($name, $category, $analysis, $brandVoice),
        'market_positioning' => determineMarketPositioning($category, $analysis),
        'competitive_advantages' => identifyCompetitiveAdvantages($category, $analysis),
        'unique_selling_points' => identifyCompetitiveAdvantages($category, $analysis),
        'value_propositions' => identifyCustomerBenefits($category, $analysis),
        'brand_voice' => $brandVoice,
        'content_tone' => $contentTone,
        'marketing_channels' => recommendMarketingChannels($category, $analysis),
        'seasonal_relevance' => analyzeSeasonalRelevance($name, $category, $analysis),
        'pricing_psychology' => analyzePricingPsychology($category, $analysis),
        'urgency_factors' => generateUrgencyFactors($category, $analysis, $contentTone),
        'social_proof_elements' => generateSocialProofElements($category, $analysis),
        'call_to_action_suggestions' => generateCTASuggestions($category, $analysis, $brandVoice, $contentTone),
        'conversion_triggers' => identifyConversionTriggers($category, $analysis, $contentTone),
        'objection_handlers' => generateObjectionHandlers($category, $analysis, $brandVoice),
        'seo_keywords' => generateAdvancedSEOKeywords($name, $category, $analysis),
        'search_intent' => analyzeSearchIntent($name, $category, $analysis),
        'content_themes' => generateContentThemes($category, $analysis),
        'customer_benefits' => identifyCustomerBenefits($category, $analysis),
        'pain_points_addressed' => identifyPainPoints($category, $analysis),
        'lifestyle_alignment' => analyzeLifestyleAlignment($category, $analysis),
        'confidence_score' => calculateMarketingConfidence($analysis, $category),
        'analysis_factors' => $analysis,
        'market_trends' => analyzeMarketTrends($category, $analysis),
        'recommendation_reasoning' => generateMarketingReasoning($name, $category, $analysis, $brandVoice, $contentTone)
    ];
}

function analyzeItemForMarketing($name, $description, $category) {
    $text = strtolower($name . ' ' . $description);
    
    return [
        'materials' => detectMaterials($text),
        'features' => detectFeatures($text),
        'style' => detectStyle($text),
        'quality_indicators' => detectQualityIndicators($text),
        'use_cases' => detectUseCases($text, $category),
        'target_demographics' => detectTargetDemographics($text),
        'emotional_appeals' => detectEmotionalAppeals($text),
        'premium_indicators' => detectPremiumIndicators($text),
        'customization_options' => detectCustomizationOptions($text),
        'seasonal_relevance' => detectSeasonalRelevance($text),
        'gift_potential' => assessGiftPotential($text, $category),
        'durability_indicators' => detectDurabilityIndicators($text),
        'convenience_factors' => detectConvenienceFactors($text, $category)
    ];
}

function generateEnhancedTitle($name, $category, $analysis, $brandVoice = '') {
    $enhancers = [];
    
    // Brand voice influences title style
    $voiceEnhancers = [
        'friendly' => ['Amazing', 'Wonderful', 'Perfect'],
        'professional' => ['Professional', 'Quality', 'Expert'],
        'playful' => ['Fun', 'Awesome', 'Cool'],
        'luxurious' => ['Premium', 'Luxury', 'Elite'],
        'casual' => ['Easy', 'Simple', 'Everyday']
    ];
    
    // Add brand voice enhancer if specified
    if (!empty($brandVoice) && isset($voiceEnhancers[strtolower($brandVoice)])) {
        $enhancers[] = $voiceEnhancers[strtolower($brandVoice)][0];
    }
    
    // Add quality indicators
    if (!empty($analysis['premium_indicators'])) {
        $enhancers[] = 'Premium';
    } elseif (!empty($analysis['quality_indicators'])) {
        $enhancers[] = 'Quality';
    }
    
    // Add material highlights
    if (!empty($analysis['materials'])) {
        $material = ucfirst($analysis['materials'][0]);
        if (!stripos($name, $material)) {
            $enhancers[] = $material;
        }
    }
    
    // Add feature highlights
    if (in_array('custom', $analysis['features'])) {
        $enhancers[] = 'Custom';
    }
    if (in_array('handmade', $analysis['features'])) {
        $enhancers[] = 'Handcrafted';
    }
    
    // Category-specific enhancers
    $categoryEnhancers = [
        'T-Shirts' => ['Comfortable', 'Stylish', 'Soft'],
        'Tumblers' => ['Insulated', 'Travel', 'Durable'],
        'Artwork' => ['Original', 'Vibrant', 'Canvas'],
        'Sublimation' => ['Custom', 'Vibrant', 'Personalized'],
        'Window Wraps' => ['Professional', 'Weather-Resistant', 'Eye-Catching']
    ];
    
    if (isset($categoryEnhancers[$category])) {
        $enhancers = array_merge($enhancers, array_slice($categoryEnhancers[$category], 0, 2));
    }
    
    // Combine enhancers with name
    $enhancers = array_unique($enhancers);
    $enhancers = array_slice($enhancers, 0, 3);
    
    if (!empty($enhancers)) {
        return implode(' ', $enhancers) . ' ' . $name;
    }
    
    return $name;
}

function generateCompellingDescription($name, $currentDescription, $category, $analysis, $brandVoice = '', $contentTone = '') {
    $hooks = generateDescriptionHooks($category, $analysis, $brandVoice);
    $benefits = generateBenefitStatements($category, $analysis, $contentTone);
    $features = generateFeatureHighlights($analysis);
    $closing = generateDescriptionClosing($category, $analysis, $brandVoice, $contentTone);
    
    // Build compelling description with brand voice and tone
    $description = '';
    
    // Opening varies by brand voice
    if (!empty($brandVoice)) {
        $voiceOpeners = [
            'friendly' => 'Discover',
            'professional' => 'Experience',
            'playful' => 'Get ready for',
            'luxurious' => 'Indulge in',
            'casual' => 'Check out'
        ];
        $opener = $voiceOpeners[strtolower($brandVoice)] ?? 'Discover';
        $description = $opener . ' ';
    }
    
    $description .= $hooks[0] . ' ';
    
    if (!empty($features)) {
        $connector = ($contentTone === 'conversational') ? 'It features' : 'Featuring';
        $description .= $connector . ' ' . implode(', ', array_slice($features, 0, 3)) . '. ';
    }
    
    if (!empty($benefits)) {
        $description .= $benefits[0] . ' ';
    }
    
    $description .= $closing;
    
    return $description;
}

// Additional helper functions for comprehensive analysis
function detectMaterials($text) {
    $materials = [];
    $materialMap = [
        'cotton' => ['cotton', '100% cotton', 'organic cotton'],
        'stainless steel' => ['stainless', 'steel', 'metal'],
        'canvas' => ['canvas', 'stretched canvas'],
        'ceramic' => ['ceramic', 'porcelain'],
        'vinyl' => ['vinyl', 'adhesive'],
        'wood' => ['wood', 'wooden', 'bamboo'],
        'glass' => ['glass', 'tempered']
    ];
    
    foreach ($materialMap as $material => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $materials[] = $material;
                break;
            }
        }
    }
    
    return array_unique($materials);
}

function detectFeatures($text) {
    $features = [];
    $featureMap = [
        'insulated' => ['insulated', 'thermal', 'keeps hot', 'keeps cold'],
        'waterproof' => ['waterproof', 'water resistant'],
        'dishwasher safe' => ['dishwasher', 'easy clean'],
        'handmade' => ['handmade', 'hand crafted', 'artisan'],
        'custom' => ['custom', 'personalized', 'customizable'],
        'eco-friendly' => ['eco', 'sustainable', 'green', 'organic'],
        'durable' => ['durable', 'long lasting', 'sturdy'],
        'lightweight' => ['lightweight', 'portable']
    ];
    
    foreach ($featureMap as $feature => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $features[] = $feature;
                break;
            }
        }
    }
    
    return array_unique($features);
}

function generateSEOKeywords($name, $category, $analysis) {
    $keywords = [];
    
    // Category base keywords
    $categoryKeywords = [
        'T-Shirts' => ['t-shirt', 'tee', 'shirt', 'apparel', 'clothing'],
        'Tumblers' => ['tumbler', 'mug', 'drinkware', 'travel mug', 'insulated cup'],
        'Artwork' => ['art', 'print', 'wall art', 'canvas', 'artwork'],
        'Sublimation' => ['sublimation', 'custom print', 'personalized'],
        'Window Wraps' => ['decal', 'vinyl', 'window wrap', 'sticker']
    ];
    
    $keywords = array_merge($keywords, $categoryKeywords[$category] ?? []);
    
    // Add material keywords
    foreach ($analysis['materials'] as $material) {
        $keywords[] = $material;
        $keywords[] = $material . ' ' . strtolower($category);
    }
    
    // Add feature keywords
    foreach ($analysis['features'] as $feature) {
        $keywords[] = str_replace('_', ' ', $feature);
    }
    
    // Add style keywords
    foreach ($analysis['style'] as $style) {
        $keywords[] = $style;
    }
    
    return array_unique(array_slice($keywords, 0, 15));
}

function identifyTargetAudience($category, $analysis) {
    $audienceMap = [
        'T-Shirts' => 'Fashion-conscious individuals, casual wear enthusiasts, gift buyers',
        'Tumblers' => 'Busy professionals, coffee lovers, eco-conscious consumers, travelers',
        'Artwork' => 'Home decorators, art enthusiasts, gift buyers, interior designers',
        'Sublimation' => 'Personalization seekers, gift buyers, event planners',
        'Window Wraps' => 'Business owners, car enthusiasts, advertisers, decorators'
    ];
    
    $baseAudience = $audienceMap[$category] ?? 'General consumers';
    
    // Enhance based on analysis
    if (in_array('premium', $analysis['premium_indicators'])) {
        $baseAudience = 'Affluent ' . strtolower($baseAudience);
    }
    
    if (in_array('eco-friendly', $analysis['features'])) {
        $baseAudience .= ', environmentally conscious buyers';
    }
    
    return $baseAudience;
}

// Continue with more helper functions...
function generateDescriptionHooks($category, $analysis, $brandVoice = '') {
    $baseHooks = [
        'T-Shirts' => [
            'Experience unmatched comfort and style with this premium t-shirt.',
            'Make a statement with this eye-catching and comfortable t-shirt.',
            'Discover your new favorite t-shirt that combines quality with style.'
        ],
        'Tumblers' => [
            'Keep your beverages perfectly temperature-controlled all day long.',
            'Experience the ultimate in portable drinkware convenience.',
            'Transform your daily hydration routine with this premium tumbler.'
        ],
        'Artwork' => [
            'Transform any space with this stunning piece of art.',
            'Add character and personality to your walls with this beautiful artwork.',
            'Bring inspiration and beauty into your home with this captivating piece.'
        ],
        'Sublimation' => [
            'Create something uniquely yours with this custom sublimation piece.',
            'Express your personality with this vibrant, personalized item.',
            'Make it memorable with this custom-designed masterpiece.'
        ],
        'Window Wraps' => [
            'Make a bold statement that captures attention and drives results.',
            'Transform any surface into a powerful marketing tool.',
            'Create eye-catching displays that stand out from the competition.'
        ]
    ];
    
    $hooks = $baseHooks[$category] ?? ['Discover something special with this unique item.'];
    
    // Modify hooks based on brand voice
    if (!empty($brandVoice)) {
        switch (strtolower($brandVoice)) {
            case 'playful':
                $hooks[0] = str_replace(['Experience', 'Discover', 'Transform'], ['Get ready to love', 'Check out', 'Have fun with'], $hooks[0]);
                break;
            case 'luxurious':
                $hooks[0] = str_replace(['this', 'with'], ['this exquisite', 'featuring'], $hooks[0]);
                break;
            case 'casual':
                $hooks[0] = str_replace(['unmatched', 'premium', 'stunning'], ['great', 'quality', 'nice'], $hooks[0]);
                break;
        }
    }
    
    return $hooks;
}

function generateBenefitStatements($category, $analysis, $contentTone = '') {
    $benefits = [
        'T-Shirts' => [
            'Soft, breathable fabric ensures all-day comfort.',
            'Durable construction maintains its shape wash after wash.',
            'Versatile design pairs perfectly with any outfit.'
        ],
        'Tumblers' => [
            'Superior insulation keeps drinks hot for hours or cold all day.',
            'Leak-proof design makes it perfect for travel and daily use.',
            'Ergonomic design fits comfortably in your hand and cup holders.'
        ],
        'Artwork' => [
            'High-quality printing ensures vibrant, long-lasting colors.',
            'Museum-quality materials provide professional presentation.',
            'Ready to display with easy hanging options included.'
        ],
        'Sublimation' => [
            'Vibrant colors that penetrate deep for lasting beauty.',
            'Scratch and fade-resistant finish looks new longer.',
            'Completely customizable to match your vision.'
        ],
        'Window Wraps' => [
            'Weather-resistant materials withstand harsh outdoor conditions.',
            'Easy application with professional bubble-free results.',
            'Removable without damage when you\'re ready for a change.'
        ]
    ];
    
    return $benefits[$category] ?? ['Quality construction ensures lasting satisfaction.'];
}

function generateFeatureHighlights($analysis) {
    $highlights = [];
    
    foreach ($analysis['features'] as $feature) {
        $featureMap = [
            'insulated' => 'advanced insulation technology',
            'waterproof' => 'waterproof protection',
            'dishwasher safe' => 'dishwasher-safe convenience',
            'handmade' => 'artisan craftsmanship',
            'custom' => 'personalization options',
            'eco-friendly' => 'eco-conscious materials',
            'durable' => 'long-lasting durability',
            'lightweight' => 'lightweight portability'
        ];
        
        if (isset($featureMap[$feature])) {
            $highlights[] = $featureMap[$feature];
        }
    }
    
    return $highlights;
}

function generateDescriptionClosing($category, $analysis, $brandVoice = '', $contentTone = '') {
    if (!empty($analysis['gift_potential'])) {
        return 'Perfect as a thoughtful gift or a special treat for yourself.';
    }
    
    $closings = [
        'T-Shirts' => 'Elevate your wardrobe with this must-have addition.',
        'Tumblers' => 'Upgrade your daily routine with this essential companion.',
        'Artwork' => 'Bring inspiration and beauty into your space today.',
        'Sublimation' => 'Create something uniquely yours that tells your story.',
        'Window Wraps' => 'Make your mark with professional-quality results.'
    ];
    
    return $closings[$category] ?? 'Experience the difference quality makes.';
}

// Additional analysis functions would continue here...
function calculateMarketingConfidence($analysis, $category) {
    $confidence = 0.7; // Base confidence
    
    // Increase confidence based on available data
    if (!empty($analysis['materials'])) $confidence += 0.1;
    if (!empty($analysis['features'])) $confidence += 0.1;
    if (!empty($analysis['quality_indicators'])) $confidence += 0.05;
    if (!empty($analysis['use_cases'])) $confidence += 0.05;
    
    return min(0.99, $confidence);
}

function generateMarketingReasoning($name, $category, $analysis, $brandVoice = '', $contentTone = '') {
    $reasoning = "Generated marketing copy based on comprehensive analysis of '{$name}' in the {$category} category. ";
    
    if (!empty($analysis['materials'])) {
        $reasoning .= "Detected materials: " . implode(', ', $analysis['materials']) . ". ";
    }
    
    if (!empty($analysis['features'])) {
        $reasoning .= "Key features identified: " . implode(', ', $analysis['features']) . ". ";
    }
    
    $reasoning .= "Marketing strategy optimized for target audience and conversion potential.";
    
    return $reasoning;
}

// Placeholder functions for comprehensive analysis (would be expanded)
function detectStyle($text) { 
    $styles = [];
    if (strpos($text, 'modern') !== false) $styles[] = 'modern';
    if (strpos($text, 'classic') !== false) $styles[] = 'classic';
    if (strpos($text, 'vintage') !== false) $styles[] = 'vintage';
    return $styles;
}

function detectQualityIndicators($text) { 
    $indicators = [];
    if (strpos($text, 'premium') !== false) $indicators[] = 'premium';
    if (strpos($text, 'quality') !== false) $indicators[] = 'high-quality';
    if (strpos($text, 'durable') !== false) $indicators[] = 'durable';
    return $indicators;
}

function detectUseCases($text, $category) { 
    $useCases = [];
    switch ($category) {
        case 'T-Shirts':
            $useCases = ['casual wear', 'events', 'gifts'];
            break;
        case 'Tumblers':
            $useCases = ['travel', 'office', 'outdoor activities'];
            break;
        case 'Artwork':
            $useCases = ['home decor', 'office decoration', 'gifts'];
            break;
        case 'Sublimation':
            $useCases = ['personalization', 'custom gifts', 'branding'];
            break;
        case 'Window Wraps':
            $useCases = ['business advertising', 'vehicle branding', 'storefront display'];
            break;
    }
    return $useCases;
}

function detectTargetDemographics($text) { 
    $demographics = [];
    if (strpos($text, 'professional') !== false) $demographics[] = 'professionals';
    if (strpos($text, 'business') !== false) $demographics[] = 'business owners';
    if (strpos($text, 'custom') !== false) $demographics[] = 'gift buyers';
    return $demographics;
}

function detectEmotionalAppeals($text) { 
    $appeals = [];
    if (strpos($text, 'unique') !== false) $appeals[] = 'uniqueness';
    if (strpos($text, 'personal') !== false) $appeals[] = 'personalization';
    if (strpos($text, 'quality') !== false) $appeals[] = 'pride';
    return $appeals;
}

function detectPremiumIndicators($text) { 
    $indicators = [];
    if (strpos($text, 'premium') !== false) $indicators[] = 'premium materials';
    if (strpos($text, 'luxury') !== false) $indicators[] = 'luxury finish';
    if (strpos($text, 'professional') !== false) $indicators[] = 'professional grade';
    return $indicators;
}

function detectCustomizationOptions($text) { 
    $options = [];
    if (strpos($text, 'custom') !== false) $options[] = 'custom design';
    if (strpos($text, 'personalized') !== false) $options[] = 'personalization';
    if (strpos($text, 'color') !== false) $options[] = 'color options';
    return $options;
}

function detectSeasonalRelevance($text) { 
    $seasonal = [];
    if (strpos($text, 'holiday') !== false) $seasonal[] = 'holiday season';
    if (strpos($text, 'gift') !== false) $seasonal[] = 'gift season';
    if (strpos($text, 'summer') !== false) $seasonal[] = 'summer';
    return $seasonal;
}

function assessGiftPotential($text, $category) { 
    return in_array($category, ['T-Shirts', 'Tumblers', 'Artwork']) || strpos($text, 'gift') !== false;
}

function detectDurabilityIndicators($text) { 
    $indicators = [];
    if (strpos($text, 'durable') !== false) $indicators[] = 'long-lasting';
    if (strpos($text, 'quality') !== false) $indicators[] = 'quality construction';
    if (strpos($text, 'professional') !== false) $indicators[] = 'professional grade';
    return $indicators;
}

function detectConvenienceFactors($text, $category) { 
    $factors = [];
    switch ($category) {
        case 'Tumblers':
            $factors = ['portable', 'easy to clean', 'fits cup holders'];
            break;
        case 'T-Shirts':
            $factors = ['comfortable', 'easy care', 'versatile'];
            break;
        case 'Artwork':
            $factors = ['ready to hang', 'fade resistant', 'easy installation'];
            break;
    }
    return $factors;
}

function identifyEmotionalTriggers($category, $analysis) { 
    $triggers = ['pride in ownership', 'personal expression'];
    if (!empty($analysis['customization_options'])) {
        $triggers[] = 'uniqueness';
    }
    if (!empty($analysis['gift_potential'])) {
        $triggers[] = 'thoughtful gifting';
    }
    return $triggers;
}
function generateSellingPoints($name, $category, $analysis, $brandVoice = '') {
    $categoryPoints = [
        'T-Shirts' => ['Comfortable 100% cotton blend', 'Durable screen printing', 'Perfect fit for all body types', 'Machine washable'],
        'Tumblers' => ['Double-wall insulation keeps drinks hot/cold', 'Spill-proof lid design', 'Fits most cup holders', 'BPA-free materials'],
        'Artwork' => ['High-quality canvas material', 'Fade-resistant inks', 'Ready to hang hardware included', 'Original designs'],
        'Sublimation' => ['Vibrant, long-lasting colors', 'Scratch and fade resistant', 'Dishwasher safe coating', 'Custom personalization available'],
        'Window Wraps' => ['Weather-resistant vinyl', 'Easy installation process', 'Professional-grade adhesive', 'UV-protected colors']
    ];
    
    $points = $categoryPoints[$category] ?? ['High quality materials', 'Expert craftsmanship', 'Satisfaction guaranteed'];
    
    // Add custom features if detected
    if (in_array('custom', $analysis['features'])) {
        array_unshift($points, 'Fully customizable design');
    }
    if (in_array('handmade', $analysis['features'])) {
        array_unshift($points, 'Handcrafted with attention to detail');
    }
    
    return array_slice($points, 0, 4);
}

function identifyCompetitiveAdvantages($category, $analysis) {
    $categoryAdvantages = [
        'T-Shirts' => ['Local small business support', 'Faster turnaround than big retailers', 'Personal customer service'],
        'Tumblers' => ['Local customization available', 'Supporting small business', 'Unique designs not found elsewhere'],
        'Artwork' => ['Original local artist creations', 'Personal connection with artist', 'One-of-a-kind pieces'],
        'Sublimation' => ['Unlimited customization options', 'Local production means faster delivery', 'Personal design consultation'],
        'Window Wraps' => ['Local installation support', 'Custom sizing available', 'Direct communication with designer']
    ];
    
    return $categoryAdvantages[$category] ?? ['Personalized service', 'Local business support', 'Unique items'];
}

function generateUrgencyFactors($category, $analysis, $contentTone = '') {
    $factors = ['Limited quantity available', 'Custom orders take 3-5 business days', 'Popular design - order soon'];
    
    if ($contentTone === 'urgent') {
        $factors = ['Only few left in stock!', 'Order today for fastest delivery', 'This design is selling fast'];
    }
    
    return $factors;
}

function generateCTASuggestions($category, $analysis, $brandVoice = '', $contentTone = '') {
    $voiceCTAs = [
        'friendly' => ['Get Yours Today!', 'Order Now & Smile!', 'Make It Yours!'],
        'professional' => ['Order Now', 'Purchase Today', 'Add to Cart'],
        'playful' => ['Grab Yours Now!', 'Get It While It\'s Hot!', 'Snag This Deal!'],
        'luxurious' => ['Secure Your Premium Item', 'Invest in Quality', 'Claim Your Exclusive Piece'],
        'casual' => ['Pick One Up', 'Get One Now', 'Add to Cart']
    ];
    
    return $voiceCTAs[$brandVoice] ?? ['Order Now', 'Add to Cart', 'Buy Today'];
}

function identifyConversionTriggers($category, $analysis, $contentTone = '') {
    return [
        'Free local pickup available',
        '100% satisfaction guarantee',
        'Support local small business',
        'Custom designs welcome',
        'Fast turnaround time'
    ];
}

function generateAdvancedSEOKeywords($name, $category, $analysis) {
    $baseKeywords = [];
    $categoryKeywords = [
        'T-Shirts' => ['custom t-shirts', 'personalized shirts', 'local printing', 'screen printed tees'],
        'Tumblers' => ['custom tumblers', 'personalized drinkware', 'insulated cups', 'travel mugs'],
        'Artwork' => ['custom artwork', 'canvas prints', 'local art', 'wall decor'],
        'Sublimation' => ['sublimation printing', 'custom sublimated items', 'personalized gifts'],
        'Window Wraps' => ['window graphics', 'vehicle wraps', 'custom decals', 'storefront graphics']
    ];
    
    $baseKeywords = $categoryKeywords[$category] ?? ['custom items', 'personalized items'];
    
    // Add location keywords
    $locationKeywords = ['local', 'custom', 'personalized', 'handmade', 'small business'];
    
    return array_merge($baseKeywords, $locationKeywords);
}

function analyzeSearchIntent($name, $category, $analysis) {
    // Most custom items have transactional intent
    return 'transactional';
}

function identifyCustomerBenefits($category, $analysis) {
    $categoryBenefits = [
        'T-Shirts' => ['Express your personality', 'Comfortable all-day wear', 'Conversation starter', 'Perfect gift option'],
        'Tumblers' => ['Keep drinks at perfect temperature', 'Reduce single-use cups', 'Show your style', 'Convenient for travel'],
        'Artwork' => ['Transform your space', 'Support local artists', 'Unique home decor', 'Great conversation piece'],
        'Sublimation' => ['Create lasting memories', 'Perfect personalized gifts', 'Durable keepsakes', 'Express creativity'],
        'Window Wraps' => ['Increase business visibility', 'Professional appearance', 'Weather protection', 'Brand recognition']
    ];
    
    return $categoryBenefits[$category] ?? ['High quality item', 'Great value', 'Customer satisfaction'];
}

function generatePsychographicProfile($category, $analysis) {
    $profiles = [
        'T-Shirts' => 'Creative individuals who value self-expression and comfort. They appreciate unique designs and supporting local businesses.',
        'Tumblers' => 'Environmentally conscious people who are always on-the-go. They value practicality and sustainability.',
        'Artwork' => 'Art enthusiasts and home decorators who appreciate original, local creativity and want to support artists.',
        'Sublimation' => 'Gift-givers and memory-makers who value personalization and creating lasting keepsakes.',
        'Window Wraps' => 'Business owners and professionals who understand the importance of visual marketing and brand presence.'
    ];
    
    return $profiles[$category] ?? 'Quality-conscious consumers who appreciate personalized items and local craftsmanship.';
}

function generateDemographicTargeting($category, $analysis) {
    $demographics = [
        'T-Shirts' => 'Ages 16-65, all genders, middle-income households, students, professionals, gift-buyers',
        'Tumblers' => 'Ages 25-55, health-conscious individuals, commuters, office workers, outdoor enthusiasts',
        'Artwork' => 'Ages 30-65, homeowners, art collectors, interior design enthusiasts, gift-buyers',
        'Sublimation' => 'Ages 25-60, parents, grandparents, event planners, gift-givers, memorial keepsake buyers',
        'Window Wraps' => 'Business owners, marketing managers, retail store owners, service providers'
    ];
    
    return $demographics[$category] ?? 'Ages 25-55, middle to upper-middle income, quality-conscious consumers';
}

function analyzeSeasonalRelevance($name, $category, $analysis) {
    $text = strtolower($name);
    
    if (strpos($text, 'christmas') !== false || strpos($text, 'holiday') !== false) {
        return 'Peak demand October-December, especially November-December for Christmas gifts';
    }
    if (strpos($text, 'summer') !== false || strpos($text, 'beach') !== false) {
        return 'Peak demand May-August for summer activities and vacation themes';
    }
    if (strpos($text, 'halloween') !== false || strpos($text, 'spooky') !== false) {
        return 'Peak demand September-October for Halloween celebrations';
    }
    if (strpos($text, 'valentine') !== false || strpos($text, 'love') !== false) {
        return 'Peak demand January-February for Valentine\'s Day gifts';
    }
    
    return 'Year-round appeal with potential seasonal customization opportunities';
}

// Stub functions for compatibility
function determineMarketPositioning($category, $analysis) { return 'Standard'; }
function determineBrandVoice($category, $analysis) { return 'Friendly'; }
function determineContentTone($category, $analysis) { return 'Professional'; }
function recommendMarketingChannels($category, $analysis) { return []; }
function analyzePricingPsychology($category, $analysis) { return 'Value-based'; }
function generateSocialProofElements($category, $analysis) { return []; }
function generateObjectionHandlers($category, $analysis, $brandVoice = '') { return []; }
function generateContentThemes($category, $analysis) { return []; }
function identifyPainPoints($category, $analysis) { return []; }
function analyzeLifestyleAlignment($category, $analysis) { return []; }
function analyzeMarketTrends($category, $analysis) { return []; }

function generateFallbackAltText($images, $name, $category) {
    $altTexts = [];
    foreach ($images as $index => $imagePath) {
        $filename = basename($imagePath);
        $altTexts[] = [
            'image_path' => str_replace(__DIR__ . '/../', '', $imagePath),
            'alt_text' => "Custom {$category} - {$name}" . ($index > 0 ? " (View " . ($index + 1) . ")" : ""),
            'description' => "High-quality {$category} featuring {$name}. Professional item photography showcasing the design and craftsmanship."
        ];
    }
    return $altTexts;
}

?> 