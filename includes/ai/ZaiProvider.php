<?php
// includes/ai/ZaiProvider.php
//
// Z.AI (GLM) provider — OpenAI-compatible Chat Completions format.
// Vision-capable: GLM models accept image_url content parts.
// Endpoint: https://api.z.ai/api/paas/v4/chat/completions

require_once __DIR__ . '/BaseProvider.php';

class ZaiProvider extends BaseProvider
{
    private const API_URL = 'https://api.z.ai/api/paas/v4/chat/completions';

    /**
     * Build the messages array for Chat Completions.
     * Images are passed as image_url content parts (data URI).
     */
    private function buildMessages(string $systemPrompt, string $userText, array $imageUrls = []): array
    {
        $messages = [];

        if ($systemPrompt !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt
            ];
        }

        if (empty($imageUrls)) {
            $messages[] = [
                'role' => 'user',
                'content' => $userText
            ];
        } else {
            // Multimodal: text + images as content parts
            $content = [['type' => 'text', 'text' => $userText]];
            foreach ($imageUrls as $url) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
            }
            $messages[] = [
                'role' => 'user',
                'content' => $content
            ];
        }

        return $messages;
    }

    /**
     * Extract the assistant text from a Chat Completions response.
     */
    private function extractResponseText(array $response): string
    {
        if (isset($response['choices'][0]['message']['content']) && is_string($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }
        return '';
    }

    /**
     * Make a Chat Completions call.
     */
    private function makeChatCall(array $data): array
    {
        $apiKey = $this->settings['zai_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Z.AI API key missing");
        }

        return $this->makeAPICall(self::API_URL, $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
    }

    /**
     * Convert local file paths / data URIs to image URL strings for the API.
     */
    private function resolveImageUrls(array $images): array
    {
        $urls = [];
        foreach ((array) $images as $path) {
            try {
                $img = $this->imageToBase64($path);
                $urls[] = "data:{$img['mime_type']};base64,{$img['data']}";
            } catch (Exception $e) {
                error_log("Z.AI Image Error: " . $e->getMessage());
            }
        }
        return $urls;
    }

    // --- AIProviderInterface implementation ---

    public function generateMarketing($name, $description, $category, $brandVoice, $contentTone)
    {
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a marketing expert. Respond strictly with JSON.', $prompt),
            'temperature' => (float) ($this->settings['ai_temperature'] ?? 0.7),
            'max_tokens' => (int) ($this->settings['ai_max_tokens'] ?? 1000)
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generateEnhancedMarketing($name, $description, $category, $imageInsights, $brandVoice, $contentTone, $existingMarketingData = null)
    {
        $prompt = "Item: {$name}\nDescription: {$description}\nCategory: {$category}\nImage Analysis: {$imageInsights}\nVoice: {$brandVoice}\nTone: {$contentTone}\n\nReturn JSON with: title, description, keywords, selling_points.";
        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a marketing expert with visual insights. Respond strictly with JSON.', $prompt),
            'temperature' => (float) ($this->settings['ai_temperature'] ?? 0.7),
            'max_tokens' => (int) ($this->settings['ai_max_tokens'] ?? 1000)
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generateCost($name, $description, $category)
    {
        $prompt = "Estimate production cost for: {$name} ({$category}). Description: {$description}. Return JSON: {\"cost\": <number>, \"reasoning\": \"string\"}";
        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a cost estimation expert. Respond strictly with JSON.', $prompt),
            'temperature' => 0.3,
            'max_tokens' => (int) ($this->settings['ai_max_tokens'] ?? 1000)
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generateCostWithImages($name, $description, $category, $images)
    {
        $prompt = "Estimate production cost for: {$name} ({$category}). Description: {$description}. Analyze the provided images for materials, complexity, and quality. Return JSON: {\"cost\": <number>, \"reasoning\": \"string\", \"confidence\": \"string\", \"breakdown\": {\"Materials\": <number>, \"Labor\": <number>, \"Equipment\": <number>, \"Energy\": <number>}, \"analysis\": {\"detected_materials\": [], \"detected_features\": [], \"complexity_score\": <number>, \"skill_level_required\": \"string\"}}";
        $imageUrls = $this->resolveImageUrls($images);
        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a manufacturing and cost estimation expert. Analyze images and text to suggest production costs. Respond strictly with JSON.', $prompt, $imageUrls),
            'temperature' => 0.3,
            'max_tokens' => 1000
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generatePricing($name, $description, $category, $cost_price)
    {
        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a pricing expert. Respond strictly with JSON.', $prompt),
            'temperature' => 0.7,
            'max_tokens' => (int) ($this->settings['ai_max_tokens'] ?? 1000)
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generatePricingWithImages($name, $description, $category, $cost_price, $images)
    {
        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $imageUrls = $this->resolveImageUrls($images);
        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a pricing expert. Analyze images and text to suggest pricing. Respond strictly with JSON.', $prompt, $imageUrls),
            'temperature' => 0.7,
            'max_tokens' => 1000
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generateDimensions($name, $description, $category)
    {
        $prompt = $this->buildDimensionsPrompt($name, $description, $category);
        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a shipping logistics expert. Respond strictly with JSON.', $prompt),
            'temperature' => 0.3,
            'max_tokens' => (int) ($this->settings['ai_max_tokens'] ?? 1000)
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function analyzeItemImage($imagePath, $existingCategories = [])
    {
        $img = $this->imageToBase64($imagePath);
        $cats = implode(', ', $existingCategories);
        $prompt = "Analyze this product image. Known categories: {$cats}. Return JSON: {category, title, description, confidence, reasoning}.";
        $imageUrl = "data:{$img['mime_type']};base64,{$img['data']}";

        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a product image analysis expert. Respond strictly with JSON.', $prompt, [$imageUrl]),
            'temperature' => 0.2,
            'max_tokens' => 600
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
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
        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a helpful assistant creating personalized receipt messages. Return JSON with "title" and "content".', $prompt),
            'temperature' => 0.7,
            'max_tokens' => 200
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generateMarketingWithImages($name, $description, $category, $images, $brandVoice, $contentTone)
    {
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $imageUrls = $this->resolveImageUrls($images);
        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are a marketing expert. Analyze images and text to create content. Respond strictly with JSON.', $prompt, $imageUrls),
            'temperature' => (float) ($this->settings['ai_temperature'] ?? 0.7),
            'max_tokens' => (int) ($this->settings['ai_max_tokens'] ?? 1000)
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function detectObjectBoundaries($imagePath)
    {
        $img = $this->imageToBase64($imagePath);
        $prompt = "Analyze this image and determine the optimal crop boundaries to show only the main object(s) with minimal background. Return ONLY JSON: {\"crop_left_percent\": <number>, \"crop_top_percent\": <number>, \"crop_right_percent\": <number>, \"crop_bottom_percent\": <number>, \"confidence\": <number>, \"description\": \"string\"}. Percentages are 0.0 to 1.0.";
        $imageUrl = "data:{$img['mime_type']};base64,{$img['data']}";

        $data = [
            'model' => $this->settings['zai_model'],
            'messages' => $this->buildMessages('You are an image cropping expert. Respond strictly with JSON.', $prompt, [$imageUrl]),
            'temperature' => 0.1,
            'max_tokens' => 300
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function getModels()
    {
        return [
            ['id' => 'glm-4.6', 'name' => 'GLM-4.6', 'description' => 'Balanced flagship (vision)'],
            ['id' => 'glm-4.5', 'name' => 'GLM-4.5', 'description' => 'Capable multimodal model'],
            ['id' => 'glm-4.5-air', 'name' => 'GLM-4.5 Air', 'description' => 'Fast and affordable'],
            ['id' => 'glm-4.7', 'name' => 'GLM-4.7', 'description' => 'Newer release'],
            ['id' => 'glm-5', 'name' => 'GLM-5', 'description' => 'Latest generation flagship'],
            ['id' => 'glm-5-turbo', 'name' => 'GLM-5 Turbo', 'description' => 'Fast latest-gen'],
            ['id' => 'glm-5.1', 'name' => 'GLM-5.1', 'description' => 'Advanced latest-gen'],
            ['id' => 'glm-5.2', 'name' => 'GLM-5.2', 'description' => 'Top-tier latest-gen']
        ];
    }

    public function supportsImages(): bool
    {
        return true;
    }
}
