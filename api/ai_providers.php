<?php
/**
 * AI Providers System for WhimsicalFrog
 * Handles multiple AI providers including local algorithms and external APIs
 */

require_once 'config.php';

class AIProviders {
    private $pdo;
    private $settings;
    
    public function __construct() {
        $this->pdo = null; // Initialize as null, will be created when needed
        $this->settings = $this->loadAISettings();
    }
    
    /**
     * Get PDO connection (lazy loading)
     */
    private function getPDO() {
        if ($this->pdo === null) {
            global $dsn, $user, $pass, $options;
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        }
        return $this->pdo;
    }
    
    /**
     * Load AI settings from database
     */
    private function loadAISettings() {
        $defaults = [
            'ai_provider' => 'local',
            'openai_api_key' => '',
            'openai_model' => 'gpt-3.5-turbo',
            'anthropic_api_key' => '',
            'anthropic_model' => 'claude-3-haiku-20240307',
            'google_api_key' => '',
            'google_model' => 'gemini-pro',
            'meta_api_key' => '',
            'meta_model' => 'llama-3.1-70b-instruct',
            'ai_temperature' => 0.7,
            'ai_max_tokens' => 1000,
            'ai_timeout' => 30,
            'fallback_to_local' => true
        ];
        
        try {
            $pdo = $this->getPDO();
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai'");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Error loading AI settings: " . $e->getMessage());
            // Return defaults if database is not available
        }
        
        return $defaults;
    }
    
    /**
     * Analyze images for alt text generation
     */
    public function analyzeImagesForAltText($images, $name, $description, $category) {
        $provider = $this->settings['ai_provider'];
        
        try {
            switch ($provider) {
                case 'openai':
                    return $this->analyzeImagesWithOpenAI($images, $name, $description, $category);
                case 'anthropic':
                    return $this->analyzeImagesWithAnthropic($images, $name, $description, $category);
                case 'google':
                    return $this->analyzeImagesWithGoogle($images, $name, $description, $category);
                default:
                    // Fallback for providers that don't support image analysis
                    return $this->generateBasicAltText($images, $name, $category);
            }
        } catch (Exception $e) {
            error_log("Image analysis error: " . $e->getMessage());
            return $this->generateBasicAltText($images, $name, $category);
        }
    }

    /**
     * Extract marketing insights from image analysis data
     */
    public function extractMarketingInsightsFromImages($imageAnalysisData, $name, $category) {
        if (empty($imageAnalysisData) || !is_array($imageAnalysisData)) {
            return '';
        }
        
        $insights = [];
        foreach ($imageAnalysisData as $imageData) {
            if (isset($imageData['description'])) {
                $insights[] = $imageData['description'];
            }
        }
        
        if (empty($insights)) {
            return '';
        }
        
        // Combine insights into a comprehensive description
        $combinedInsights = "Visual analysis reveals: " . implode(" Additionally, ", $insights);
        
        // Add context about the product
        $contextualInsights = "Based on the product images for '{$name}' in the {$category} category: {$combinedInsights}";
        
        return $contextualInsights;
    }

