<?php
// includes/ai/MetaProvider.php

require_once __DIR__ . '/BaseProvider.php';

class MetaProvider extends BaseProvider
{
    public function generateMarketing($name, $description, $category, $brandVoice, $contentTone)
    {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey))
            throw new Exception("Meta API key missing");

        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [
                ['role' => 'system', 'content' => 'You are a marketing expert. Respond strictly with JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => (float) $this->settings['ai_temperature'],
            'max_tokens' => (int) $this->settings['ai_max_tokens']
        ];

        $response = $this->makeAPICall('https://openrouter.ai/api/v1/chat/completions', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://whimsicalfrog.us',
            'X-Title: WhimsicalFrog AI Assistant'
        ]);

        return $this->parseAIResponse($response['choices'][0]['message']['content'] ?? '');
    }

    public function generateEnhancedMarketing($name, $description, $category, $imageInsights, $brandVoice, $contentTone, $existingMarketingData = null)
    {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey))
            throw new Exception("Meta API key missing");

        $prompt = "Item: {$name}\nDescription: {$description}\nCategory: {$category}\nImage Analysis: {$imageInsights}\nVoice: {$brandVoice}\nTone: {$contentTone}\n\nReturn JSON with: title, description, keywords, selling_points.";

        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [
                ['role' => 'system', 'content' => 'You are a marketing expert with visual insights. Respond strictly with JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => (float) $this->settings['ai_temperature']
        ];

        $response = $this->makeAPICall('https://openrouter.ai/api/v1/chat/completions', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://whimsicalfrog.us',
            'X-Title: WhimsicalFrog AI Assistant'
        ]);

        return $this->parseAIResponse($response['choices'][0]['message']['content'] ?? '');
    }

    public function generateCost($name, $description, $category)
    {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey))
            throw new Exception("Meta API key missing");

        $prompt = "Estimate production cost for: {$name} ({$category}). Description: {$description}. Return JSON: {\"cost\": <number>, \"reasoning\": \"string\"}";

        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.3
        ];

        $response = $this->makeAPICall('https://openrouter.ai/api/v1/chat/completions', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://whimsicalfrog.us',
            'X-Title: WhimsicalFrog AI Assistant'
        ]);

        return $this->parseAIResponse($response['choices'][0]['message']['content'] ?? '');
    }

    public function generatePricing($name, $description, $category, $cost_price)
    {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey))
            throw new Exception("Meta API key missing");

        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.7
        ];

        $response = $this->makeAPICall('https://openrouter.ai/api/v1/chat/completions', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://whimsicalfrog.us',
            'X-Title: WhimsicalFrog AI Assistant'
        ]);

        return $this->parseAIResponse($response['choices'][0]['message']['content'] ?? '');
    }

    public function generateDimensions($name, $description, $category)
    {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey))
            throw new Exception("Meta API key missing");

        $prompt = $this->buildDimensionsPrompt($name, $description, $category);
        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.3
        ];

        $response = $this->makeAPICall('https://openrouter.ai/api/v1/chat/completions', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://whimsicalfrog.us',
            'X-Title: WhimsicalFrog AI Assistant'
        ]);

        return $this->parseAIResponse($response['choices'][0]['message']['content'] ?? '');
    }

    public function analyzeItemImage($imagePath, $existingCategories = [])
    {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey))
            throw new Exception("Meta API key missing");

        $img = $this->imageToBase64($imagePath);
        $cats = implode(', ', $existingCategories);
        $prompt = "Analyze this image. Categories: {$cats}. Return JSON: {category, title, description, confidence, reasoning}.";

        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$img['mime_type']};base64,{$img['data']}"]]
                    ]
                ]
            ],
            'max_tokens' => 500
        ];

        $response = $this->makeAPICall('https://openrouter.ai/api/v1/chat/completions', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://whimsicalfrog.us',
            'X-Title: WhimsicalFrog AI Assistant'
        ]);

        return $this->parseAIResponse($response['choices'][0]['message']['content'] ?? '');
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
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey))
            throw new Exception("Meta API key missing");

        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant creating personalized receipt messages. Return JSON with "title" and "content".'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 200
        ];

        $response = $this->makeAPICall('https://openrouter.ai/api/v1/chat/completions', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://whimsicalfrog.us',
            'X-Title: WhimsicalFrog AI Assistant'
        ]);

        return $this->parseAIResponse($response['choices'][0]['message']['content'] ?? '');
    }

    public function detectObjectBoundaries($imagePath)
    {
        $apiKey = $this->settings['meta_api_key'];
        if (empty($apiKey))
            throw new Exception("Meta API key missing");

        $img = $this->imageToBase64($imagePath);
        $prompt = "Analyze this image and determine the optimal crop boundaries to show only the main object(s) with minimal background. Return ONLY JSON: {\"crop_left_percent\": <number>, \"crop_top_percent\": <number>, \"crop_right_percent\": <number>, \"crop_bottom_percent\": <number>, \"confidence\": <number>, \"description\": \"string\"}. Percentages are 0.0 to 1.0.";

        $data = [
            'model' => $this->settings['meta_model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$img['mime_type']};base64,{$img['data']}"]]
                    ]
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 300
        ];

        $response = $this->makeAPICall('https://openrouter.ai/api/v1/chat/completions', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://whimsicalfrog.us',
            'X-Title: WhimsicalFrog AI Assistant'
        ]);

        return $this->parseAIResponse($response['choices'][0]['message']['content'] ?? '');
    }

    public function generateMarketingWithImages($name, $description, $category, $images, $brandVoice, $contentTone)
    {
        // MetaProvider doesn't have a specific combined image/marketing call yet, 
        // fallback to standard marketing or enhance with first image insights
        if (!empty($images) && $this->supportsImages()) {
            return $this->generateEnhancedMarketing($name, $description, $category, "Visual context from " . count($images) . " images", $brandVoice, $contentTone);
        }
        return $this->generateMarketing($name, $description, $category, $brandVoice, $contentTone);
    }

    public function generatePricingWithImages($name, $description, $category, $cost_price, $images)
    {
        // Fallback to standard pricing
        return $this->generatePricing($name, $description, $category, $cost_price);
    }

    public function generateCostWithImages($name, $description, $category, $images)
    {
        return $this->generateCost($name, $description, $category);
    }

    public function getModels()
    {
        return [
            ['id' => 'meta-llama/llama-4-maverick', 'name' => 'Llama 4 Maverick', 'description' => 'Latest - 400B params, multimodal'],
            ['id' => 'meta-llama/llama-4-scout', 'name' => 'Llama 4 Scout', 'description' => 'Fast - 109B params, 12 languages'],
            ['id' => 'meta-llama/llama-3.1-405b-instruct', 'name' => 'Llama 3.1 405B Instruct', 'description' => 'Most powerful open-source LLM'],
            ['id' => 'meta-llama/llama-3.1-70b-instruct', 'name' => 'Llama 3.1 70B Instruct', 'description' => 'Balanced performance'],
            ['id' => 'meta-llama/llama-3.1-8b-instruct', 'name' => 'Llama 3.1 8B Instruct', 'description' => 'Fast and affordable']
        ];
    }

    public function supportsImages(): bool
    {
        // Llama 3.2 Vision and other multimodal models on OpenRouter support images
        return strpos($this->settings['meta_model'], 'vision') !== false || strpos($this->settings['meta_model'], 'pixtral') !== false;
    }
}
