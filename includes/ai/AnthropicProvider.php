<?php
// includes/ai/AnthropicProvider.php

require_once __DIR__ . '/BaseProvider.php';

class AnthropicProvider extends BaseProvider
{
    public function generateMarketing($name, $description, $category, $brandVoice, $contentTone)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => (int) $this->settings['ai_max_tokens'],
            'temperature' => (float) $this->settings['ai_temperature'],
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }

    public function generateEnhancedMarketing($name, $description, $category, $imageInsights, $brandVoice, $contentTone, $existingMarketingData = null)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $prompt = "Item: {$name}\nDescription: {$description}\nCategory: {$category}\nImage Analysis: {$imageInsights}\nVoice: {$brandVoice}\nTone: {$contentTone}\n\nReturn JSON with: title, description, keywords, selling_points.";

        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => (int) $this->settings['ai_max_tokens'],
            'temperature' => (float) $this->settings['ai_temperature'],
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }

    public function generateCost($name, $description, $category)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $prompt = "Estimate production cost for: {$name} ({$category}). Description: {$description}. Return JSON: {\"cost\": <number>, \"reasoning\": \"string\"}";

        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => 500,
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }

    public function generatePricing($name, $description, $category, $cost_price)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => 1000,
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }

    public function generateDimensions($name, $description, $category)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $prompt = $this->buildDimensionsPrompt($name, $description, $category);
        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => 200,
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }

    public function analyzeItemImage($imagePath, $existingCategories = [])
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $img = $this->imageToBase64($imagePath);
        $cats = implode(', ', $existingCategories);
        $prompt = "Analyze this image of a product for an online store. Suggest a creative product name, detailed description, and most appropriate category from these options: {$cats}. Return ONLY valid JSON with these exact fields: {\"category\": \"string\", \"title\": \"string\", \"description\": \"string\", \"confidence\": \"high|medium|low\", \"reasoning\": \"string\"}.";

        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => 1000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $img['mime_type'],
                                'data' => $img['data']
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ];

        error_log("AnthropicProvider.analyzeItemImage: Calling API with model=" . $this->settings['anthropic_model']);

        try {
            $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2023-06-01'
            ]);

            error_log("AnthropicProvider.analyzeItemImage: API response=" . json_encode($response));
            $result = $this->parseAIResponse($response['content'][0]['text'] ?? '');
            error_log("AnthropicProvider.analyzeItemImage: Parsed result=" . json_encode($result));
            return $result;
        } catch (Exception $e) {
            error_log("AnthropicProvider.analyzeItemImage: API Error - " . $e->getMessage());
            throw $e;
        }
    }

    public function generateAltText($images, $name, $description, $category)
    {
        $results = [];
        foreach ((array) $images as $path) {
            $results[] = $this->analyzeItemImage($path);
        }
        return $results;
    }

    public function generateReceipt($prompt)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => 200,
            'temperature' => 0.7,
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }

    public function generateMarketingWithImages($name, $description, $category, $images, $brandVoice, $contentTone)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $img['mime_type'],
                        'data' => $img['data']
                    ]
                ];
            } catch (Exception $e) {
                error_log("Anthropic Image Error: " . $e->getMessage());
            }
        }

        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => (int) $this->settings['ai_max_tokens'],
            'temperature' => (float) $this->settings['ai_temperature'],
            'messages' => [['role' => 'user', 'content' => $content]]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }

    public function generatePricingWithImages($name, $description, $category, $cost_price, $images)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $img['mime_type'],
                        'data' => $img['data']
                    ]
                ];
            } catch (Exception $e) {
                error_log("Anthropic Image Error: " . $e->getMessage());
            }
        }

        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'messages' => [['role' => 'user', 'content' => $content]]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }

    public function detectObjectBoundaries($imagePath)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $img = $this->imageToBase64($imagePath);
        $prompt = "Analyze this image and determine the optimal crop boundaries to show only the main object(s) with minimal background. Return ONLY JSON: {\"crop_left_percent\": <number>, \"crop_top_percent\": <number>, \"crop_right_percent\": <number>, \"crop_bottom_percent\": <number>, \"confidence\": <number>, \"description\": \"string\"}. Percentages are 0.0 to 1.0.";

        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => 300,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $img['mime_type'],
                                'data' => $img['data']
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }

    public function getModels()
    {
        return [
            ['id' => 'claude-sonnet-4-5-20250929', 'name' => 'Claude 4.5 Sonnet', 'description' => 'Latest flagship model - best overall performance'],
            ['id' => 'claude-3-5-sonnet-latest', 'name' => 'Claude 3.5 Sonnet (Latest Alias)', 'description' => 'Alias - auto-updates to newest snapshot'],
            ['id' => 'claude-3-5-haiku-latest', 'name' => 'Claude 3.5 Haiku (Latest Alias)', 'description' => 'Fast model - auto-updates to newest snapshot'],
            ['id' => 'claude-3-opus-latest', 'name' => 'Claude 3 Opus (Latest Alias)', 'description' => 'Advanced reasoning - auto-updates']
        ];
    }

    public function supportsImages(): bool
    {
        return true;
    }
    public function generateCostWithImages($name, $description, $category, $images)
    {
        $apiKey = $this->settings['anthropic_api_key'];
        if (empty($apiKey))
            throw new Exception("Anthropic API key missing");

        $prompt = "Estimate production cost for: {$name} ({$category}). Description: {$description}. Analyze the provided images for materials, complexity, and quality. Return JSON: {\"cost\": <number>, \"reasoning\": \"string\", \"confidence\": \"string\", \"breakdown\": {\"Materials\": <number>, \"Labor\": <number>, \"Equipment\": <number>, \"Energy\": <number>}, \"analysis\": {\"detected_materials\": [], \"detected_features\": [], \"complexity_score\": <number>, \"skill_level_required\": \"string\"}}";
        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $img['media_type'],
                        'data' => $img['data']
                    ]
                ];
            } catch (Exception $e) {
                error_log("Anthropic Image Error: " . $e->getMessage());
            }
        }

        $data = [
            'model' => $this->settings['anthropic_model'],
            'max_tokens' => 1000,
            'temperature' => 0.3,
            'messages' => [['role' => 'user', 'content' => $content]]
        ];

        $response = $this->makeAPICall('https://api.anthropic.com/v1/messages', $data, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);

        return $this->parseAIResponse($response['content'][0]['text'] ?? '');
    }
}