    /**
     * Generate enhanced marketing content using image insights
     */
    public function generateEnhancedMarketingContent($name, $description, $category, $imageInsights = '', $brandVoice = '', $contentTone = '') {
        $provider = $this->settings['ai_provider'];
        
        try {
            switch ($provider) {
                case 'openai':
                    return $this->generateEnhancedWithOpenAI($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
                case 'anthropic':
                    return $this->generateEnhancedWithAnthropic($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
                case 'google':
                    return $this->generateEnhancedWithGoogle($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
                case 'meta':
                    return $this->generateEnhancedWithMeta($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
                case 'local':
                default:
                    return $this->generateEnhancedWithLocal($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
            }
        } catch (Exception $e) {
            error_log("Enhanced AI Provider Error ($provider): " . $e->getMessage());
            
            // Fallback to local if enabled
            if ($this->settings['fallback_to_local'] && $provider !== 'local') {
                error_log("Falling back to local AI for enhanced content");
                return $this->generateEnhancedWithLocal($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
            }
            
            throw $e;
        }
    }

    /**
     * Generate marketing content using selected AI provider
     */
    public function generateMarketingContent($name, $description, $category, $brandVoice = '', $contentTone = '') {
        $provider = $this->settings['ai_provider'];
        
        try {
            switch ($provider) {
                case 'openai':
                    return $this->generateWithOpenAI($name, $description, $category, $brandVoice, $contentTone);
                case 'anthropic':
                    return $this->generateWithAnthropic($name, $description, $category, $brandVoice, $contentTone);
                case 'google':
                    return $this->generateWithGoogle($name, $description, $category, $brandVoice, $contentTone);
                case 'meta':
                    return $this->generateWithMeta($name, $description, $category, $brandVoice, $contentTone);
                case 'local':
                default:
                    return $this->generateWithLocal($name, $description, $category, $brandVoice, $contentTone);
            }
        } catch (Exception $e) {
            error_log("AI Provider Error ($provider): " . $e->getMessage());
            
            // Fallback to local if enabled
            if ($this->settings['fallback_to_local'] && $provider !== 'local') {
                error_log("Falling back to local AI");
                return $this->generateWithLocal($name, $description, $category, $brandVoice, $contentTone);
            }
            
            throw $e;
        }
    }
    
    /**
     * Generate pricing suggestions using selected AI provider
     */
    public function generatePricingSuggestion($name, $description, $category, $costPrice) {
        $provider = $this->settings['ai_provider'];
        
        try {
            switch ($provider) {
                case 'openai':
                    return $this->generatePricingWithOpenAI($name, $description, $category, $costPrice);
                case 'anthropic':
                    return $this->generatePricingWithAnthropic($name, $description, $category, $costPrice);
                case 'google':
                    return $this->generatePricingWithGoogle($name, $description, $category, $costPrice);
                case 'meta':
                    return $this->generatePricingWithMeta($name, $description, $category, $costPrice);
                case 'local':
                default:
                    return $this->generatePricingWithLocal($name, $description, $category, $costPrice);
            }
        } catch (Exception $e) {
            error_log("AI Pricing Provider Error ($provider): " . $e->getMessage());
            
            // Fallback to local if enabled
            if ($this->settings['fallback_to_local'] && $provider !== 'local') {
                error_log("Falling back to local pricing AI");
                return $this->generatePricingWithLocal($name, $description, $category, $costPrice);
            }
            
            throw $e;
        }
    }
    
    /**
     * Generate marketing content with image support
     */
    public function generateMarketingContentWithImages($name, $description, $category, $images = [], $brandVoice = '', $contentTone = '') {
        $provider = $this->settings['ai_provider'];
        
        // Check if current model supports images
        if (!empty($images) && !$this->currentModelSupportsImages()) {
            // Fall back to text-only generation
            return $this->generateMarketingContent($name, $description, $category, $brandVoice, $contentTone);
        }
        
        try {
            switch ($provider) {
                case 'openai':
                    return $this->generateWithOpenAIImages($name, $description, $category, $images, $brandVoice, $contentTone);
                case 'anthropic':
                    return $this->generateWithAnthropicImages($name, $description, $category, $images, $brandVoice, $contentTone);
                case 'google':
                    return $this->generateWithGoogleImages($name, $description, $category, $images, $brandVoice, $contentTone);
                case 'meta':
                    return $this->generateWithMetaImages($name, $description, $category, $images, $brandVoice, $contentTone);
                case 'local':
                default:
                    return $this->generateWithLocal($name, $description, $category, $brandVoice, $contentTone);
            }
        } catch (Exception $e) {
            error_log("AI Provider Error with Images ($provider): " . $e->getMessage());
            
            // Fallback to text-only if image processing fails
            return $this->generateMarketingContent($name, $description, $category, $brandVoice, $contentTone);
        }
    }
    
    /**
     * Generate pricing suggestions with image support
     */
    public function generatePricingSuggestionWithImages($name, $description, $category, $costPrice, $images = []) {
        $provider = $this->settings['ai_provider'];
        
        // Check if current model supports images
        if (!empty($images) && !$this->currentModelSupportsImages()) {
            // Fall back to text-only generation
            return $this->generatePricingSuggestion($name, $description, $category, $costPrice);
        }
        
        try {
            switch ($provider) {
                case 'openai':
                    return $this->generatePricingWithOpenAIImages($name, $description, $category, $costPrice, $images);
                case 'anthropic':
                    return $this->generatePricingWithAnthropicImages($name, $description, $category, $costPrice, $images);
                case 'google':
                    return $this->generatePricingWithGoogleImages($name, $description, $category, $costPrice, $images);
                case 'meta':
                    return $this->generatePricingWithMetaImages($name, $description, $category, $costPrice, $images);
                case 'local':
                default:
                    return $this->generatePricingWithLocal($name, $description, $category, $costPrice);
            }
        } catch (Exception $e) {
            error_log("AI Pricing Provider Error with Images ($provider): " . $e->getMessage());
            
            // Fallback to text-only if image processing fails
            return $this->generatePricingSuggestion($name, $description, $category, $costPrice);
        }
    }
    
    /**
     * Check if current model supports images
     */
    private function currentModelSupportsImages() {
        try {
            $pdo = $this->getPDO();
            $provider = $this->settings['ai_provider'];
            $modelKey = $provider . '_model';
            $modelId = $this->settings[$modelKey] ?? 'local-basic';
            
            $stmt = $pdo->prepare("SELECT supports_images FROM ai_models WHERE provider = ? AND model_id = ? AND is_active = 1");
            $stmt->execute([$provider, $modelId]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error checking image support: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert image file to base64 for API calls
     */
    private function imageToBase64($imagePath) {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: " . $imagePath);
        }
        
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new Exception("Failed to read image file: " . $imagePath);
        }
        
        $mimeType = mime_content_type($imagePath);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            throw new Exception("Unsupported image type: " . $mimeType);
        }
        
        return [
            'data' => base64_encode($imageData),
            'mime_type' => $mimeType
        ];
    }
    
    /**
     * OpenAI Integration
     */
    private function generateWithOpenAI($name, $description, $category, $brandVoice, $contentTone) {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }
        
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        
        $data = [
            'model' => $this->settings['openai_model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a marketing expert specializing in custom crafts and personalized items. Respond only with valid JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://api.openai.com/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI response");
        }
        
        return $this->parseAIResponse($response['choices'][0]['message']['content']);
    }
    
    /**
     * Anthropic Integration
     */
    private function generateWithAnthropic($name, $description, $category, $brandVoice, $contentTone) {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Anthropic API key not configured");
        }
        
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        
        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => (int)$this->settings['ai_max_tokens'],
            'temperature' => (float)$this->settings['ai_temperature'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $response = $this->makeAPICall(
            'https://api.anthropic.com/v1/messages',
            $data,
            [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2023-06-01'
            ]
        );
        
        if (!$response) {
            throw new Exception("No response from Anthropic API");
        }
        
        // Handle error responses
        if (isset($response['error'])) {
            $errorMsg = $response['error']['message'] ?? 'Unknown Anthropic API error';
            if (strpos($errorMsg, 'credit balance') !== false) {
                throw new Exception("Anthropic API: Insufficient credits. Please add credits to your account.");
            }
            throw new Exception("Anthropic API Error: " . $errorMsg);
        }
        
        if (!isset($response['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic response format");
        }
        
        return $this->parseAIResponse($response['content'][0]['text']);
    }
    
    /**
     * Google AI Integration
     */
    private function generateWithGoogle($name, $description, $category, $brandVoice, $contentTone) {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Google API key not configured");
        }
        
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)$this->settings['ai_temperature'],
                'maxOutputTokens' => (int)$this->settings['ai_max_tokens']
            ]
        ];
        
        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        
        if (!$response || !isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Google AI response");
        }
        
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text']);
    }
    
    /**
     * Meta (Llama) Integration via OpenRouter
     */
    private function generateWithMeta($name, $description, $category, $brandVoice, $contentTone) {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Meta API key not configured");
        }
        
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        
        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a marketing expert specializing in custom crafts and personalized items. Respond only with valid JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://openrouter.ai/api/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: https://whimsicalfrog.us',
                'X-Title: WhimsicalFrog AI Assistant'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid Meta response");
        }
        
        return $this->parseAIResponse($response['choices'][0]['message']['content']);
    }
    
    /**
     * Local AI Integration (existing system)
     */
    private function generateWithLocal($name, $description, $category, $brandVoice, $contentTone) {
        // Use existing local AI functions
        require_once 'suggest_marketing.php';
        return generateMarketingIntelligence($name, $description, $category, $this->pdo, $brandVoice, $contentTone);
    }

    /**
     * Enhanced Generation Methods (with image insights)
     */
    private function generateEnhancedWithOpenAI($name, $description, $category, $imageInsights, $brandVoice, $contentTone) {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }
        
        $prompt = $this->buildEnhancedMarketingPrompt($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
        
        $data = [
            'model' => $this->settings['openai_model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a marketing expert specializing in custom crafts and personalized items. You have access to detailed visual analysis of the product images. Use this visual information to create more accurate and compelling marketing content. Respond only with valid JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://api.openai.com/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI response");
        }
        
        return $this->parseAIResponse($response['choices'][0]['message']['content']);
    }

    private function generateEnhancedWithAnthropic($name, $description, $category, $imageInsights, $brandVoice, $contentTone) {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Anthropic API key not configured");
        }
        
        $prompt = $this->buildEnhancedMarketingPrompt($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
        
        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => (int)$this->settings['ai_max_tokens'],
            'temperature' => (float)$this->settings['ai_temperature'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $response = $this->makeAPICall(
            'https://api.anthropic.com/v1/messages',
            $data,
            [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2023-06-01'
            ]
        );
        
        if (!$response) {
            throw new Exception("No response from Anthropic API");
        }
        
        if (isset($response['error'])) {
            $errorMsg = $response['error']['message'] ?? 'Unknown Anthropic API error';
            if (strpos($errorMsg, 'credit balance') !== false) {
                throw new Exception("Anthropic API: Insufficient credits. Please add credits to your account.");
            }
            throw new Exception("Anthropic API Error: " . $errorMsg);
        }
        
        if (!isset($response['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic response format");
        }
        
        return $this->parseAIResponse($response['content'][0]['text']);
    }

    private function generateEnhancedWithGoogle($name, $description, $category, $imageInsights, $brandVoice, $contentTone) {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Google API key not configured");
        }
        
        $prompt = $this->buildEnhancedMarketingPrompt($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)$this->settings['ai_temperature'],
                'maxOutputTokens' => (int)$this->settings['ai_max_tokens']
            ]
        ];
        
        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        
        if (!$response || !isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Google AI response");
        }
        
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text']);
    }

    private function generateEnhancedWithMeta($name, $description, $category, $imageInsights, $brandVoice, $contentTone) {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Meta API key not configured");
        }
        
        $prompt = $this->buildEnhancedMarketingPrompt($name, $description, $category, $imageInsights, $brandVoice, $contentTone);
        
        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a marketing expert specializing in custom crafts and personalized items. You have access to detailed visual analysis of the product images. Use this visual information to create more accurate and compelling marketing content. Respond only with valid JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://openrouter.ai/api/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: https://whimsicalfrog.us',
                'X-Title: WhimsicalFrog AI Assistant'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid Meta response");
        }
        
        return $this->parseAIResponse($response['choices'][0]['message']['content']);
    }

    private function generateEnhancedWithLocal($name, $description, $category, $imageInsights, $brandVoice, $contentTone) {
        // Use existing local AI functions with enhanced description
        require_once 'suggest_marketing.php';
        
        // Enhance the description with image insights
        $enhancedDescription = $description;
        if (!empty($imageInsights)) {
            $enhancedDescription .= "\n\nVisual Analysis: " . $imageInsights;
        }
        
        return generateMarketingIntelligence($name, $enhancedDescription, $category, $this->pdo, $brandVoice, $contentTone);
    }
    
    /**
     * Local pricing with existing system
     */
    private function generatePricingWithLocal($name, $description, $category, $costPrice) {
        require_once 'suggest_price.php';
        return analyzePricing($name, $description, $category, $costPrice, $this->pdo);
    }
    
    /**
     * OpenAI Pricing Integration
     */
    private function generatePricingWithOpenAI($name, $description, $category, $costPrice) {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }
        
        $prompt = $this->buildPricingPrompt($name, $description, $category, $costPrice);
        
        $data = [
            'model' => $this->settings['openai_model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a pricing expert for custom crafts and handmade items. You must respond with ONLY valid JSON in the exact format requested. Do not include any additional text, explanations, or markdown formatting outside of the JSON structure. Focus on providing detailed, specific reasoning and comprehensive component breakdowns for pricing analysis.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://api.openai.com/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI pricing response");
        }
        
        return $this->parsePricingResponse($response['choices'][0]['message']['content']);
    }
    
    /**
     * Anthropic Pricing Integration
     */
    private function generatePricingWithAnthropic($name, $description, $category, $costPrice) {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Anthropic API key not configured");
        }
        
        $prompt = $this->buildPricingPrompt($name, $description, $category, $costPrice);
        
        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => (int)$this->settings['ai_max_tokens'],
            'temperature' => (float)$this->settings['ai_temperature'],
            'system' => 'You are a pricing expert for custom crafts and handmade items. You must respond with ONLY valid JSON in the exact format requested. Do not include any additional text, explanations, or markdown formatting outside of the JSON structure. Focus on providing detailed, specific reasoning and comprehensive component breakdowns for pricing analysis.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $response = $this->makeAPICall(
            'https://api.anthropic.com/v1/messages',
            $data,
            [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2023-06-01'
            ]
        );
        
