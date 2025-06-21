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
        
        if (!$response || !isset($response['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic response");
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
     * Local AI Integration (existing system)
     */
    private function generateWithLocal($name, $description, $category, $brandVoice, $contentTone) {
        // Use existing local AI functions
        require_once 'suggest_marketing.php';
        return generateMarketingIntelligence($name, $description, $category, $this->pdo, $brandVoice, $contentTone);
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
                    'content' => 'You are a pricing expert for custom crafts. Respond only with valid JSON.'
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
        
        if (!$response || !isset($response['content'][0]['text'])) {
            throw new Exception("Invalid Anthropic pricing response");
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
            throw new Exception("Invalid Google pricing response");
        }
        
        return $this->parsePricingResponse($response['candidates'][0]['content']['parts'][0]['text']);
    }
    
    /**
     * Build marketing prompt for AI
     */
    private function buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone) {
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
- Content Tone: {$contentTone}

Focus on custom crafts, personalized items, and handmade quality. Make it compelling for potential customers.";
    }
    
    /**
     * Build pricing prompt for AI
     */
    private function buildPricingPrompt($name, $description, $category, $costPrice) {
        return "Analyze pricing for a custom craft item. Return ONLY valid JSON with this exact structure:

{
  \"price\": 25.99,
  \"reasoning\": \"detailed pricing explanation\",
  \"confidence\": \"high\",
  \"factors\": [\"factor1\", \"factor2\", \"factor3\"],
  \"analysis\": {
    \"pricing_strategy\": \"value_based\",
    \"market_positioning\": \"premium\",
    \"profit_margin_analysis\": \"margin details\",
    \"competitive_price_range\": \"$20-$30\",
    \"psychological_pricing_notes\": \"pricing psychology\"
  }
}

Product Details:
- Name: {$name}
- Description: {$description}
- Category: {$category}
- Cost Price: \${$costPrice}

Consider materials, labor, market demand, and competition for custom craft items.";
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
            'analysis' => [
                'pricing_strategy' => 'value_based',
                'market_positioning' => 'standard',
                'profit_margin_analysis' => '',
                'competitive_price_range' => '',
                'psychological_pricing_notes' => ''
            ]
        ];
        
        return array_merge($defaults, $data);
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
            'gemini-pro-vision' => 'Gemini Pro Vision'
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
            'gemini-pro-vision' => 'Multimodal capabilities'
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
            ]
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