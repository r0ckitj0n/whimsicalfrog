<?php
// includes/ai/DeepseekProvider.php
//
// Deepseek provider — OpenAI-compatible Chat Completions format.
// TEXT-ONLY: deepseek-v4-flash does NOT support image input.
// Endpoint: https://api.deepseek.com/v1/chat/completions

require_once __DIR__ . '/BaseProvider.php';

class DeepseekProvider extends BaseProvider
{
    private const API_URL = 'https://api.deepseek.com/v1/chat/completions';

    /**
     * Build the messages array for Chat Completions.
     * Deepseek is text-only — no image support.
     */
    private function buildMessages(string $systemPrompt, string $userText): array
    {
        $messages = [];

        if ($systemPrompt !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userText
        ];

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
        $apiKey = $this->settings['deepseek_api_key'];
        if (empty($apiKey)) {
            throw new Exception("Deepseek API key missing");
        }

        return $this->makeAPICall(self::API_URL, $data, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
    }

    // --- AIProviderInterface implementation ---
    // Note: *WithImages methods delegate to text-only variants since
    // deepseek-v4-flash does not accept image input.

    public function generateMarketing($name, $description, $category, $brandVoice, $contentTone)
    {
        $prompt = $this->buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone);
        $data = [
            'model' => $this->settings['deepseek_model'],
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
            'model' => $this->settings['deepseek_model'],
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
            'model' => $this->settings['deepseek_model'],
            'messages' => $this->buildMessages('You are a cost estimation expert. Respond strictly with JSON.', $prompt),
            'temperature' => 0.3,
            'max_tokens' => (int) ($this->settings['ai_max_tokens'] ?? 1000)
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generateCostWithImages($name, $description, $category, $images)
    {
        // Deepseek has no vision — delegate to text-only cost generation
        return $this->generateCost($name, $description, $category);
    }

    public function generatePricing($name, $description, $category, $cost_price)
    {
        $prompt = $this->buildPricingPrompt($name, $description, $category, $cost_price);
        $data = [
            'model' => $this->settings['deepseek_model'],
            'messages' => $this->buildMessages('You are a pricing expert. Respond strictly with JSON.', $prompt),
            'temperature' => 0.7,
            'max_tokens' => (int) ($this->settings['ai_max_tokens'] ?? 1000)
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generatePricingWithImages($name, $description, $category, $cost_price, $images)
    {
        // Deepseek has no vision — delegate to text-only pricing generation
        return $this->generatePricing($name, $description, $category, $cost_price);
    }

    public function generateDimensions($name, $description, $category)
    {
        $prompt = $this->buildDimensionsPrompt($name, $description, $category);
        $data = [
            'model' => $this->settings['deepseek_model'],
            'messages' => $this->buildMessages('You are a shipping logistics expert. Respond strictly with JSON.', $prompt),
            'temperature' => 0.3,
            'max_tokens' => (int) ($this->settings['ai_max_tokens'] ?? 1000)
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function analyzeItemImage($imagePath, $existingCategories = [])
    {
        // Deepseek has no vision capability.
        throw new Exception("Deepseek does not support image analysis (text-only model)");
    }

    public function generateAltText($images, $name, $description, $category)
    {
        throw new Exception("Deepseek does not support image analysis (text-only model)");
    }

    public function generateReceipt($prompt)
    {
        $data = [
            'model' => $this->settings['deepseek_model'],
            'messages' => $this->buildMessages('You are a helpful assistant creating personalized receipt messages. Return JSON with "title" and "content".', $prompt),
            'temperature' => 0.7,
            'max_tokens' => 200
        ];
        return $this->parseAIResponse($this->extractResponseText($this->makeChatCall($data)));
    }

    public function generateMarketingWithImages($name, $description, $category, $images, $brandVoice, $contentTone)
    {
        // Deepseek has no vision — delegate to text-only marketing generation
        return $this->generateMarketing($name, $description, $category, $brandVoice, $contentTone);
    }

    public function detectObjectBoundaries($imagePath)
    {
        throw new Exception("Deepseek does not support image analysis (text-only model)");
    }

    public function getModels()
    {
        return [
            ['id' => 'deepseek-chat', 'name' => 'Deepseek Chat', 'description' => 'General chat (maps to v4-flash)'],
            ['id' => 'deepseek-v4-flash', 'name' => 'Deepseek V4 Flash', 'description' => 'Fast, capable (text-only)'],
            ['id' => 'deepseek-v4-pro', 'name' => 'Deepseek V4 Pro', 'description' => 'Higher quality (text-only)'],
            ['id' => 'deepseek-reasoner', 'name' => 'Deepseek Reasoner', 'description' => 'Reasoning model (text-only)']
        ];
    }

    public function supportsImages(): bool
    {
        return false;
    }
}