        if (!$response) {
            throw new Exception("No response from Anthropic pricing API");
        }
        
        // Handle error responses
        if (isset($response['error'])) {
            $errorMsg = $response['error']['message'] ?? 'Unknown Anthropic API error';
            if (strpos($errorMsg, 'credit balance') !== false) {
                throw new Exception("Anthropic API: Insufficient credits. Please add credits to your account.");
            }
            throw new Exception("Anthropic Pricing API Error: " . $errorMsg);
        }
        
        if (!isset($response['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic pricing response format");
        }
        
        return $this->parsePricingResponse($response['content'][0]['text']);
    }
    
    /**
     * Google Pricing Integration
     */
    private function generatePricingWithGoogle($name, $description, $category, $costPrice) {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Google API key not configured");
        }
        
        $prompt = $this->buildPricingPrompt($name, $description, $category, $costPrice);
        
        // Add system instruction for Google
        $enhancedPrompt = "You are a pricing expert for custom crafts and handmade items. You must respond with ONLY valid JSON in the exact format requested. Do not include any additional text, explanations, or markdown formatting outside of the JSON structure. Focus on providing detailed, specific reasoning and comprehensive component breakdowns for pricing analysis.\n\n" . $prompt;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $enhancedPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)$this->settings['ai_temperature'],
                'maxOutputTokens' => (int)$this->settings['ai_max_tokens']
            ]
        ];
        
        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        
        if (!$response || !isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Google pricing response");
        }
        
        return $this->parsePricingResponse($response['candidates'][0]['content']['parts'][0]['text']);
    }
    
    /**
     * Meta Pricing Integration
     */
    private function generatePricingWithMeta($name, $description, $category, $costPrice) {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Meta API key not configured");
        }
        
        $prompt = $this->buildPricingPrompt($name, $description, $category, $costPrice);
        
        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a pricing expert for custom crafts and handmade items. You must respond with ONLY valid JSON in the exact format requested. Do not include any additional text, explanations, or markdown formatting outside of the JSON structure. Focus on providing detailed, specific reasoning and comprehensive component breakdowns for pricing analysis.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://openrouter.ai/api/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: https://whimsicalfrog.us',
                'X-Title: WhimsicalFrog AI Assistant'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid Meta pricing response");
        }
        
        return $this->parsePricingResponse($response['choices'][0]['message']['content']);
    }
    
    /**
     * OpenAI Integration with Images
     */
    private function generateWithOpenAIImages($name, $description, $category, $images, $brandVoice, $contentTone) {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }
        
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a marketing expert specializing in custom crafts and personalized items. Analyze the provided images along with text to create better marketing content. Respond only with valid JSON.'
            ],
            [
                'role' => 'user',
                'content' => []
            ]
        ];
        
        // Add text content
        $messages[1]['content'][] = ['type' => 'text', 'text' => $prompt];
        
        // Add images
        foreach ($images as $imagePath) {
            try {
                $imageData = $this->imageToBase64($imagePath);
                $messages[1]['content'][] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$imageData['mime_type']};base64,{$imageData['data']}"
                    ]
                ];
            } catch (Exception $e) {
                error_log("Failed to process image for OpenAI: " . $e->getMessage());
            }
        }
        
        $data = [
            'model' => $this->settings['openai_model'],
            'messages' => $messages,
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://api.openai.com/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI response");
        }
        
        return $this->parseAIResponse($response['choices'][0]['message']['content']);
    }
    
    /**
     * Anthropic Integration with Images
     */
    private function generateWithAnthropicImages($name, $description, $category, $images, $brandVoice, $contentTone) {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Anthropic API key not configured");
        }
        
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        
        $content = [];
        
        // Add text content
        $content[] = ['type' => 'text', 'text' => $prompt];
        
        // Add images
        foreach ($images as $imagePath) {
            try {
                $imageData = $this->imageToBase64($imagePath);
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $imageData['mime_type'],
                        'data' => $imageData['data']
                    ]
                ];
            } catch (Exception $e) {
                error_log("Failed to process image for Anthropic: " . $e->getMessage());
            }
        }
        
        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => (int)$this->settings['ai_max_tokens'],
            'temperature' => (float)$this->settings['ai_temperature'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]
        ];
        
        $response = $this->makeAPICall(
            'https://api.anthropic.com/v1/messages',
            $data,
            [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2023-06-01'
            ]
        );
        
        if (!$response || !isset($response['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic response");
        }
        
        return $this->parseAIResponse($response['content'][0]['text']);
    }
    
    /**
     * Google Integration with Images
     */
    private function generateWithGoogleImages($name, $description, $category, $images, $brandVoice, $contentTone) {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Google API key not configured");
        }
        
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        
        $parts = [];
        
        // Add text content
        $parts[] = ['text' => $prompt];
        
        // Add images
        foreach ($images as $imagePath) {
            try {
                $imageData = $this->imageToBase64($imagePath);
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $imageData['mime_type'],
                        'data' => $imageData['data']
                    ]
                ];
            } catch (Exception $e) {
                error_log("Failed to process image for Google: " . $e->getMessage());
            }
        }
        
        $data = [
            'contents' => [
                [
                    'parts' => $parts
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)$this->settings['ai_temperature'],
                'maxOutputTokens' => (int)$this->settings['ai_max_tokens']
            ]
        ];
        
        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        
        if (!$response || !isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Google AI response");
        }
        
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text']);
    }
    
    /**
     * Meta Integration with Images
     */
    private function generateWithMetaImages($name, $description, $category, $images, $brandVoice, $contentTone) {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Meta API key not configured");
        }
        
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a marketing expert specializing in custom crafts and personalized items. Analyze the provided images along with text to create better marketing content. Respond only with valid JSON.'
            ],
            [
                'role' => 'user',
                'content' => []
            ]
        ];
        
        // Add text content
        $messages[1]['content'][] = ['type' => 'text', 'text' => $prompt];
        
        // Add images
        foreach ($images as $imagePath) {
            try {
                $imageData = $this->imageToBase64($imagePath);
                $messages[1]['content'][] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$imageData['mime_type']};base64,{$imageData['data']}"
                    ]
                ];
            } catch (Exception $e) {
                error_log("Failed to process image for Meta: " . $e->getMessage());
            }
        }
        
        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => $messages,
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://openrouter.ai/api/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: https://whimsicalfrog.us',
                'X-Title: WhimsicalFrog AI Assistant'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid Meta response");
        }
        
        return $this->parseAIResponse($response['choices'][0]['message']['content']);
    }
    
    /**
     * OpenAI Pricing with Images
     */
    private function generatePricingWithOpenAIImages($name, $description, $category, $costPrice, $images) {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }
        
        $prompt = $this->buildPricingPrompt($name, $description, $category, $costPrice);
        
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a pricing expert for custom crafts. Analyze the provided images along with text to suggest better pricing. Respond only with valid JSON.'
            ],
            [
                'role' => 'user',
                'content' => []
            ]
        ];
        
        // Add text content
        $messages[1]['content'][] = ['type' => 'text', 'text' => $prompt];
        
        // Add images
        foreach ($images as $imagePath) {
            try {
                $imageData = $this->imageToBase64($imagePath);
                $messages[1]['content'][] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$imageData['mime_type']};base64,{$imageData['data']}"
                    ]
                ];
            } catch (Exception $e) {
                error_log("Failed to process image for OpenAI pricing: " . $e->getMessage());
            }
        }
        
        $data = [
            'model' => $this->settings['openai_model'],
            'messages' => $messages,
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://api.openai.com/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI pricing response");
        }
        
        return $this->parsePricingResponse($response['choices'][0]['message']['content']);
    }
    
    /**
     * Anthropic Pricing with Images
     */
    private function generatePricingWithAnthropicImages($name, $description, $category, $costPrice, $images) {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Anthropic API key not configured");
        }
        
        $prompt = $this->buildPricingPrompt($name, $description, $category, $costPrice);
        
        $content = [];
        
        // Add text content
        $content[] = ['type' => 'text', 'text' => $prompt];
        
        // Add images
        foreach ($images as $imagePath) {
            try {
                $imageData = $this->imageToBase64($imagePath);
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $imageData['mime_type'],
                        'data' => $imageData['data']
                    ]
                ];
            } catch (Exception $e) {
                error_log("Failed to process image for Anthropic pricing: " . $e->getMessage());
            }
        }
        
        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => (int)$this->settings['ai_max_tokens'],
            'temperature' => (float)$this->settings['ai_temperature'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]
        ];
        
        $response = $this->makeAPICall(
            'https://api.anthropic.com/v1/messages',
            $data,
            [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2023-06-01'
            ]
        );
        
        if (!$response || !isset($response['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic pricing response");
        }
        
        return $this->parsePricingResponse($response['content'][0]['text']);
    }
    
    /**
     * Google Pricing with Images
     */
    private function generatePricingWithGoogleImages($name, $description, $category, $costPrice, $images) {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Google API key not configured");
        }
        
        $prompt = $this->buildPricingPrompt($name, $description, $category, $costPrice);
        
        $parts = [];
        
        // Add text content
        $parts[] = ['text' => $prompt];
        
        // Add images
        foreach ($images as $imagePath) {
            try {
                $imageData = $this->imageToBase64($imagePath);
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $imageData['mime_type'],
                        'data' => $imageData['data']
                    ]
                ];
            } catch (Exception $e) {
                error_log("Failed to process image for Google pricing: " . $e->getMessage());
            }
        }
        
        $data = [
            'contents' => [
                [
                    'parts' => $parts
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)$this->settings['ai_temperature'],
                'maxOutputTokens' => (int)$this->settings['ai_max_tokens']
            ]
        ];
        
        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        
        if (!$response || !isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid Google pricing response");
        }
        
        return $this->parsePricingResponse($response['candidates'][0]['content']['parts'][0]['text']);
    }
    
    /**
     * Meta Pricing with Images
     */
    private function generatePricingWithMetaImages($name, $description, $category, $costPrice, $images) {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Meta API key not configured");
        }
        
        $prompt = $this->buildPricingPrompt($name, $description, $category, $costPrice);
        
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a pricing expert for custom crafts. Analyze the provided images along with text to suggest better pricing. Respond only with valid JSON.'
            ],
            [
                'role' => 'user',
                'content' => []
            ]
        ];
        
        // Add text content
        $messages[1]['content'][] = ['type' => 'text', 'text' => $prompt];
        
        // Add images
        foreach ($images as $imagePath) {
            try {
                $imageData = $this->imageToBase64($imagePath);
                $messages[1]['content'][] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$imageData['mime_type']};base64,{$imageData['data']}"
                    ]
                ];
            } catch (Exception $e) {
                error_log("Failed to process image for Meta pricing: " . $e->getMessage());
            }
        }
        
        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => $messages,
            'temperature' => (float)$this->settings['ai_temperature'],
            'max_tokens' => (int)$this->settings['ai_max_tokens']
        ];
        
        $response = $this->makeAPICall(
            'https://openrouter.ai/api/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: https://whimsicalfrog.us',
                'X-Title: WhimsicalFrog AI Assistant'
            ]
        );
        
        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Invalid Meta pricing response");
        }
        
        return $this->parsePricingResponse($response['choices'][0]['message']['content']);
    }
    
    /**
     * Build enhanced marketing prompt with image insights
     */
    private function buildEnhancedMarketingPrompt($name, $description, $category, $imageInsights, $brandVoice, $contentTone) {
        $voiceText = !empty($brandVoice) ? "Brand Voice: {$brandVoice}" : "Brand Voice: Professional";
        $toneText = !empty($contentTone) ? "Content Tone: {$contentTone}" : "Content Tone: Informative";
        
        // Enhanced voice/tone instructions with stronger emphasis
        $voiceInstruction = !empty($brandVoice) ? "\n\nCRITICAL REQUIREMENT: You MUST use a distinctly {$brandVoice} brand voice throughout ALL content. This voice should dramatically influence the title, description, and all marketing copy. Make the {$brandVoice} personality clearly evident in every sentence." : "";
        $toneInstruction = !empty($contentTone) ? "\n\nCRITICAL REQUIREMENT: You MUST maintain a distinctly {$contentTone} content tone in ALL writing. Every piece of text should strongly reflect this {$contentTone} tone. Be bold and obvious with the tone - don't be subtle." : "";
        
        // Add creativity instruction to force variation
        $creativityBoost = "\n\nIMPORTANT: Create fresh, unique content that stands out. Avoid generic phrases. Be creative and distinctive in your approach. Use unexpected angles and compelling language that captures attention.";
        
        // Add timestamp for uniqueness
        $timestamp = time();
        $uniquenessPrompt = "\n\nGeneration ID: {$timestamp} - Ensure this content is unique and different from previous generations.";
        
        $imageSection = '';
        if (!empty($imageInsights)) {
            $imageSection = "\n\nIMAGE ANALYSIS:\n{$imageInsights}\n\nPlease use this visual information to create more accurate and compelling marketing content that reflects what customers will actually see in the product images.";
        }
        
        return "Generate comprehensive marketing content for a custom craft item using the provided visual analysis. Return ONLY valid JSON with this exact structure:

{
  \"title\": \"enhanced product title\",
  \"description\": \"compelling product description\",
  \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],
  \"target_audience\": \"target audience description\",
  \"selling_points\": [\"point1\", \"point2\", \"point3\"],
  \"competitive_advantages\": [\"advantage1\", \"advantage2\"],
  \"seo_keywords\": [\"seo1\", \"seo2\", \"seo3\"],
  \"call_to_action_suggestions\": [\"cta1\", \"cta2\"],
  \"urgency_factors\": [\"urgency1\", \"urgency2\"],
  \"conversion_triggers\": [\"trigger1\", \"trigger2\"],
  \"demographic_targeting\": \"demographic info\",
  \"psychographic_profile\": \"psychographic info\",
  \"search_intent\": \"transactional\",
  \"seasonal_relevance\": \"seasonal info\",
  \"customer_benefits\": [\"benefit1\", \"benefit2\"],
  \"confidence_score\": 0.85,
  \"reasoning\": \"explanation of suggestions\"
}

