<?php
// includes/ai/GoogleProvider.php

require_once __DIR__ . '/BaseProvider.php';

class GoogleProvider extends BaseProvider
{
    public function generateMarketing($name, $description, $category, $brandVoice, $contentTone)
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => (float) $this->settings['ai_temperature'],
                'maxOutputTokens' => (int) $this->settings['ai_max_tokens']
            ]
        ];

        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function generateEnhancedMarketing($name, $description, $category, $imageInsights, $brandVoice, $contentTone, $existingMarketingData = null)
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $prompt = "Item: {$name}\nDescription: {$description}\nCategory: {$category}\nImage Analysis: {$imageInsights}\nVoice: {$brandVoice}\nTone: {$contentTone}\n\nReturn JSON with: title, description, keywords, selling_points.";
        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => (float) $this->settings['ai_temperature'],
                'maxOutputTokens' => (int) $this->settings['ai_max_tokens']
            ]
        ];

        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function generateCost($name, $description, $category)
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $prompt = "Estimate production cost for: {$name} ({$category}). Description: {$description}. Return JSON: {\"cost\": <number>, \"reasoning\": \"string\"}";
        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 500]
        ];

        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function generatePricing($name, $description, $category, $cost_price)
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 1000]
        ];

        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function generateDimensions($name, $description, $category)
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $prompt = $this->buildDimensionsPrompt($name, $description, $category);
        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 200]
        ];

        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function analyzeItemImage($imagePath, $existingCategories = [])
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $img = $this->imageToBase64($imagePath);
        $cats = implode(', ', $existingCategories);
        $prompt = "Analyze this image. Categories: {$cats}. Return JSON: {category, title, description, confidence, reasoning}.";

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $img['mime_type'], 'data' => $img['data']]]
                    ]
                ]
            ],
            'generationConfig' => ['maxOutputTokens' => 1000]
        ];

        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
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
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $enhancedPrompt = "You are a helpful assistant creating personalized receipt messages. Return JSON with \"title\" and \"content\".\n\n" . $prompt;
        $data = [
            'contents' => [['parts' => [['text' => $enhancedPrompt]]]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 200]
        ];

        $model = $this->settings['google_model'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function generateMarketingWithImages($name, $description, $category, $images, $brandVoice, $contentTone)
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $parts = [['text' => $prompt]];

        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $parts[] = ['inline_data' => ['mime_type' => $img['mime_type'], 'data' => $img['data']]];
            } catch (Exception $e) {
                error_log("Google Image Error: " . $e->getMessage());
            }
        }

        $data = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'temperature' => (float) $this->settings['ai_temperature'],
                'maxOutputTokens' => (int) $this->settings['ai_max_tokens']
            ]
        ];

        $model = 'gemini-1.5-flash'; // Good for images
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function generatePricingWithImages($name, $description, $category, $cost_price, $images)
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $parts = [['text' => $prompt]];

        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $parts[] = ['inline_data' => ['mime_type' => $img['mime_type'], 'data' => $img['data']]];
            } catch (Exception $e) {
                error_log("Google Image Error: " . $e->getMessage());
            }
        }

        $data = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 1000]
        ];

        $model = 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function generateCostWithImages($name, $description, $category, $images)
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $prompt = "Estimate production cost for: {$name} ({$category}). Description: {$description}. Analyze the provided images for materials, complexity, and quality. Return JSON: {\"cost\": <number>, \"reasoning\": \"string\", \"confidence\": \"string\", \"breakdown\": {\"Materials\": <number>, \"Labor\": <number>, \"Equipment\": <number>, \"Energy\": <number>}, \"analysis\": {\"detected_materials\": [], \"detected_features\": [], \"complexity_score\": <number>, \"skill_level_required\": \"string\"}}";
        $parts = [['text' => $prompt]];

        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $parts[] = ['inline_data' => ['mime_type' => $img['mime_type'], 'data' => $img['data']]];
            } catch (Exception $e) {
                error_log("Google Image Error: " . $e->getMessage());
            }
        }

        $data = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 1000]
        ];

        $model = 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function detectObjectBoundaries($imagePath)
    {
        $apiKey = $this->settings['google_api_key'];
        if (empty($apiKey))
            throw new Exception("Google API key missing");

        $img = $this->imageToBase64($imagePath);
        $prompt = "Analyze this image and determine the optimal crop boundaries to show only the main object(s) with minimal background. Return ONLY JSON: {\"crop_left_percent\": <number>, \"crop_top_percent\": <number>, \"crop_right_percent\": <number>, \"crop_bottom_percent\": <number>, \"confidence\": <number>, \"description\": \"string\"}. Percentages are 0.0 to 1.0.";

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $img['mime_type'], 'data' => $img['data']]]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 300
            ]
        ];

        $model = 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        return $this->parseAIResponse($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    public function getModels()
    {
        return [
            ['id' => 'gemini-3-pro', 'name' => 'Gemini 3 Pro', 'description' => 'Most powerful - exceptional reasoning'],
            ['id' => 'gemini-3-flash', 'name' => 'Gemini 3 Flash', 'description' => 'Default - rapid intelligent responses'],
            ['id' => 'gemini-3-deep-think', 'name' => 'Gemini 3 Deep Think', 'description' => 'Deep reasoning mode'],
            ['id' => 'gemini-ultra-2', 'name' => 'Gemini Ultra 2', 'description' => 'Advanced multimodal (Jan 2026)'],
            ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro', 'description' => 'Previous generation (stable)'],
            ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'description' => 'Legacy - still available'],
            ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'description' => 'Legacy - fast and efficient']
        ];
    }

    public function supportsImages(): bool
    {
        return true;
    }
}
