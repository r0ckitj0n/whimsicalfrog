<?php
// includes/ai/OpenAIProvider.php

require_once __DIR__ . '/BaseProvider.php';

class OpenAIProvider extends BaseProvider
{
    private function buildInput(string $text, array $images = []): array
    {
        $content = [
            ['type' => 'input_text', 'text' => $text]
        ];

        foreach ($images as $imageUrl) {
            $content[] = ['type' => 'input_image', 'image_url' => ['url' => $imageUrl]];
        }

        return [
            [
                'role' => 'user',
                'content' => $content
            ]
        ];
    }

    private function extractResponseText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        $texts = [];
        if (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (isset($item['type']) && $item['type'] === 'message' && isset($item['content']) && is_array($item['content'])) {
                    foreach ($item['content'] as $content) {
                        if (!is_array($content)) {
                            continue;
                        }
                        $type = $content['type'] ?? '';
                        if (($type === 'output_text' || $type === 'text') && isset($content['text']) && is_string($content['text'])) {
                            $texts[] = $content['text'];
                        }
                    }
                } elseif (isset($item['content']) && is_string($item['content'])) {
                    $texts[] = $item['content'];
                }
            }
        }

        if (!empty($texts)) {
            return implode("\n", $texts);
        }

        if (isset($response['choices'][0]['message']['content']) && is_string($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }

        return '';
    }

    private function makeResponsesCall(array $data, string $apiKey): array
    {
        return $this->makeAPICall('https://api.openai.com/v1/responses', $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
    }

    public function generateMarketing($name, $description, $category, $brandVoice, $contentTone)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $data = [
            'model' => $this->settings['openai_model'],
            'instructions' => 'You are a marketing expert. Respond strictly with JSON.',
            'input' => $this->buildInput($prompt),
            'temperature' => (float) $this->settings['ai_temperature']
        ];
        $data['max_output_tokens'] = (int) $this->settings['ai_max_tokens'];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function generateEnhancedMarketing($name, $description, $category, $imageInsights, $brandVoice, $contentTone, $existingMarketingData = null)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $prompt = "Item: {$name}\nDescription: {$description}\nCategory: {$category}\nImage Analysis: {$imageInsights}\nVoice: {$brandVoice}\nTone: {$contentTone}\n\nReturn JSON with: title, description, keywords, selling_points.";

        $data = [
            'model' => $this->settings['openai_model'],
            'instructions' => 'You are a marketing expert with visual insights. Respond strictly with JSON.',
            'input' => $this->buildInput($prompt),
            'temperature' => (float) $this->settings['ai_temperature'],
            'max_output_tokens' => (int) $this->settings['ai_max_tokens']
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function generateCost($name, $description, $category)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $prompt = "Estimate production cost for: {$name} ({$category}). Description: {$description}. Return JSON: {\"cost\": <number>, \"reasoning\": \"string\"}";

        $data = [
            'model' => $this->settings['openai_model'],
            'input' => $this->buildInput($prompt),
            'temperature' => 0.3,
            'max_output_tokens' => (int) $this->settings['ai_max_tokens']
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function generatePricing($name, $description, $category, $cost_price)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $data = [
            'model' => $this->settings['openai_model'],
            'input' => $this->buildInput($prompt),
            'temperature' => 0.7,
            'max_output_tokens' => (int) $this->settings['ai_max_tokens']
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function generateDimensions($name, $description, $category)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $prompt = $this->buildDimensionsPrompt($name, $description, $category);
        $data = [
            'model' => $this->settings['openai_model'],
            'input' => $this->buildInput($prompt),
            'temperature' => 0.3,
            'max_output_tokens' => (int) $this->settings['ai_max_tokens']
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function analyzeItemImage($imagePath, $existingCategories = [])
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $img = $this->imageToBase64($imagePath);
        $cats = implode(', ', $existingCategories);
        $prompt = "Analyze this image. Categories: {$cats}. Return JSON: {category, title, description, confidence, reasoning}.";

        $imageUrl = "data:{$img['mime_type']};base64,{$img['data']}";
        $data = [
            'model' => 'gpt-4o', // Vision-capable model
            'input' => $this->buildInput($prompt, [$imageUrl]),
            'max_output_tokens' => 500
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function generateAltText($images, $name, $description, $category)
    {
        // Implementation for multiple images
        $results = [];
        foreach ((array) $images as $path) {
            $results[] = $this->analyzeItemImage($path);
        }
        return $results;
    }

    public function generateReceipt($prompt)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $data = [
            'model' => $this->settings['openai_model'],
            'instructions' => 'You are a helpful assistant creating personalized receipt messages. Return JSON with "title" and "content".',
            'input' => $this->buildInput($prompt),
            'temperature' => 0.7,
            'max_output_tokens' => 200
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function generateMarketingWithImages($name, $description, $category, $images, $brandVoice, $contentTone)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $imageUrls = [];
        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $imageUrls[] = "data:{$img['mime_type']};base64,{$img['data']}";
            } catch (Exception $e) {
                error_log("OpenAI Image Error: " . $e->getMessage());
            }
        }

        $data = [
            'model' => 'gpt-4o',
            'instructions' => 'You are a marketing expert. Analyze images and text to create content. Respond strictly with JSON.',
            'input' => $this->buildInput($prompt, $imageUrls),
            'temperature' => (float) $this->settings['ai_temperature'],
            'max_output_tokens' => (int) $this->settings['ai_max_tokens']
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function generatePricingWithImages($name, $description, $category, $cost_price, $images)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $imageUrls = [];
        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $imageUrls[] = "data:{$img['mime_type']};base64,{$img['data']}";
            } catch (Exception $e) {
                error_log("OpenAI Image Error: " . $e->getMessage());
            }
        }

        $data = [
            'model' => 'gpt-4o',
            'instructions' => 'You are a pricing expert. Analyze images and text to suggest pricing. Respond strictly with JSON.',
            'input' => $this->buildInput($prompt, $imageUrls),
            'temperature' => 0.7,
            'max_output_tokens' => 1000
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function generateCostWithImages($name, $description, $category, $images)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $prompt = "Estimate production cost for: {$name} ({$category}). Description: {$description}. Analyze the provided images for materials, complexity, and quality. Return JSON: {\"cost\": <number>, \"reasoning\": \"string\", \"confidence\": \"string\", \"breakdown\": {\"Materials\": <number>, \"Labor\": <number>, \"Equipment\": <number>, \"Energy\": <number>}, \"analysis\": {\"detected_materials\": [], \"detected_features\": [], \"complexity_score\": <number>, \"skill_level_required\": \"string\"}}";

        $imageUrls = [];
        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $imageUrls[] = "data:{$img['mime_type']};base64,{$img['data']}";
            } catch (Exception $e) {
                error_log("OpenAI Image Error: " . $e->getMessage());
            }
        }

        $data = [
            'model' => 'gpt-4o',
            'instructions' => 'You are a manufacturing and cost estimation expert. Analyze images and text to suggest production costs. Respond strictly with JSON.',
            'input' => $this->buildInput($prompt, $imageUrls),
            'temperature' => 0.3,
            'max_output_tokens' => 1000
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function detectObjectBoundaries($imagePath)
    {
        $apiKey = $this->settings['openai_api_key'];
        if (empty($apiKey))
            throw new Exception("OpenAI API key missing");

        $img = $this->imageToBase64($imagePath);
        $prompt = "Analyze this image and determine the optimal crop boundaries to show only the main object(s) with minimal background. Return ONLY JSON: {\"crop_left_percent\": <number>, \"crop_top_percent\": <number>, \"crop_right_percent\": <number>, \"crop_bottom_percent\": <number>, \"confidence\": <number>, \"description\": \"string\"}. Percentages are 0.0 to 1.0.";

        $imageUrl = "data:{$img['mime_type']};base64,{$img['data']}";
        $data = [
            'model' => 'gpt-4o',
            'input' => $this->buildInput($prompt, [$imageUrl]),
            'temperature' => 0.1,
            'max_output_tokens' => 300
        ];

        $response = $this->makeResponsesCall($data, $apiKey);

        return $this->parseAIResponse($this->extractResponseText($response));
    }

    public function getModels()
    {
        return [
            ['id' => 'gpt-5.2', 'name' => 'GPT-5.2', 'description' => 'Latest flagship model (Feb 2026)'],
            ['id' => 'gpt-5.2-codex', 'name' => 'GPT-5.2 Codex', 'description' => 'Best for coding tasks'],
            ['id' => 'gpt-5', 'name' => 'GPT-5', 'description' => 'Major release (Aug 2025)'],
            ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'description' => 'API-only access - being retired'],
            ['id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'description' => 'Fast and capable (legacy)'],
            ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo', 'description' => 'Fast and affordable (legacy)']
        ];
    }

    public function supportsImages(): bool
    {
        return true;
    }
}