Product Details:
- Name: {$name}
- Description: {$description}
- Category: {$category}
- {$voiceText}
- {$toneText}{$voiceInstruction}{$toneInstruction}{$creativityBoost}{$uniquenessPrompt}{$imageSection}

Focus on custom crafts, personalized items, and handmade quality. Use the visual analysis to create more accurate and compelling content.";
    }

    /**
     * Build marketing prompt for AI
     */
    private function buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone) {
        // Enhanced voice/tone instructions with stronger emphasis
        $voiceInstruction = !empty($brandVoice) ? "\n\nCRITICAL REQUIREMENT: You MUST use a distinctly {$brandVoice} brand voice throughout ALL content. This voice should dramatically influence the title, description, and all marketing copy. Make the {$brandVoice} personality clearly evident in every sentence. Be bold and obvious with this voice - don't be subtle." : "";
        $toneInstruction = !empty($contentTone) ? "\n\nCRITICAL REQUIREMENT: You MUST maintain a distinctly {$contentTone} content tone in ALL writing. Every piece of text should strongly reflect this {$contentTone} tone. The tone should be immediately recognizable and consistent throughout." : "";
        
        // Add creativity instruction to force variation
        $creativityBoost = "\n\nIMPORTANT: Create fresh, unique content that stands out. Avoid generic phrases. Be creative and distinctive in your approach. Use unexpected angles and compelling language that captures attention. Make it memorable and engaging.";
        
        // Add timestamp for uniqueness
        $timestamp = time();
        $uniquenessPrompt = "\n\nGeneration ID: {$timestamp} - Ensure this content is unique and different from previous generations. Bring a fresh perspective.";
        
        return "Generate comprehensive marketing content for a custom craft item. Return ONLY valid JSON with this exact structure:

{
  \"title\": \"enhanced product title\",
  \"description\": \"compelling product description\",
  \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],
  \"target_audience\": \"target audience description\",
  \"selling_points\": [\"point1\", \"point2\", \"point3\"],
  \"competitive_advantages\": [\"advantage1\", \"advantage2\"],
  \"seo_keywords\": [\"seo1\", \"seo2\", \"seo3\"],
  \"call_to_action_suggestions\": [\"cta1\", \"cta2\"],
  \"urgency_factors\": [\"urgency1\", \"urgency2\"],
  \"conversion_triggers\": [\"trigger1\", \"trigger2\"],
  \"demographic_targeting\": \"demographic info\",
  \"psychographic_profile\": \"psychographic info\",
  \"search_intent\": \"transactional\",
  \"seasonal_relevance\": \"seasonal info\",
  \"customer_benefits\": [\"benefit1\", \"benefit2\"],
  \"confidence_score\": 0.85,
  \"reasoning\": \"explanation of suggestions\"
}

Product Details:
- Name: {$name}
- Description: {$description}
- Category: {$category}
- Brand Voice: {$brandVoice}
- Content Tone: {$contentTone}{$voiceInstruction}{$toneInstruction}{$creativityBoost}{$uniquenessPrompt}

Focus on custom crafts, personalized items, and handmade quality. Make it compelling for potential customers.";
    }
    
    /**
     * Build pricing prompt for AI
     */
    private function buildPricingPrompt($name, $description, $category, $costPrice) {
        return "You are a pricing expert for custom crafts and handmade items. Analyze the pricing for this item and provide comprehensive reasoning with detailed breakdown components.

Return ONLY valid JSON with this EXACT structure (no additional text):

{
  \"price\": 25.99,
  \"reasoning\": \"Comprehensive explanation of pricing strategy and rationale\",
  \"confidence\": \"high\",
  \"factors\": [\"market_demand\", \"material_costs\", \"labor_time\", \"competition\", \"brand_positioning\"],
  \"components\": [
    {
      \"type\": \"cost_plus_pricing\",
      \"label\": \"Cost-Plus Analysis\",
      \"amount\": 18.50,
      \"explanation\": \"Base cost plus markup for materials and direct labor\"
    },
    {
      \"type\": \"market_research\",
      \"label\": \"Market Research Analysis\",
      \"amount\": 22.00,
      \"explanation\": \"Price based on comparable items in the market\"
    },
    {
      \"type\": \"competitive_analysis\",
      \"label\": \"Competitive Analysis\",
      \"amount\": 24.00,
      \"explanation\": \"Pricing relative to direct competitors\"
    },
    {
      \"type\": \"value_based_pricing\",
      \"label\": \"Value-Based Pricing\",
      \"amount\": 28.00,
      \"explanation\": \"Price based on perceived customer value\"
    },
    {
      \"type\": \"brand_premium\",
      \"label\": \"Brand Premium\",
      \"amount\": 26.50,
      \"explanation\": \"Premium for brand quality and reputation\"
    },
    {
      \"type\": \"psychological_pricing\",
      \"label\": \"Psychological Pricing\",
      \"amount\": 25.99,
      \"explanation\": \"Price point optimized for customer psychology\"
    }
  ],
  \"analysis\": {
    \"pricing_strategy\": \"value_based\",
    \"market_positioning\": \"premium\",
    \"profit_margin_analysis\": \"Detailed margin breakdown and justification\",
    \"competitive_price_range\": \"$20-$30\",
    \"psychological_pricing_notes\": \"Why this specific price point works psychologically\"
  }
}

CRITICAL REQUIREMENTS:
1. The \"components\" array MUST contain 4-6 different pricing approaches with detailed explanations
2. Each component must have a realistic \"amount\" that reflects that pricing method
3. The \"reasoning\" field must be a comprehensive 2-3 sentence explanation
4. All explanations must be specific to this product, not generic
5. Consider the cost price of \${$costPrice} as your baseline

Product Details:
- Name: {$name}
- Description: {$description}
- Category: {$category}
- Cost Price: \${$costPrice}

Analyze this as a custom craft item considering:
- Materials and labor costs
- Market demand for {$category} items
- Competition in the custom crafts market
- Value proposition for personalized/handmade items
- Profit margins typical for small craft businesses
- Customer willingness to pay for quality and customization

Provide detailed, specific reasoning for each pricing component based on the actual product details provided.";
    }
    
    /**
     * Make API call to external service
     */
    private function makeAPICall($url, $data, $headers, $method = 'POST') {
        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int)$this->settings['ai_timeout'],
            CURLOPT_SSL_VERIFYPEER => true
        ];
        
        if ($method === 'POST' && $data) {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'GET') {
            $curlOptions[CURLOPT_HTTPGET] = true;
        }
        
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API Error: HTTP {$httpCode} - " . $response);
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $decoded;
    }
    
    /**
     * Parse AI marketing response
     */
    private function parseAIResponse($content) {
        // Clean up response (remove markdown formatting if present)
        $content = preg_replace('/```json\s*|\s*```/', '', $content);
        $content = trim($content);
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse AI response as JSON: " . json_last_error_msg());
        }
        
        // Ensure required fields exist with defaults
        $defaults = [
            'title' => 'Enhanced Product',
            'description' => 'Quality custom item',
            'keywords' => [],
            'target_audience' => 'General customers',
            'selling_points' => [],
            'competitive_advantages' => [],
            'seo_keywords' => [],
            'call_to_action_suggestions' => ['Buy Now'],
            'urgency_factors' => [],
            'conversion_triggers' => [],
            'demographic_targeting' => '',
            'psychographic_profile' => '',
            'search_intent' => 'transactional',
            'seasonal_relevance' => '',
            'customer_benefits' => [],
            'confidence_score' => 0.7,
            'reasoning' => 'AI-generated marketing content'
        ];
        
        return array_merge($defaults, $data);
    }
    
    /**
     * Parse AI pricing response
     */
    private function parsePricingResponse($content) {
        // Clean up response
        $content = preg_replace('/```json\s*|\s*```/', '', $content);
        $content = trim($content);
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse pricing response as JSON: " . json_last_error_msg());
        }
        
        // Ensure required fields exist with defaults
        $defaults = [
            'price' => 25.00,
            'reasoning' => 'AI-generated pricing',
            'confidence' => 'medium',
            'factors' => [],
            'components' => [], // Add components to defaults
            'analysis' => [
                'pricing_strategy' => 'value_based',
                'market_positioning' => 'standard',
                'profit_margin_analysis' => '',
                'competitive_price_range' => '',
                'psychological_pricing_notes' => ''
            ]
        ];
        
        $result = array_merge($defaults, $data);
        
        // If no components were provided but we have reasoning, create a fallback component
        if (empty($result['components']) && !empty($result['reasoning'])) {
            $result['components'] = [
                [
                    'type' => 'ai_pricing_analysis',
                    'label' => 'AI Pricing Analysis',
                    'amount' => $result['price'],
                    'explanation' => $result['reasoning']
                ]
            ];
        }
        
        return $result;
    }
    
    /**
     * Get available models for a specific provider
     */
    public function getAvailableModels($provider) {
        try {
            switch ($provider) {
                case 'openai':
                    return $this->getOpenAIModels();
                case 'anthropic':
                    return $this->getAnthropicModels();
                case 'google':
                    return $this->getGoogleModels();
                case 'meta':
                    return $this->getMetaModels();
                case 'local':
                    return [
                        ['id' => 'local-ai', 'name' => 'Local AI Algorithm', 'description' => 'Built-in AI system']
                    ];
                default:
                    return [];
            }
        } catch (Exception $e) {
            error_log("Error fetching models for $provider: " . $e->getMessage());
            return $this->getFallbackModels($provider);
        }
    }
    
    /**
     * Get OpenAI available models
     */
    private function getOpenAIModels() {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey)) {
            return $this->getFallbackModels('openai');
        }
        
        try {
            $response = $this->makeAPICall(
                'https://api.openai.com/v1/models',
                null,
                [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                'GET'
            );
            
            if (!$response || !isset($response['data'])) {
                return $this->getFallbackModels('openai');
            }
            
            $models = [];
            foreach ($response['data'] as $model) {
                // Only include chat models that are suitable for our use case
                if (strpos($model['id'], 'gpt-') === 0 && 
                    (strpos($model['id'], 'turbo') !== false || strpos($model['id'], 'gpt-4') === 0)) {
                    $models[] = [
                        'id' => $model['id'],
                        'name' => $this->formatModelName($model['id']),
                        'description' => $this->getModelDescription($model['id'])
                    ];
                }
            }
            
            // Sort by preference
            usort($models, function($a, $b) {
                $order = ['gpt-4o', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo'];
                $aPos = array_search($a['id'], $order);
                $bPos = array_search($b['id'], $order);
                if ($aPos === false) $aPos = 999;
                if ($bPos === false) $bPos = 999;
                return $aPos - $bPos;
            });
            
            return count($models) > 0 ? $models : $this->getFallbackModels('openai');
            
        } catch (Exception $e) {
            error_log("OpenAI models API error: " . $e->getMessage());
            return $this->getFallbackModels('openai');
        }
    }
    
    /**
     * Get Anthropic available models
     */
    private function getAnthropicModels() {
        // Anthropic doesn't have a public models API, so we return known models
        // These are updated as of 2024 and should be current
        return [
            [
                'id' => 'claude-3-5-sonnet-20241022',
                'name' => 'Claude 3.5 Sonnet',
                'description' => 'Most intelligent model, best for complex tasks'
            ],
            [
                'id' => 'claude-3-5-haiku-20241022',
                'name' => 'Claude 3.5 Haiku',
                'description' => 'Fastest model, good for simple tasks'
            ],
            [
                'id' => 'claude-3-opus-20240229',
                'name' => 'Claude 3 Opus',
                'description' => 'Most capable model for complex reasoning'
            ],
            [
                'id' => 'claude-3-sonnet-20240229',
                'name' => 'Claude 3 Sonnet',
                'description' => 'Balanced performance and speed'
            ],
            [
                'id' => 'claude-3-haiku-20240307',
                'name' => 'Claude 3 Haiku',
                'description' => 'Fast and affordable'
            ]
        ];
    }
    
    /**
     * Get Google AI available models
     */
    private function getGoogleModels() {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey)) {
            return $this->getFallbackModels('google');
        }
        
        try {
            $response = $this->makeAPICall(
                'https://generativelanguage.googleapis.com/v1/models?key=' . $apiKey,
                null,
                ['Content-Type: application/json'],
                'GET'
            );
            
            if (!$response || !isset($response['models'])) {
                return $this->getFallbackModels('google');
            }
            
            $models = [];
            foreach ($response['models'] as $model) {
                // Only include models that support text generation
                if (isset($model['supportedGenerationMethods']) && 
                    in_array('generateContent', $model['supportedGenerationMethods'])) {
                    $modelId = str_replace('models/', '', $model['name']);
                    $models[] = [
                        'id' => $modelId,
                        'name' => $this->formatModelName($modelId),
                        'description' => $model['description'] ?? 'Google AI model'
                    ];
                }
            }
            
            return count($models) > 0 ? $models : $this->getFallbackModels('google');
            
        } catch (Exception $e) {
            error_log("Google AI models API error: " . $e->getMessage());
            return $this->getFallbackModels('google');
        }
    }
    
    /**
     * Get Meta (Llama) available models via OpenRouter
     */
    private function getMetaModels() {
        // Return popular Meta/Llama models available on OpenRouter
        return [
            [
                'id' => 'meta-llama/llama-3.1-405b-instruct',
                'name' => 'Llama 3.1 405B Instruct',
                'description' => 'Most capable Llama model for complex reasoning'
            ],
            [
                'id' => 'meta-llama/llama-3.1-70b-instruct',
                'name' => 'Llama 3.1 70B Instruct',
                'description' => 'Balanced performance and cost'
            ],
            [
                'id' => 'meta-llama/llama-3.1-8b-instruct',
                'name' => 'Llama 3.1 8B Instruct',
                'description' => 'Fast and affordable option'
            ],
            [
                'id' => 'meta-llama/llama-3-70b-instruct',
                'name' => 'Llama 3 70B Instruct',
                'description' => 'Previous generation, reliable performance'
            ],
            [
                'id' => 'meta-llama/llama-3-8b-instruct',
                'name' => 'Llama 3 8B Instruct',
                'description' => 'Lightweight and efficient'
            ]
        ];
    }
    
    /**
     * Get fallback models when API is unavailable
     */
    private function getFallbackModels($provider) {
        switch ($provider) {
            case 'openai':
                return [
                    ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'description' => 'Latest and most capable model'],
                    ['id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'description' => 'Fast and capable'],
                    ['id' => 'gpt-4', 'name' => 'GPT-4', 'description' => 'Highly capable model'],
                    ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo', 'description' => 'Fast and affordable']
                ];
            case 'anthropic':
                return $this->getAnthropicModels(); // Always return static list
            case 'google':
                return [
                    ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'description' => 'Most capable Gemini model'],
                    ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'description' => 'Fast and efficient'],
                    ['id' => 'gemini-pro', 'name' => 'Gemini Pro', 'description' => 'Balanced performance'],
                    ['id' => 'gemini-pro-vision', 'name' => 'Gemini Pro Vision', 'description' => 'Multimodal capabilities']
                ];
            case 'meta':
                return $this->getMetaModels();
            default:
                return [];
        }
    }
    
    /**
     * Format model name for display
     */
    private function formatModelName($modelId) {
        $names = [
            'gpt-4o' => 'GPT-4o',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
            'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku',
            'claude-3-opus-20240229' => 'Claude 3 Opus',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash',
            'gemini-pro' => 'Gemini Pro',
            'gemini-pro-vision' => 'Gemini Pro Vision',
            'meta-llama/llama-3.1-405b-instruct' => 'Llama 3.1 405B Instruct',
            'meta-llama/llama-3.1-70b-instruct' => 'Llama 3.1 70B Instruct',
            'meta-llama/llama-3.1-8b-instruct' => 'Llama 3.1 8B Instruct',
            'meta-llama/llama-3-70b-instruct' => 'Llama 3 70B Instruct',
            'meta-llama/llama-3-8b-instruct' => 'Llama 3 8B Instruct'
        ];
        
        return $names[$modelId] ?? ucwords(str_replace(['-', '_'], ' ', $modelId));
    }
    
    /**
     * Get model description
     */
    private function getModelDescription($modelId) {
        $descriptions = [
            'gpt-4o' => 'Latest and most capable model',
            'gpt-4-turbo' => 'Fast and capable with large context',
            'gpt-4' => 'Highly capable for complex tasks',
            'gpt-3.5-turbo' => 'Fast and affordable for most tasks',
            'claude-3-5-sonnet-20241022' => 'Most intelligent model, best for complex tasks',
            'claude-3-5-haiku-20241022' => 'Fastest model, good for simple tasks',
            'claude-3-opus-20240229' => 'Most capable model for complex reasoning',
            'claude-3-sonnet-20240229' => 'Balanced performance and speed',
            'claude-3-haiku-20240307' => 'Fast and affordable',
            'gemini-1.5-pro' => 'Most capable Gemini model',
            'gemini-1.5-flash' => 'Fast and efficient',
            'gemini-pro' => 'Balanced performance',
            'gemini-pro-vision' => 'Multimodal capabilities',
            'meta-llama/llama-3.1-405b-instruct' => 'Most capable Llama model for complex reasoning',
            'meta-llama/llama-3.1-70b-instruct' => 'Balanced performance and cost',
            'meta-llama/llama-3.1-8b-instruct' => 'Fast and affordable option',
            'meta-llama/llama-3-70b-instruct' => 'Previous generation, reliable performance',
            'meta-llama/llama-3-8b-instruct' => 'Lightweight and efficient'
        ];
        
        return $descriptions[$modelId] ?? 'AI model';
    }

    /**
     * Test AI provider connection
     */
    public function testProvider($provider) {
        try {
            switch ($provider) {
                case 'openai':
                    if (empty($this->settings['openai_api_key'])) {
                        return ['success' => false, 'message' => 'OpenAI API key not configured'];
                    }
                    $result = $this->generateWithOpenAI('Test Product', 'Test description', 'T-Shirts', '', '');
                    break;
                    
                case 'anthropic':
                    if (empty($this->settings['anthropic_api_key'])) {
                        return ['success' => false, 'message' => 'Anthropic API key not configured'];
                    }
                    $result = $this->generateWithAnthropic('Test Product', 'Test description', 'T-Shirts', '', '');
                    break;
                    
                case 'google':
                    if (empty($this->settings['google_api_key'])) {
                        return ['success' => false, 'message' => 'Google API key not configured'];
                    }
                    $result = $this->generateWithGoogle('Test Product', 'Test description', 'T-Shirts', '', '');
                    break;
                    
                case 'meta':
                    if (empty($this->settings['meta_api_key'])) {
                        return ['success' => false, 'message' => 'Meta API key not configured'];
                    }
                    $result = $this->generateWithMeta('Test Product', 'Test description', 'T-Shirts', '', '');
                    break;
                    
                case 'local':
                default:
                    $result = $this->generateWithLocal('Test Product', 'Test description', 'T-Shirts', '', '');
                    break;
            }
            
            return ['success' => true, 'message' => 'Provider test successful', 'data' => $result];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Provider test failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get available AI providers
     */
    public function getAvailableProviders() {
        return [
            'local' => [
                'name' => 'Local AI (Algorithm-based)',
                'description' => 'Fast, reliable, cost-free algorithm-based AI',
                'cost' => 'Free',
                'speed' => 'Very Fast',
                'requires_api_key' => false
            ],
            'openai' => [
                'name' => 'OpenAI (ChatGPT)',
                'description' => 'Advanced language model from OpenAI',
                'cost' => 'Pay per use',
                'speed' => 'Fast',
                'requires_api_key' => true,
                'models' => ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo']
            ],
            'anthropic' => [
                'name' => 'Anthropic (Claude)',
                'description' => 'Helpful, harmless, and honest AI assistant',
                'cost' => 'Pay per use',
                'speed' => 'Fast',
                'requires_api_key' => true,
                'models' => ['claude-3-haiku-20240307', 'claude-3-sonnet-20240229', 'claude-3-opus-20240229']
            ],
            'google' => [
                'name' => 'Google AI (Gemini)',
                'description' => 'Google\'s multimodal AI model',
                'cost' => 'Pay per use',
                'speed' => 'Fast',
                'requires_api_key' => true,
                'models' => ['gemini-pro', 'gemini-pro-vision']
            ],
            'meta' => [
                'name' => 'Meta AI (Llama)',
                'description' => 'Open-source large language model via OpenRouter',
                'cost' => 'Pay per use',
                'speed' => 'Fast',
                'requires_api_key' => true,
                'models' => ['meta-llama/llama-3.1-70b-instruct', 'meta-llama/llama-3.1-8b-instruct']
            ]
        ];
    }

    /**
     * Image Analysis Methods
     */
    private function analyzeImagesWithOpenAI($images, $name, $description, $category) {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }

        $altTexts = [];
        foreach ($images as $index => $imagePath) {
            $imageBase64 = base64_encode(file_get_contents($imagePath));
            $imageExtension = pathinfo($imagePath, PATHINFO_EXTENSION);
            $mimeType = "image/" . ($imageExtension === 'jpg' ? 'jpeg' : $imageExtension);

            $prompt = "Analyze this product image and generate a descriptive alt text for accessibility and SEO. The product is: {$name} (Category: {$category}). Description: {$description}. 

Respond with JSON in this format:
{
  \"alt_text\": \"Brief, descriptive alt text (under 125 characters)\",
  \"description\": \"Detailed description of what's visible in the image\"
}";

            $data = [
                'model' => 'gpt-4o', // Use vision model
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageBase64}"
                                ]
                            ]
                        ]
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 300
            ];

            $response = $this->makeAPICall(
                'https://api.openai.com/v1/chat/completions',
                $data,
                [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ]
            );

            if ($response && isset($response['choices'][0]['message']['content'])) {
                $analysis = json_decode($response['choices'][0]['message']['content'], true);
                if ($analysis) {
                    $altTexts[] = [
                        'image_path' => str_replace(__DIR__ . '/../', '', $imagePath),
                        'alt_text' => $analysis['alt_text'] ?? "Custom {$category} - {$name}",
                        'description' => $analysis['description'] ?? "Product image showing {$name}"
                    ];
                } else {
                    // Fallback if JSON parsing fails
                    $altTexts[] = $this->generateBasicAltTextForImage($imagePath, $name, $category, $index);
                }
            } else {
                $altTexts[] = $this->generateBasicAltTextForImage($imagePath, $name, $category, $index);
            }
        }

        return $altTexts;
    }

    private function analyzeImagesWithAnthropic($images, $name, $description, $category) {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Anthropic API key not configured");
        }

        $altTexts = [];
        foreach ($images as $index => $imagePath) {
            $imageBase64 = base64_encode(file_get_contents($imagePath));
            $imageExtension = pathinfo($imagePath, PATHINFO_EXTENSION);
            $mimeType = "image/" . ($imageExtension === 'jpg' ? 'jpeg' : $imageExtension);

            $prompt = "Analyze this product image and generate a descriptive alt text for accessibility and SEO. The product is: {$name} (Category: {$category}). Description: {$description}. 

Respond with JSON in this format:
{
  \"alt_text\": \"Brief, descriptive alt text (under 125 characters)\",
  \"description\": \"Detailed description of what's visible in the image\"
}";

            $data = [
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => 300,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mimeType,
                                    'data' => $imageBase64
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $response = $this->makeAPICall(
                'https://api.anthropic.com/v1/messages',
                $data,
                [
                    'x-api-key: ' . $apiKey,
                    'Content-Type: application/json',
                    'anthropic-version: 2023-06-01'
                ]
            );

            if ($response && isset($response['content'][0]['text'])) {
                $analysis = json_decode($response['content'][0]['text'], true);
                if ($analysis) {
                    $altTexts[] = [
                        'image_path' => str_replace(__DIR__ . '/../', '', $imagePath),
                        'alt_text' => $analysis['alt_text'] ?? "Custom {$category} - {$name}",
                        'description' => $analysis['description'] ?? "Product image showing {$name}"
                    ];
                } else {
                    $altTexts[] = $this->generateBasicAltTextForImage($imagePath, $name, $category, $index);
                }
            } else {
                $altTexts[] = $this->generateBasicAltTextForImage($imagePath, $name, $category, $index);
            }
        }

        return $altTexts;
    }

    private function analyzeImagesWithGoogle($images, $name, $description, $category) {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Google API key not configured");
        }

        $altTexts = [];
        foreach ($images as $index => $imagePath) {
            $imageBase64 = base64_encode(file_get_contents($imagePath));
            $imageExtension = pathinfo($imagePath, PATHINFO_EXTENSION);
            $mimeType = "image/" . ($imageExtension === 'jpg' ? 'jpeg' : $imageExtension);

            $prompt = "Analyze this product image and generate a descriptive alt text for accessibility and SEO. The product is: {$name} (Category: {$category}). Description: {$description}. 

Respond with JSON in this format:
{
  \"alt_text\": \"Brief, descriptive alt text (under 125 characters)\",
  \"description\": \"Detailed description of what's visible in the image\"
}";

            $data = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $imageBase64
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 300
                ]
            ];

            $model = 'gemini-1.5-flash'; // Use vision model
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);

            if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                $analysis = json_decode($response['candidates'][0]['content']['parts'][0]['text'], true);
                if ($analysis) {
                    $altTexts[] = [
                        'image_path' => str_replace(__DIR__ . '/../', '', $imagePath),
                        'alt_text' => $analysis['alt_text'] ?? "Custom {$category} - {$name}",
                        'description' => $analysis['description'] ?? "Product image showing {$name}"
                    ];
                } else {
                    $altTexts[] = $this->generateBasicAltTextForImage($imagePath, $name, $category, $index);
                }
            } else {
                $altTexts[] = $this->generateBasicAltTextForImage($imagePath, $name, $category, $index);
            }
        }

        return $altTexts;
    }

    private function generateBasicAltText($images, $name, $category) {
        $altTexts = [];
        foreach ($images as $index => $imagePath) {
            $altTexts[] = $this->generateBasicAltTextForImage($imagePath, $name, $category, $index);
        }
        return $altTexts;
    }

    private function generateBasicAltTextForImage($imagePath, $name, $category, $index) {
        return [
            'image_path' => str_replace(__DIR__ . '/../', '', $imagePath),
            'alt_text' => "Custom {$category} - {$name}" . ($index > 0 ? " (View " . ($index + 1) . ")" : ""),
            'description' => "High-quality {$category} featuring {$name}. Professional product photography showcasing the design and craftsmanship."
        ];
    }
}

// Global instance
$GLOBALS['aiProviders'] = new AIProviders();

// Helper functions
function getAIProviders() {
    return $GLOBALS['aiProviders'];
}

function generateAIMarketingContent($name, $description, $category, $brandVoice = '', $contentTone = '') {
    return $GLOBALS['aiProviders']->generateMarketingContent($name, $description, $category, $brandVoice, $contentTone);
}

function generateAIPricingSuggestion($name, $description, $category, $costPrice) {
    return $GLOBALS['aiProviders']->generatePricingSuggestion($name, $description, $category, $costPrice);
}

?> 